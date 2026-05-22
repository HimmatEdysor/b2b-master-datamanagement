# RDS tenant provisioning user (`TENANT_DB_*`)

The master app uses **one MySQL admin account** (not `root` on RDS) to:

1. Clone schema from the template database into `b2b_tenant_*` databases
2. `CREATE USER` for each tenant’s dedicated CRM database user
3. `GRANT` on each new tenant database

Configure in **`.env`** or **Admin → Settings → Tenant database**:

```env
TENANT_DB_HOST=your-instance.xxxxx.ap-south-1.rds.amazonaws.com
TENANT_DB_PORT=3306
TENANT_DB_USERNAME=b2b_master
TENANT_DB_PASSWORD=your-strong-password
TENANT_TEMPLATE_DATABASE=b2b_live_database
```

## Error: `1045 Access denied for user 'b2b_master'@'172.31.x.x'`

This means the app server (EC2) cannot log in. Fix **one** of:

| Cause | Fix |
|--------|-----|
| Wrong password in `.env` / Settings | Match the password set in MySQL exactly |
| User does not exist | Create user (SQL below) |
| User only allowed from `localhost` | Recreate as `'b2b_master'@'%'` |
| RDS security group | Allow port **3306** from the EC2 security group |

Test from the **same server** as Laravel:

```bash
mysql -h YOUR_RDS_HOST -u b2b_master -p -e "SELECT 1"
```

## Create provisioning user on RDS

Connect as the **RDS master username** (from AWS RDS console → Configuration → Master username), then run:

```sql
CREATE USER IF NOT EXISTS 'b2b_master'@'%' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';

GRANT CREATE, DROP, ALTER, INDEX, CREATE USER, PROCESS ON *.* TO 'b2b_master'@'%';
GRANT SELECT, SHOW VIEW ON `b2b_live_database`.* TO 'b2b_master'@'%';
GRANT ALL PRIVILEGES ON `b2b_tenant_%`.* TO 'b2b_master'@'%';

FLUSH PRIVILEGES;
```

Replace:

- `YOUR_STRONG_PASSWORD` — same as `TENANT_DB_PASSWORD`
- `b2b_live_database` — your `TENANT_TEMPLATE_DATABASE`
- `b2b_tenant_%` — matches `TENANT_DATABASE_PREFIX` (default `b2b_tenant_`)

## Verify from the app server

```bash
cd /var/www/html/b2b-master-datamanagement
php artisan config:clear
php artisan tenant:db-admin-check
```

All checks must pass before **Retry provisioning**.

Then restart queue workers:

```bash
php artisan horizon:terminate
```

## What the app checks before provisioning

- `TENANT_DB_USERNAME` / `TENANT_DB_PASSWORD` set
- TCP connection to RDS
- `CREATE`, `DROP`, `CREATE USER` on `*.*`
- Template database exists and `SHOW CREATE TABLE` works
- Test `CREATE DATABASE` + `DROP DATABASE`

## Per-tenant user (created automatically)

After schema clone, the app creates a **dedicated** MySQL user per company (e.g. `b2b_tenant_chhotadon`) with access only to that tenant’s database. You do not create these manually.
