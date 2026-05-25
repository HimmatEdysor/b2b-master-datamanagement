<?php

return [

    'crm_api_token' => env('CRM_MASTER_API_TOKEN'),

    'is_local' => env('APP_ENV', 'production') === 'local',

    'tenant_base_domain' => env('TENANT_BASE_DOMAIN') ?: (
        env('APP_ENV') === 'local' ? 'localhost' : 'guaranteeadmit.com'
    ),

    'tenant_base_domain_production' => env('TENANT_BASE_DOMAIN_PRODUCTION', 'guaranteeadmit.com'),

    /*
    | Slug for the default platform tenant (apex guaranteeadmit.com CRM).
    */
    'platform_default_slug' => env('PLATFORM_DEFAULT_TENANT_SLUG', 'guaranteeadmit'),

    'tenant_url_scheme' => env('TENANT_URL_SCHEME') ?: (
        env('APP_ENV') === 'local' ? 'http' : 'https'
    ),

    'tenant_crm_port' => env('TENANT_CRM_PORT', env('APP_ENV') === 'local' ? '8000' : null),

    /*
    | Force :port on tenant CRM URLs even on production base domain (normally off).
    */
    'tenant_crm_port_force' => env('TENANT_CRM_PORT_FORCE', false),

    'master_domain' => env('MASTER_DOMAIN') ?: (
        env('APP_ENV') === 'local' ? '127.0.0.1:8001' : 'master.guaranteeadmit.com'
    ),

    'master_url' => env('MASTER_APP_URL', env('APP_URL', 'http://localhost:8001')),

    /*
    | S3: on approve, master creates {slug}/.keep in AWS_BUCKET (same as main CRM).
    | CRM subdomain uses storage.s3_folder from resolve API to scope /s3-document.
    */
    'tenant_s3_enabled' => (bool) env('AWS_BUCKET'),

    /*
    | Platform MySQL admin (provision only: CREATE DATABASE, schema clone, CREATE USER).
    | AWS RDS: set TENANT_DB_USERNAME to the RDS master user (usually not "root"). Override in Admin → Settings.
    | Each company's CRM connection is stored on the tenants row after approval — not read from here.
    */
    'tenant_db_host' => env('TENANT_DB_HOST', env('DB_HOST', '127.0.0.1')),
    'tenant_db_port' => env('TENANT_DB_PORT', env('DB_PORT', '3306')),
    'tenant_db_username' => env('TENANT_DB_USERNAME', env('DB_USERNAME', 'root')),
    'tenant_db_password' => env('TENANT_DB_PASSWORD', env('DB_PASSWORD', '')),

    /*
    | How to clone template → tenant schema (no row data; seed tables separately).
    | pdo       — SHOW CREATE TABLE via PDO (default; works on AWS RDS without RELOAD/FLUSH)
    | mysqldump — CLI mysqldump with --skip-lock-tables (legacy / local debugging)
    */
    'tenant_db_clone_method' => env('TENANT_DB_CLONE_METHOD', 'pdo'),

    /*
    | After CREATE DATABASE, GRANT specific privileges on that DB to TENANT_DB_USERNAME (RDS-safe, not ALL).
    */
    'tenant_db_grant_admin_on_create' => filter_var(
        env('TENANT_DB_GRANT_ADMIN_ON_CREATE', true),
        FILTER_VALIDATE_BOOL
    ),

    /*
    | Hosts for GRANT … TO 'TENANT_DB_USERNAME'@host after each tenant database is created.
    */
    'tenant_db_admin_grant_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TENANT_DB_ADMIN_GRANT_HOSTS', '%'))
    ))),

    /*
    | AWS RDS: use explicit privileges (GRANT ALL PRIVILEGES is blocked). Comma-separated override in .env optional.
    */
    'tenant_db_global_privileges' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TENANT_DB_GLOBAL_PRIVILEGES', 'CREATE,DROP,ALTER,INDEX,CREATE USER,PROCESS'))
    ))),

    'tenant_db_database_privileges' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TENANT_DB_DATABASE_PRIVILEGES', 'SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,ALTER,INDEX,CREATE TEMPORARY TABLES,LOCK TABLES,EXECUTE,CREATE VIEW,SHOW VIEW,TRIGGER,REFERENCES'))
    ))),

    'tenant_db_template_privileges' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TENANT_DB_TEMPLATE_PRIVILEGES', 'SELECT,SHOW VIEW'))
    ))),

    'tenant_db_tenant_user_privileges' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TENANT_DB_TENANT_USER_PRIVILEGES', 'SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,ALTER,INDEX,CREATE TEMPORARY TABLES,LOCK TABLES,EXECUTE,CREATE VIEW,SHOW VIEW,TRIGGER,REFERENCES'))
    ))),

    /*
    | Seconds for mysqldump/mysql when cloning template → tenant DB (Horizon timeout uses this too).
    | PDO clone ignores mysqldump; timeout still applies to queued provision jobs.
    */
    'tenant_db_clone_timeout' => (int) env('TENANT_DB_CLONE_TIMEOUT', 3000),

    /*
    | Local dev: allow "Provision now" (sync, ~15–40s for schema-only) without Horizon.
    | Production should use the queue (TENANT_PROVISION_SYNC_LOCAL=false).
    */
    'tenant_provision_sync_local' => filter_var(
        env('TENANT_PROVISION_SYNC_LOCAL', env('APP_ENV') === 'local'),
        FILTER_VALIDATE_BOOL
    ),

    /*
    | MySQL hosts for per-tenant DB users (CREATE USER … @host). Use % for remote/TCP;
    | include localhost when CRM connects via socket on the same server.
    */
    'tenant_db_user_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TENANT_DB_USER_HOSTS', env('APP_ENV') === 'local' ? '%,localhost' : '%'))
    ))),

    'template_database' => env('TENANT_TEMPLATE_DATABASE', 'b2b_live_database'),

    'tenant_database_prefix' => env('TENANT_DATABASE_PREFIX', 'b2b_tenant_'),

    /*
    | Reserved company slug for tenant:db-admin-check only (database = prefix + slug).
    | Do not create a real tenant with this slug.
    */
    'tenant_provision_check_slug' => env('TENANT_PROVISION_CHECK_SLUG', 'provisioncheck'),

    /*
    | Absolute path to the tenant-facing CRM Laravel app (directory containing artisan).
    | Bulk “migrate databases” runs `php artisan migrate --force` there with DB_* per tenant.
    | If TENANT_CRM_PATH is unset and this app lives in a subfolder (e.g. …/B2B_CRM/master-portal),
    | the parent directory is used when it contains artisan (sibling CRM monorepo layout).
    */
    'tenant_crm_path' => env('TENANT_CRM_PATH') ?: (
        is_file(dirname(base_path()).DIRECTORY_SEPARATOR.'artisan')
            ? dirname(base_path())
            : null
    ),

    /*
    | Optional PHP binary for running tenant CRM artisan (defaults to PHP_BINARY).
    */
    'tenant_crm_php_binary' => env('TENANT_CRM_PHP_BINARY'),

    /*
    | Max seconds per tenant for migrate subprocess.
    */
    'tenant_crm_migrate_timeout' => (float) env('TENANT_CRM_MIGRATE_TIMEOUT', 600),

    /*
    | “Migrate all” runs sequentially; max companies per request (safety cap).
    */
    'tenant_crm_migrate_bulk_max_tenants' => (int) env('TENANT_CRM_MIGRATE_BULK_MAX_TENANTS', 500),

    /*
    | PHP time limit for the bulk migrate request (0 = unlimited when allowed).
    */
    'tenant_crm_bulk_migrate_time_limit' => (int) env('TENANT_CRM_BULK_MIGRATE_TIME_LIMIT', 0),

    /*
    | Only these tables get row data copied from the template DB after schema clone.
    | All other tables stay empty (no full mysqldump data). Add/remove tables here as needed.
    */
    'tenant_seed_tables' => [
        'permissions',
        'roles',
        'role_permissions',
        'countries',
        'states',
        'cities',
        'course_types',
        'universities',
        'courses',
        'service_names',
    ],

    /*
    | Template user row(s) copied by id after seed (e.g. bootstrap admin before default CRM user is created).
    */
    'tenant_seed_user_ids' => [1],

    /*
    | universities columns cleared when seeding from template (tenant-specific URM contacts).
    */
    'tenant_universities_blank_columns' => ['urm_name', 'urm_contact_no', 'urm_email'],

    /*
    | web_settings columns copied from template (user_id null row only). No API keys, mail, etc.
    */
    'tenant_web_setting_theme_columns' => [
        'admin_theme_json',
        'chat_custom_color',
        'company_display_name',
        'company_logo_url',
        'favicon_url',
    ],

    /*
    | Default CRM admin user created in each new tenant database on approval.
    */
    'tenant_default_user' => [
        'email' => env('TENANT_DEFAULT_USER_EMAIL', 'himmat@edysor.in'),
        'password' => env('TENANT_DEFAULT_USER_PASSWORD', '12341234'),
        'name' => env('TENANT_DEFAULT_USER_NAME', 'Admin'),
    ],

    'tenant_statuses' => [
        'pending',
        'provisioning',
        'active',
        'failed',
        'suspended',
        'rejected',
    ],

    'tenant_status_labels' => [
        'pending' => 'Pending (await approval)',
        'provisioning' => 'Provisioning',
        'active' => 'Active',
        'failed' => 'Failed',
        'suspended' => 'Suspended',
        'rejected' => 'Rejected',
    ],

    'subscription_statuses' => [
        'pending',
        'trial',
        'active',
        'cancelled',
        'expired',
        'suspended',
    ],

    'subscription_status_labels' => [
        'pending' => 'Pending',
        'trial' => 'Trial',
        'active' => 'Active',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
        'suspended' => 'Suspended',
    ],

    /*
    | Subscription statuses that allow CRM login on tenant subdomains.
    */
    'crm_allowed_subscription_statuses' => ['active', 'trial'],

    /*
    | Plan slugs that never get subscription_expires_at (use interval "none" on the plan too).
    */
    'subscription_free_plan_slugs' => ['free'],

    /*
    | Custom domain DNS/SSL hints in admin + automatic A records for subdomains.
    */
    'custom_domain_server_ip' => env('CUSTOM_DOMAIN_SERVER_IP'),
    'custom_domain_cname_target' => env('CUSTOM_DOMAIN_CNAME_TARGET'),
    'custom_domain_ssl_email' => env('CUSTOM_DOMAIN_SSL_EMAIL'),
    'custom_domain_ssl_webroot' => env('CUSTOM_DOMAIN_SSL_WEBROOT', '/var/www/html'),

    /*
    | DNS auto-provision: cloudflare (default), route53, or manual.
    | Cloudflare: CLOUDFLARE_API_TOKEN in .env + zone ID in Web settings or .env.
    */
    'dns_provider' => env('DNS_PROVIDER', 'cloudflare'),
    'dns_auto_link_subdomains' => env('DNS_AUTO_LINK_SUBDOMAINS', true),
    'cloudflare_api_token' => env('CLOUDFLARE_API_TOKEN'),
    'dns_cloudflare_zone_id' => env('DNS_CLOUDFLARE_ZONE_ID'),
    'dns_cloudflare_base_domain' => env('DNS_CLOUDFLARE_BASE_DOMAIN'),
    'dns_cloudflare_proxied' => filter_var(env('DNS_CLOUDFLARE_PROXIED', false), FILTER_VALIDATE_BOOL),
    'dns_route53_hosted_zone_id' => env('DNS_ROUTE53_HOSTED_ZONE_ID'),
    'dns_route53_base_domain' => env('DNS_ROUTE53_BASE_DOMAIN'),
    'dns_route53_region' => env('DNS_ROUTE53_REGION'),

    /*
    | Queue for tenant DB provisioning (run: php artisan queue:work).
    */
    'tenant_provision_queue' => env('TENANT_PROVISION_QUEUE', 'provisioning'),

];
