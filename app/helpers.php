<?php

use App\Support\MasterAuth;

if (! function_exists('master_can')) {
    function master_can(string $permission): bool
    {
        return MasterAuth::can($permission);
    }
}

if (! function_exists('master_can_view_activity_logs')) {
    function master_can_view_activity_logs(): bool
    {
        return master_can('logs.view') || master_can('tenants.view');
    }
}

if (! function_exists('master_can_view_horizon')) {
    function master_can_view_horizon(): bool
    {
        return master_can('horizon.view') || master_can('settings.edit');
    }
}

if (! function_exists('master_queue_uses_background_worker')) {
    function master_queue_uses_background_worker(): bool
    {
        return ! in_array(config('queue.default'), ['sync', 'null'], true);
    }
}

if (! function_exists('master_env')) {
    /** Use TENANT_DB_* when set; otherwise fall back to DB_* (empty string counts as unset). */
    function master_env(string $primary, string $fallback, string $default = ''): string
    {
        $value = env($primary);
        if ($value !== null && $value !== '') {
            return (string) $value;
        }

        $value = env($fallback);
        if ($value !== null && $value !== '') {
            return (string) $value;
        }

        return $default;
    }
}

if (! function_exists('mysql_connect_host')) {
    /**
     * Resolve MySQL hostname for PDO (optional IPv4-only for EC2 → RDS).
     */
    function mysql_connect_host(?string $host): string
    {
        $host = trim((string) $host);

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return $host !== '' ? $host : '127.0.0.1';
        }

        $forceIpv4 = env('DB_FORCE_IPV4');
        if ($forceIpv4 === null || $forceIpv4 === '') {
            $forceIpv4 = str_contains(strtolower($host), '.rds.amazonaws.com');
        }

        if (! filter_var($forceIpv4, FILTER_VALIDATE_BOOL)) {
            return $host;
        }

        $records = @dns_get_record($host, DNS_A);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (! empty($record['ip'])) {
                    return (string) $record['ip'];
                }
            }
        }

        return $host;
    }
}
