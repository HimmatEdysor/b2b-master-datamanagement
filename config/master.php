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
    | Default MySQL credentials applied to new tenants when not overridden.
    */
    'tenant_db_host' => env('TENANT_DB_HOST', env('DB_HOST', '127.0.0.1')),
    'tenant_db_port' => env('TENANT_DB_PORT', env('DB_PORT', '3306')),
    'tenant_db_username' => env('TENANT_DB_USERNAME', env('DB_USERNAME', 'root')),
    'tenant_db_password' => env('TENANT_DB_PASSWORD', env('DB_PASSWORD', '')),

    'template_database' => env('TENANT_TEMPLATE_DATABASE', 'b2b_live_database'),

    'tenant_database_prefix' => env('TENANT_DATABASE_PREFIX', 'b2b_tenant_'),

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

];
