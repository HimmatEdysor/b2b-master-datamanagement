# Main B2B CRM — minimal integration (when you choose)

The existing CRM stays unchanged until you add these files. Only **one HTTP call** per request (with cache) is needed.

## 1. Environment (main CRM `.env`)

```env
MASTER_API_URL=https://master.guaranteeadmit.com
MASTER_API_TOKEN=same-as-CRM_MASTER_API_TOKEN-in-master-portal
MASTER_API_CACHE_SECONDS=300
```

## 2. Config `config/master_api.php`

```php
<?php

return [
    'url' => rtrim(env('MASTER_API_URL', ''), '/'),
    'token' => env('MASTER_API_TOKEN'),
    'cache_seconds' => (int) env('MASTER_API_CACHE_SECONDS', 300),
];
```

## 3. Service `app/Services/MasterTenantConfigService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MasterTenantConfigService
{
    public function resolve(string $host): ?array
    {
        $base = config('master_api.url');
        $token = config('master_api.token');

        if ($base === '' || $token === '') {
            return null;
        }

        $cacheKey = 'master_tenant:'.md5($host);

        return Cache::remember($cacheKey, config('master_api.cache_seconds'), function () use ($base, $token, $host) {
            $response = Http::withToken($token)
                ->timeout(5)
                ->get($base.'/api/v1/tenant/resolve', ['host' => $host]);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();

            return ($json['success'] ?? false) ? ($json['data'] ?? null) : null;
        });
    }

    public function applyDatabaseConfig(array $data): void
    {
        $db = $data['database'] ?? [];

        config([
            'database.connections.tenant' => [
                'driver' => $db['driver'] ?? 'mysql',
                'host' => $db['host'] ?? '127.0.0.1',
                'port' => $db['port'] ?? 3306,
                'database' => $db['database'] ?? '',
                'username' => $db['username'] ?? '',
                'password' => $db['password'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ],
            'database.default' => 'tenant',
            'app.name' => $data['branding']['app_name'] ?? config('app.name'),
        ]);

        if (! empty($data['branding']['logo_url'])) {
            config(['envvariables.COMPANY_LOGO' => $data['branding']['logo_url']]);
        }

        \Illuminate\Support\Facades\DB::purge('tenant');
        \Illuminate\Support\Facades\DB::reconnect('tenant');
    }
}
```

## 4. Middleware `app/Http/Middleware/ResolveTenantFromMasterApi.php`

```php
<?php

namespace App\Http\Middleware;

use App\Services\MasterTenantConfigService;
use Closure;
use Illuminate\Http\Request;

class ResolveTenantFromMasterApi
{
    public function __construct(
        protected MasterTenantConfigService $masterTenant
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (! config('master_api.url')) {
            return $next($request);
        }

        $host = $request->getHost();
        $data = $this->masterTenant->resolve($host);

        if (! $data) {
            abort(404, 'Company not configured for this domain.');
        }

        $this->masterTenant->applyTenantConfig($data);
        $request->attributes->set('tenant_config', $data);

        return $next($request);
    }
}
```

## 5. Register middleware

In `app/Http/Kernel.php`, add to **global** middleware stack (top, after TrustProxies):

```php
\App\Http\Middleware\ResolveTenantFromMasterApi::class,
```

Add `tenant` connection stub in `config/database.php` (copy of `mysql` with empty database).

## 6. Fallback without master API

If `MASTER_API_URL` is empty, CRM uses existing `.env` `DB_*` — production behaviour unchanged during rollout.

## Flow

```text
Browser → edysor.guaranteeadmit.com
    → CRM middleware
    → GET master/api/v1/tenant/resolve?host=...
    → receives database_id + database credentials + branding
    → switches default DB connection
    → rest of CRM runs unchanged
```

## 7. Migrations on all company databases

New migration files live in the **B2B CRM** `database/migrations/` folder. Each company has its own MySQL database in master portal.

After you add or pull migrations, run them on **every** tenant database using either method:

| Where | Command / UI |
|-------|----------------|
| **Master portal** (recommended UI) | Admin → **Companies** → **Refresh list** → **Run migrations** (one DB at a time, live status) |
| **B2B CRM** (CLI from dev / deploy) | `php artisan tenants:migrate-all --force` |
| **Master portal** (CLI) | `php artisan tenants:migrate-databases --force` |

Requirements:

- B2B CRM `.env`: `MASTER_API_URL` and `MASTER_API_TOKEN` (same value as master `CRM_MASTER_API_TOKEN`)
- Master `.env`: `TENANT_CRM_PATH` = B2B CRM root (folder with `artisan`)
- Master API: `GET /api/v1/tenants/migration-databases` (list + domains for B2B `tenants:migrate-all`)
- Master API: `POST /api/v1/tenants/{slug}/migrate` (single company, same subprocess as UI)

**Progressive UI (master):** Each run **re-fetches** the company list from master DB (including new domains/companies). Click **Refresh list** after approving a new tenant, then **Run migrations** again.

Optional: migrate one company only:

```bash
php artisan tenants:migrate-all edysor --force
```

`php artisan migrate` alone only runs against the **current** `.env` `DB_*` connection (single-DB / legacy). It does **not** fan out to all tenants unless you use `tenants:migrate-all`.

## 8. Domain management (CRM `/user` settings)

The master portal stores all CRM hostnames (`tenant_domains`). Use these APIs from the tenant CRM **user settings** page (e.g. `https://guaranteeadmit.com/user`) so admins can see the default platform URL, their company subdomain, and add custom domains.

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/api/v1/tenants/{slug}/domains` | List domains + primary/default URLs |
| `POST` | `/api/v1/tenants/{slug}/domains` | Add `custom` host or `subdomain_alias` |
| `POST` | `/api/v1/tenants/{slug}/domains/{id}` | Set primary domain |
| `DELETE` | `/api/v1/tenants/{slug}/domains/{id}` | Remove domain (not the canonical `{slug}.{base}` subdomain) |

Auth: same `Authorization: Bearer {MASTER_API_TOKEN}` as resolve.

**POST body examples:**

```json
{ "type": "custom", "host": "crm.client.com" }
```

```json
{ "type": "subdomain_alias", "alias": "sales" }
```

Creates `sales.guaranteeadmit.com` pointing at the same tenant database.

**List response (abbreviated):**

```json
{
  "success": true,
  "data": {
    "slug": "edysor",
    "base_domain": "guaranteeadmit.com",
    "default_platform_url": "https://guaranteeadmit.com",
    "primary_url": "https://edysor.guaranteeadmit.com",
    "domains": [
      { "host": "edysor.guaranteeadmit.com", "type": "subdomain", "is_primary": true, "url": "https://edysor.guaranteeadmit.com", "label": "Company CRM subdomain" }
    ]
  }
}
```

The default Guarantee Admit tenant (`slug: guaranteeadmit`) uses apex **`https://guaranteeadmit.com`** as the platform CRM. Partner companies use **`https://{slug}.guaranteeadmit.com`**.

Resolve API also returns `domains_detail`, `primary_url`, `default_platform_url`, and `is_platform_default` for display on `/user`.

## Security

- Use HTTPS for master API
- Long random `CRM_MASTER_API_TOKEN`
- Restrict master admin panel by IP/VPN in production
- Do not expose master API without token
