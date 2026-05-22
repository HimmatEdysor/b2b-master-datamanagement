# Production subdomain test (`*.guaranteeadmit.com`)

Your **domain** activity log lines like `ensure_subdomain` + `*.localhost` are from **local** master (`APP_ENV=local`, `TENANT_BASE_DOMAIN=localhost`). They do **not** create public DNS on [guaranteeadmit.com](https://guaranteeadmit.com/user).

To test a **live** partner subdomain (e.g. `sasada.guaranteeadmit.com`) pointing at the same CRM stack as the main site:

## URL model

| Host | Meaning |
|------|---------|
| [https://guaranteeadmit.com/user](https://guaranteeadmit.com/user) | Default platform tenant (`slug`: `guaranteeadmit`) — apex domain |
| `https://{slug}.guaranteeadmit.com/login` | Partner company CRM (e.g. `sasada.guaranteeadmit.com`) |

Master only creates the **DNS A record** and registry; the CRM app must already serve `*.guaranteeadmit.com` (nginx/Apache vhost + tenant resolve API).

## 1. Master `.env` (production server or staging)

```env
APP_ENV=production
APP_URL=https://master.guaranteeadmit.com

TENANT_BASE_DOMAIN=guaranteeadmit.com
TENANT_URL_SCHEME=https
# No TENANT_CRM_PORT on production (standard 443)

CUSTOM_DOMAIN_SERVER_IP=<public IP of CRM web server>
DNS_PROVIDER=cloudflare
CLOUDFLARE_API_TOKEN=<token with Zone.DNS Edit>
DNS_CLOUDFLARE_ZONE_ID=<zone id for guaranteeadmit.com>
DNS_CLOUDFLARE_BASE_DOMAIN=guaranteeadmit.com
DNS_CLOUDFLARE_PROXIED=false
```

Also set **Web settings** in master admin: CRM server IP, Cloudflare zone ID, base domain (overrides config when saved).

`CUSTOM_DOMAIN_SERVER_IP` must be the **real server IP** where CRM runs — not `127.0.0.1`.

## 2. Create / approve company on production master

1. Admin → Companies → create or open tenant (e.g. slug `sasada`).
2. Approve / provision database (queue or sync).
3. Confirm **Domains** shows host: `sasada.guaranteeadmit.com` (not `sasada.localhost`).

If the host is still `*.localhost`, you are on a **local** master DB — use production master or fix `TENANT_BASE_DOMAIN` before approval.

## 3. Add record in Cloudflare (manual)

Cloudflare dashboard → **DNS** → **DNS management for guaranteeadmit.com** → **Add record**:

| Field | Value for company slug `sasada` |
|-------|----------------------------------|
| **Type** | `A` |
| **Name** | `sasada` (not the full domain — Cloudflare adds `.guaranteeadmit.com`) |
| **IPv4 address** | Your CRM server public IP (same as `CUSTOM_DOMAIN_SERVER_IP`) |
| **Proxy status** | **DNS only** (grey cloud) recommended if you verify by IP |
| **TTL** | Auto |

Result: **`sasada.guaranteeadmit.com`** → server IP.  
Then in master admin click **Verify DNS** or **DNS Update** (API upserts the same record).

## 4. DNS Update (UI or CLI)

**Admin UI:** Company → **Manage company** → **Domains, DNS & SSL** → **DNS Update**

**CLI (on master server):**

```bash
php artisan tenant:dns-update sasada
```

Expect:

- Cloudflare A record: `sasada` → your `CUSTOM_DOMAIN_SERVER_IP`
- Log line in `storage/logs/master-activity/dns/YYYY-MM-DD.log`:
  - `action=dns_update` `status=ok` `message=DNS updated: sasada.guaranteeadmit.com → …`

**Activity logs:** Admin → **Activity logs** → channel **DNS & SSL** (not only **Domains** — that channel has `ensure_subdomain`).

## 5. Verify DNS

```bash
dig +short sasada.guaranteeadmit.com A
# Should return your CRM server IP
```

```bash
TOKEN=<CRM_MASTER_API_TOKEN>
curl -s -H "Authorization: Bearer $TOKEN" \
  "https://master.guaranteeadmit.com/api/v1/tenant/resolve?host=sasada.guaranteeadmit.com"
```

Expect JSON with `database`, `slug`, `status`.

## 6. SSL Apply

After DNS propagates:

1. On CRM server: Certbot / nginx for `sasada.guaranteeadmit.com` (wildcard cert `*.guaranteeadmit.com` is ideal).
2. Master admin → **SSL Apply** on that domain row.
3. Open **Open HTTPS** / `https://sasada.guaranteeadmit.com/login`.

## 7. Test from local master against production DNS (optional)

You can point **local** master at Cloudflare while CRM stays on localhost only for **resolve** testing — hosts in DB must be `*.guaranteeadmit.com`:

```env
TENANT_BASE_DOMAIN=guaranteeadmit.com
CUSTOM_DOMAIN_SERVER_IP=<production CRM IP>
CLOUDFLARE_API_TOKEN=...
DNS_CLOUDFLARE_ZONE_ID=...
```

Then either re-approve (new subdomain host) or update `tenant_domains.host` to `sasada.guaranteeadmit.com` and run:

```bash
php artisan tenant:dns-update sasada
```

CRM login will still be local unless CRM is deployed on that IP; DNS test only confirms Cloudflare + master logging.

## Checklist

- [ ] `TENANT_BASE_DOMAIN=guaranteeadmit.com` on master used for the test
- [ ] Primary domain row = `{slug}.guaranteeadmit.com`
- [ ] CRM server IP set (Web settings + `.env`)
- [ ] Cloudflare token + zone ID valid
- [ ] **DNS Update** → success alert + `dns_update` in DNS log
- [ ] `dig` / resolve API returns tenant
- [ ] CRM vhost + **SSL Apply** → `https://{slug}.guaranteeadmit.com/login` loads like main [guaranteeadmit.com/user](https://guaranteeadmit.com/user)
