# Local multi-tenant test (Edysor)

## 1. Run both apps

```bash
# Terminal A — master portal
cd master-portal && php artisan serve --port=8001

# Terminal B — main CRM
cd .. && php artisan serve --port=8000
```

Main CRM `.env` must have (already set):

```env
MASTER_API_URL=http://127.0.0.1:8001
MASTER_API_TOKEN=<same as master-portal CRM_MASTER_API_TOKEN>
```

## 2. Hostname options

Browsers do **not** resolve `edysor.127.0.0.1` automatically. Pick **one**:

### Option A — `edysor.localhost` (easiest, no hosts file)

Open: **http://edysor.localhost:8000/dashboard**

Works in Chrome/Edge/Firefox (maps `*.localhost` → 127.0.0.1).

### Option B — `edysor.127.0.0.1` (your URL)

Add to `/etc/hosts` (macOS):

```bash
sudo sh -c 'echo "127.0.0.1 edysor.127.0.0.1" >> /etc/hosts'
```

Then open: **http://edysor.127.0.0.1:8000/dashboard**

### Option C — `edysor.test`

```bash
sudo sh -c 'echo "127.0.0.1 edysor.test" >> /etc/hosts'
```

Open: **http://edysor.test:8000/dashboard**

## 3. Verify master API

```bash
TOKEN=$(grep CRM_MASTER_API_TOKEN master-portal/.env | cut -d= -f2)
curl -s -H "Authorization: Bearer $TOKEN" \
  "http://127.0.0.1:8001/api/v1/tenant/resolve?host=edysor.127.0.0.1"
```

Expect `"database":"b2b_tenant_edysor"`.

## 4. Default local URLs

| URL | Tenant DB |
|-----|-----------|
| http://127.0.0.1:8000 | `b2b_live_database` (Guarantee Admit) |
| http://edysor.localhost:8000 | `b2b_tenant_edysor` |

## 5. Clone tenant DB (first time only)

```bash
cd master-portal
php artisan tenant:clone-database edysor          # interactive prompt: schema only vs all data
php artisan tenant:clone-database edysor --data   # skip prompt; copy all data (slow)
```

Provisioning copies **schema only** from the template DB, then row data only for tables listed in `config/master.php` → `tenant_seed_tables` (not the full template database).

## 6. Login / sessions

If login cookies fail on `edysor.127.0.0.1`, use **edysor.localhost** instead (cookie domain works better).

After changing `.env`, run: `php artisan config:clear`

## 7. Production subdomain (`*.guaranteeadmit.com`)

Local logs show `*.localhost` and `action=ensure_subdomain` — that is **not** public DNS.

For live partner URLs (`https://{slug}.guaranteeadmit.com`) and Cloudflare A records, see **[PRODUCTION_SUBDOMAIN_DNS_TEST.md](./PRODUCTION_SUBDOMAIN_DNS_TEST.md)**.

Quick CLI (production master + real server IP + Cloudflare):

```bash
php artisan tenant:dns-update yourslug
```
