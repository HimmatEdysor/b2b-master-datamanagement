# RDS tenant provisioning user (`TENANT_DB_*`)

AWS RDS **does not allow** `GRANT ALL PRIVILEGES ON *.*` or `WITH GRANT OPTION`.  
This app uses **explicit privileges** everywhere (portal + setup SQL).

## One-time RDS setup (run as RDS master user)

Replace passwords and database names, then run in MySQL:

```sql
CREATE USER IF NOT EXISTS 'b2b_master'@'%' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';

GRANT CREATE, DROP, ALTER, INDEX, CREATE USER, PROCESS ON *.* TO 'b2b_master'@'%';
GRANT SELECT, SHOW VIEW ON `b2b_live_database`.* TO 'b2b_master'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX,
  CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, TRIGGER, REFERENCES
  ON `b2b_tenant_%`.* TO 'b2b_master'@'%';

FLUSH PRIVILEGES;
```

`.env` on the app server:

```env
TENANT_DB_HOST=your-instance.region.rds.amazonaws.com
TENANT_DB_USERNAME=b2b_master
TENANT_DB_PASSWORD=YOUR_STRONG_PASSWORD
TENANT_TEMPLATE_DATABASE=b2b_live_database
TENANT_DB_CLONE_METHOD=pdo
TENANT_DB_GRANT_ADMIN_ON_CREATE=false
TENANT_DB_SHARED_CREDENTIALS=true
```

With `TENANT_DB_SHARED_CREDENTIALS=true`, provisioning does **not** run `CREATE USER` / per-tenant `GRANT` (RDS blocks `GRANT OPTION`). Each company row stores the same `b2b_master` credentials; CRM connects using that user and the company `database_name`.

Verify:

```bash
php artisan config:clear
php artisan tenant:db-admin-check
```

## Error 1044 on provision check database

The self-check uses the **same name pattern** as real tenants: `b2b_tenant_{slug}` (default slug `provisioncheck` → `b2b_tenant_provisioncheck`). If `b2b_master` only has privileges on `b2b_tenant_%`.* but not `CREATE` on `*.*`, that fails with **1044**.

**Fix:** grant global CREATE + DROP (required to run `CREATE DATABASE`):

```sql
GRANT CREATE, DROP, ALTER, INDEX, CREATE USER, PROCESS ON *.* TO 'b2b_master'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX,
  CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, TRIGGER, REFERENCES
  ON `b2b_tenant_%`.* TO 'b2b_master'@'%';
FLUSH PRIVILEGES;
```

If per-DB `GRANT` is blocked on RDS (no GRANT OPTION), pre-grant `b2b_tenant_%`.* as above and optionally set `TENANT_DB_GRANT_ADMIN_ON_CREATE=false`.

## Error 1044 on `b2b_tenant_{slug}` after CREATE DATABASE

`CREATE` on `*.*` lets `b2b_master` **create** the database but **not use** it until database-level grants exist.

The app now runs **GRANT → FLUSH → verify `USE`** before cloning schema. If that still fails, run on RDS:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX,
  CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, TRIGGER, REFERENCES
  ON `b2b_tenant_%`.* TO 'b2b_master'@'%';
FLUSH PRIVILEGES;
```

`b2b_tenant_appppp` matches `b2b_tenant_%` — without that grant line, provisioning stops with 1044.

## Provisioning order (per company)

1. Reserve `b2b_tenant_{slug}` on company row  
2. `CREATE DATABASE` (empty tenant DB)  
3. `GRANT` + verify access for `b2b_master` on that DB (or rely on `b2b_tenant_%`.* wildcard)  
4. Clone schema from template (PDO, no mysqldump FLUSH)  
5. Seed reference tables  
6. `CREATE USER` + `GRANT` for dedicated CRM user (specific privileges, not ALL)  
7. Default CRM admin login  

## Privilege lists (config)

Override in `.env` as comma-separated lists if needed:

| Config key | Used for |
|------------|----------|
| `TENANT_DB_GLOBAL_PRIVILEGES` | `*.*` for provisioning user |
| `TENANT_DB_TEMPLATE_PRIVILEGES` | Read template DB |
| `TENANT_DB_DATABASE_PRIVILEGES` | Each `b2b_tenant_*` DB (admin + clone) |
| `TENANT_DB_TENANT_USER_PRIVILEGES` | Per-company CRM MySQL user |

Defaults are in `config/master.php`.

## RDS allowed vs blocked

| Command | RDS |
|---------|-----|
| `GRANT SELECT, INSERT, … ON db.*` | Yes |
| `GRANT ALL PRIVILEGES ON db.*` | Often blocked |
| `GRANT ALL PRIVILEGES ON *.*` | No |
| `WITH GRANT OPTION` | No |
| `FLUSH PRIVILEGES` | Yes |
| `CREATE USER` | Yes (with CREATE USER on *.*) |
| `FLUSH TABLES` (mysqldump default) | No — app uses PDO clone |

## Error 1045

`Access denied for 'b2b_master'@'172.31.x.x'` — the app reached RDS but MySQL rejected the login.

1. **Password** — must match in MySQL and in app config. Check **Admin → Web settings → MySQL admin password** first (saved values override `.env`). Then `TENANT_DB_PASSWORD` in `.env`. If both are empty, the app uses `DB_PASSWORD`.
2. **User host** — create `'b2b_master'@'%'` (not only `@'localhost'`). EC2 private IPs like `172.31.x.x` match `@'%'`.
3. **Reset password on RDS** (as RDS master user), then mirror in settings / `.env`:

```sql
ALTER USER 'b2b_master'@'%' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';
FLUSH PRIVILEGES;
```

4. **Verify** — Admin → Web settings → **Test tenant MySQL connection**, or on the server:

```bash
php artisan config:clear
php artisan tenant:db-admin-check
php artisan horizon:terminate
```

5. **Security group** — RDS must allow inbound 3306 from the app server (if you get timeout, not 1045, fix SG first).

## Skip per-DB GRANT step

If `b2b_master` already has wildcard `b2b_tenant_%` grants from RDS master:

```env
TENANT_DB_GRANT_ADMIN_ON_CREATE=false
```
