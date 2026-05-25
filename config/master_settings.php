<?php

/**
 * Master portal settings editable in Admin → Web settings.
 * DB values override .env when saved; empty DB fields fall back to env/config.
 */
return [

    'sections' => [
        'urls' => [
            'label' => 'Tenant & master URLs',
            'description' => 'How company CRM links are built in admin and resolve API.',
        ],
        'dns' => [
            'label' => 'DNS & custom domains',
            'description' => 'Server IP, Cloudflare zone (recommended), or Route53. Set CLOUDFLARE_API_TOKEN in .env.',
        ],
        'database' => [
            'label' => 'Database provisioning',
            'description' => 'MySQL used when approving companies (CREATE DATABASE / users). Not per-company CRM credentials. On AWS RDS use a dedicated user (e.g. b2b_master) with @\'%\' — see docs/RDS_TENANT_DB_ADMIN.md.',
        ],
        'crm' => [
            'label' => 'Tenant CRM app',
            'description' => 'Path to tenant CRM Laravel app and default admin user on provision.',
        ],
    ],

    'fields' => [
        'tenant_base_domain' => [
            'section' => 'urls',
            'label' => 'Tenant base domain (local/dev)',
            'config' => 'tenant_base_domain',
            'type' => 'string',
            'hint' => 'e.g. localhost — used when APP_ENV=local',
        ],
        'tenant_base_domain_production' => [
            'section' => 'urls',
            'label' => 'Tenant base domain (production)',
            'config' => 'tenant_base_domain_production',
            'type' => 'string',
            'hint' => 'e.g. guaranteeadmit.com',
        ],
        'tenant_url_scheme' => [
            'section' => 'urls',
            'label' => 'CRM URL scheme',
            'config' => 'tenant_url_scheme',
            'type' => 'string',
            'hint' => 'http or https',
        ],
        'tenant_crm_port' => [
            'section' => 'urls',
            'label' => 'CRM port (optional)',
            'config' => 'tenant_crm_port',
            'type' => 'string',
            'hint' => 'e.g. 8000 for local; leave empty on production',
        ],
        'tenant_crm_port_force' => [
            'section' => 'urls',
            'label' => 'Always append port to CRM URLs',
            'config' => 'tenant_crm_port_force',
            'type' => 'boolean',
        ],
        'master_url' => [
            'section' => 'urls',
            'label' => 'Master portal URL',
            'config' => 'master_url',
            'type' => 'string',
            'hint' => 'Public URL of this admin app',
        ],
        'master_domain' => [
            'section' => 'urls',
            'label' => 'Master domain (display)',
            'config' => 'master_domain',
            'type' => 'string',
        ],
        'platform_default_slug' => [
            'section' => 'urls',
            'label' => 'Platform default tenant slug',
            'config' => 'platform_default_slug',
            'type' => 'string',
            'hint' => 'Apex CRM tenant slug (e.g. guaranteeadmit)',
        ],

        'custom_domain_server_ip' => [
            'section' => 'dns',
            'label' => 'CRM server IP (A record)',
            'config' => 'custom_domain_server_ip',
            'type' => 'string',
            'hint' => 'Public IPv4 for custom domains and auto subdomain DNS',
        ],
        'custom_domain_cname_target' => [
            'section' => 'dns',
            'label' => 'Custom domain CNAME target (optional)',
            'config' => 'custom_domain_cname_target',
            'type' => 'string',
            'hint' => 'Defaults to {slug}.base domain if empty',
        ],
        'custom_domain_ssl_email' => [
            'section' => 'dns',
            'label' => 'SSL / Certbot email',
            'config' => 'custom_domain_ssl_email',
            'type' => 'string',
        ],
        'custom_domain_ssl_webroot' => [
            'section' => 'dns',
            'label' => 'Certbot webroot path',
            'config' => 'custom_domain_ssl_webroot',
            'type' => 'string',
        ],
        'dns_provider' => [
            'section' => 'dns',
            'label' => 'DNS provider',
            'config' => 'dns_provider',
            'type' => 'select',
            'options' => [
                'cloudflare' => 'Cloudflare',
                'route53' => 'Amazon Route53',
                'manual' => 'Manual only (no API)',
            ],
            'hint' => 'Cloudflare is recommended. API token stays in .env (CLOUDFLARE_API_TOKEN).',
        ],
        'dns_auto_link_subdomains' => [
            'section' => 'dns',
            'label' => 'Auto-link subdomains to server IP',
            'config' => 'dns_auto_link_subdomains',
            'type' => 'boolean',
        ],
        'dns_cloudflare_zone_id' => [
            'section' => 'dns',
            'label' => 'Cloudflare zone ID',
            'config' => 'dns_cloudflare_zone_id',
            'type' => 'string',
            'hint' => 'From Cloudflare dashboard → your domain → Overview → Zone ID',
        ],
        'dns_cloudflare_base_domain' => [
            'section' => 'dns',
            'label' => 'Cloudflare base domain',
            'config' => 'dns_cloudflare_base_domain',
            'type' => 'string',
            'hint' => 'e.g. guaranteeadmit.com — hosts must be under this zone',
        ],
        'dns_cloudflare_proxied' => [
            'section' => 'dns',
            'label' => 'Cloudflare proxy (orange cloud)',
            'config' => 'dns_cloudflare_proxied',
            'type' => 'boolean',
            'hint' => 'Off recommended for CRM (direct A → your server IP)',
        ],
        'dns_route53_hosted_zone_id' => [
            'section' => 'dns',
            'label' => 'Route53 hosted zone ID (optional)',
            'config' => 'dns_route53_hosted_zone_id',
            'type' => 'string',
            'hint' => 'Only if DNS provider = Route53. Needs AWS keys in .env',
        ],
        'dns_route53_base_domain' => [
            'section' => 'dns',
            'label' => 'Route53 base domain',
            'config' => 'dns_route53_base_domain',
            'type' => 'string',
        ],
        'dns_route53_region' => [
            'section' => 'dns',
            'label' => 'Route53 AWS region',
            'config' => 'dns_route53_region',
            'type' => 'string',
        ],

        'template_database' => [
            'section' => 'database',
            'label' => 'Template database name',
            'config' => 'template_database',
            'type' => 'string',
        ],
        'tenant_database_prefix' => [
            'section' => 'database',
            'label' => 'Tenant database prefix',
            'config' => 'tenant_database_prefix',
            'type' => 'string',
        ],
        'tenant_db_host' => [
            'section' => 'database',
            'label' => 'MySQL host',
            'config' => 'tenant_db_host',
            'type' => 'string',
        ],
        'tenant_db_port' => [
            'section' => 'database',
            'label' => 'MySQL port',
            'config' => 'tenant_db_port',
            'type' => 'string',
        ],
        'tenant_db_username' => [
            'section' => 'database',
            'label' => 'MySQL admin username',
            'config' => 'tenant_db_username',
            'type' => 'string',
        ],
        'tenant_db_password' => [
            'section' => 'database',
            'label' => 'MySQL admin password',
            'config' => 'tenant_db_password',
            'type' => 'password',
            'hint' => 'Must match MySQL for the admin user above. Overrides .env when saved. Leave blank when saving other fields to keep the current password.',
        ],
        'tenant_db_shared_credentials' => [
            'section' => 'database',
            'label' => 'Use shared MySQL user for all tenants (RDS)',
            'config' => 'tenant_db_shared_credentials',
            'type' => 'boolean',
            'hint' => 'Every company uses the admin user above for its own database_name (requires b2b_tenant_% grant on RDS). No per-tenant CREATE USER.',
        ],
        'tenant_db_grant_admin_on_create' => [
            'section' => 'database',
            'label' => 'GRANT admin on each new tenant DB',
            'config' => 'tenant_db_grant_admin_on_create',
            'type' => 'boolean',
            'hint' => 'Ignored when shared MySQL user is enabled. Leave off on RDS (use b2b_tenant_% wildcard).',
        ],
        'tenant_db_user_hosts' => [
            'section' => 'database',
            'label' => 'Tenant DB user hosts',
            'config' => 'tenant_db_user_hosts',
            'type' => 'csv',
            'hint' => 'Comma-separated, e.g. %,localhost',
        ],

        'tenant_crm_path' => [
            'section' => 'crm',
            'label' => 'Tenant CRM app path',
            'config' => 'tenant_crm_path',
            'type' => 'string',
            'hint' => 'Folder containing artisan (for Run migrations)',
        ],
        'tenant_default_user_email' => [
            'section' => 'crm',
            'label' => 'Default CRM admin email',
            'config' => 'tenant_default_user.email',
            'type' => 'string',
            'hint' => 'Password is generated per company on provision',
        ],
        'tenant_default_user_name' => [
            'section' => 'crm',
            'label' => 'Default CRM admin name',
            'config' => 'tenant_default_user.name',
            'type' => 'string',
        ],
    ],

    /*
    | Still read only from .env (security).
    */
    'env_only_keys' => [
        'CRM_MASTER_API_TOKEN' => 'CRM API token (shared with tenant CRM)',
        'CLOUDFLARE_API_TOKEN' => 'Cloudflare API token (DNS edit permission on zone)',
        'AWS_ACCESS_KEY_ID' => 'AWS access key',
        'AWS_SECRET_ACCESS_KEY' => 'AWS secret key',
        'AWS_BUCKET' => 'S3 bucket',
        'APP_KEY' => 'Application encryption key',
    ],

];
