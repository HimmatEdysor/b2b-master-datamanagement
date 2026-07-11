<?php

namespace App\Services;

use App\Models\MasterSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MasterSettingsService
{
    public const CACHE_KEY = 'master_settings.all';

    public function applyToConfig(): void
    {
        if (! $this->databaseIsAvailable()) {
            return;
        }

        try {
            if (! Schema::hasTable('master_settings')) {
                return;
            }

            $skipKeys = [];
            if ($this->shouldPreferEnvUrls()) {
                $skipKeys = [
                    'tenant_base_domain',
                    'tenant_base_domain_production',
                    'tenant_url_scheme',
                    'tenant_crm_port',
                    'tenant_crm_port_force',
                    'master_url',
                    'master_domain',
                    'custom_domain_server_ip',
                    'custom_domain_cname_target',
                    'custom_domain_ssl_email',
                    'custom_domain_ssl_webroot',
                    'dns_provider',
                    'dns_auto_link_subdomains',
                    'dns_cloudflare_zone_id',
                    'dns_cloudflare_base_domain',
                    'dns_cloudflare_proxied',
                    'dns_route53_hosted_zone_id',
                    'dns_route53_base_domain',
                    'dns_route53_region',
                    // In local dev, prefer .env for DB provisioning settings (Web settings are usually production/RDS).
                    'tenant_db_host',
                    'tenant_db_port',
                    'tenant_db_username',
                    'tenant_db_password',
                    'tenant_db_shared_credentials',
                    'tenant_db_grant_admin_on_create',
                    'tenant_db_user_hosts',
                ];
            }

            foreach ($this->storedMap() as $key => $raw) {
                if (in_array($key, $skipKeys, true)) {
                    continue;
                }

                if ($this->shouldSkipLocalOnlySettingInProduction($key, $raw)) {
                    continue;
                }

                $field = $this->fieldDefinition($key);
                if ($field === null || $raw === null || $raw === '') {
                    continue;
                }

                $value = $this->castStored($raw, $field['type']);
                $configKey = 'master.'.$field['config'];
                config([$configKey => $value]);
            }
        } catch (\Throwable) {
            return;
        }
    }

    public function databaseIsAvailable(): bool
    {
        try {
            Schema::getConnection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, array{definition: array, value: mixed, source: string, env_fallback: mixed}>
     */
    public function formState(): array
    {
        $stored = $this->storedMap();
        $state = [];

        foreach ($this->fields() as $key => $field) {
            $configValue = $this->configValue($field['config']);
            $hasStored = array_key_exists($key, $stored) && $stored[$key] !== null && $stored[$key] !== '';

            $state[$key] = [
                'definition' => $field,
                'value' => $hasStored ? $this->castStored($stored[$key], $field['type']) : $configValue,
                'source' => $hasStored ? 'database' : 'env',
                'env_fallback' => $configValue,
            ];
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(array $input): void
    {
        foreach ($this->fields() as $key => $field) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if ($field['type'] === 'password') {
                if ($value === null || $value === '') {
                    continue;
                }
            }

            if ($field['type'] === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0';
            } elseif ($field['type'] === 'csv') {
                $value = $this->normalizeCsv($value);
            } else {
                $value = is_string($value) ? trim($value) : (string) $value;
            }

            if ($value === '' && $field['type'] !== 'password') {
                MasterSetting::query()->where('key', $key)->delete();

                continue;
            }

            MasterSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $this->clearCache();
        $this->applyToConfig();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function shouldPreferEnvUrls(): bool
    {
        if (env('APP_ENV') === 'local') {
            return true;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1'], true);
    }

    /**
     * Web settings saved on a local machine must not override production tenant URLs.
     */
    protected function shouldSkipLocalOnlySettingInProduction(string $key, ?string $raw): bool
    {
        if (env('APP_ENV') !== 'production' || $raw === null || $raw === '') {
            return false;
        }

        return match ($key) {
            'tenant_base_domain' => in_array(strtolower(trim($raw)), ['localhost', '127.0.0.1'], true),
            'tenant_url_scheme' => strtolower(trim($raw)) === 'http',
            default => false,
        };
    }

    /**
     * Where the effective tenant DB admin password comes from (for diagnostics).
     */
    public function tenantDbPasswordSource(): string
    {
        $stored = $this->storedMap()['tenant_db_password'] ?? null;

        if ($stored !== null && $stored !== '') {
            return 'master_settings';
        }

        $tenantPass = env('TENANT_DB_PASSWORD');
        if ($tenantPass !== null && $tenantPass !== '') {
            return 'env_tenant';
        }

        $dbPass = env('DB_PASSWORD');
        if ($dbPass !== null && $dbPass !== '') {
            return 'env_db_fallback';
        }

        return 'empty';
    }

    /**
     * @return array<string, string|null>
     */
    protected function storedMap(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return MasterSetting::query()
                ->pluck('value', 'key')
                ->all();
        });
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return config('master_settings.fields', []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sections(): array
    {
        return config('master_settings.sections', []);
    }

    /**
     * @return array<string, string>
     */
    public function envOnlyKeys(): array
    {
        return config('master_settings.env_only_keys', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fieldDefinition(string $key): ?array
    {
        return $this->fields()[$key] ?? null;
    }

    protected function configValue(string $configKey): mixed
    {
        return config('master.'.$configKey);
    }

    protected function castStored(string $raw, string $type): mixed
    {
        return match ($type) {
            'boolean' => $raw === '1' || $raw === 'true',
            'csv' => array_values(array_filter(array_map('trim', explode(',', $raw)))),
            default => $raw,
        };
    }

    protected function normalizeCsv(mixed $value): string
    {
        if (is_array($value)) {
            return implode(',', array_values(array_filter(array_map('trim', $value))));
        }

        return implode(',', array_values(array_filter(array_map('trim', explode(',', (string) $value)))));
    }
}
