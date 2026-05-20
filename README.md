# B2B CRM Master Portal

**Separate Laravel project** — does not modify the main `B2B_CRM` application.

Manages companies, domains, database credentials, subscriptions, and exposes **one API** for the main CRM to resolve tenant configuration by subdomain/host.

## Setup

```bash
cd master-portal
cp .env.example .env
composer install
php artisan key:generate
# Create MySQL database: b2b_master
php artisan migrate
php artisan db:seed
php artisan serve --port=8001
```

- Admin UI: http://localhost:8001/admin  
- Login (after seed): `admin@master.local` / `password`

## Single API for main CRM

```http
GET /api/v1/tenant/resolve?host=edysor.guaranteeadmit.com
Authorization: Bearer {CRM_MASTER_API_TOKEN}
```

Optional header instead of query: `X-Tenant-Host: edysor.guaranteeadmit.com`

### Success response

```json
{
  "success": true,
  "host": "edysor.guaranteeadmit.com",
  "data": {
    "tenant_id": 2,
    "database_id": 2,
    "slug": "edysor",
    "name": "Edysor",
    "status": "active",
    "database": {
      "driver": "mysql",
      "host": "127.0.0.1",
      "port": 3306,
      "database": "b2b_tenant_edysor",
      "username": "root",
      "password": "secret"
    },
    "branding": {
      "app_name": "Edysor CRM",
      "logo_url": "https://...",
      "favicon_url": null,
      "primary_color": "#1e3a5f",
      "support_email": "support@edysor.com"
    },
    "subscription": {
      "plan_id": 1,
      "plan_name": "Starter",
      "plan_slug": "starter",
      "status": "active",
      "expires_at": null
    },
    "domains": ["edysor.guaranteeadmit.com"]
  }
}
```

## Main CRM integration (optional, minimal)

See [docs/CRM_INTEGRATION.md](docs/CRM_INTEGRATION.md) — copy a small middleware + config into the main project when you are ready. **No changes are required in the main repo until you add that file.**

## Production

- Deploy on `master.guaranteeadmit.com` (or separate server)
- Use HTTPS
- Set strong `CRM_MASTER_API_TOKEN`
- Register `guaranteeadmit.com` + each `{slug}.guaranteeadmit.com` in Companies

## Project layout

| Path | Purpose |
|------|---------|
| `app/Http/Controllers/Api/TenantResolveController.php` | Resolve API |
| `app/Services/TenantResolverService.php` | Host → tenant lookup |
| `app/Models/Tenant.php` | Company + DB credentials |
| `config/master.php` | Master settings |
| `app/Services/TenantSeedDataService.php` | Copies reference data into new tenant DBs on approve |
| `app/Services/TenantS3FolderService.php` | Creates `{slug}/` in shared S3 bucket on approve |
