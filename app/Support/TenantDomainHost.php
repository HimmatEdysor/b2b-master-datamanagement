<?php

namespace App\Support;

use App\Models\Tenant;

class TenantDomainHost
{
    /** Hostname only (no scheme/path); lowercase after normalize(). */
    public const CUSTOM_DOMAIN_REGEX = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/';

    public static function normalize(string $host): string
    {
        $host = trim($host);
        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        $host = preg_replace('#/.*$#', '', $host) ?? $host;
        $host = strtolower(trim(preg_replace('/\s+/', '', $host) ?? ''));

        return rtrim($host, '.');
    }

    public static function prepareNullableHost(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $host = self::normalize((string) $value);

        return $host === '' ? null : $host;
    }

    /**
     * Optional custom domain on company create/edit/register (leave empty to skip).
     *
     * @return list<string|\Illuminate\Contracts\Validation\ValidationRule>
     */
    public static function optionalCustomDomainRules(): array
    {
        return ['nullable', 'string', 'max:255', 'regex:'.self::CUSTOM_DOMAIN_REGEX];
    }

    /**
     * Required hostname when adding via Domains UI form.
     *
     * @return list<string|\Illuminate\Contracts\Validation\ValidationRule>
     */
    public static function requiredCustomDomainRules(): array
    {
        return ['required', 'string', 'max:255', 'regex:'.self::CUSTOM_DOMAIN_REGEX];
    }

    public static function isBaseDomainHost(string $host): bool
    {
        $host = self::normalize($host);
        $base = TenantUrl::baseDomain();

        return $host === $base || $host === 'www.'.$base;
    }

    /**
     * DNS + SSL instructions shown in admin after a custom domain is added.
     *
     * @return array{
     *     host: string,
     *     server_ip: ?string,
     *     cname_target: string,
     *     dns_records: list<array{type: string, name: string, value: string, note: string}>,
     *     ssl_commands: list<string>,
     *     nginx_snippet: ?string
     * }
     */
    public static function setupGuide(string $host, Tenant $tenant): array
    {
        $host = self::normalize($host);
        $serverIp = trim((string) config('master.custom_domain_server_ip', ''));
        $cnameTarget = trim((string) config('master.custom_domain_cname_target', ''));
        if ($cnameTarget === '') {
            $cnameTarget = TenantUrl::subdomainHost($tenant->slug);
        }

        $sslEmail = trim((string) config('master.custom_domain_ssl_email', ''));
        $webroot = trim((string) config('master.custom_domain_ssl_webroot', '/var/www/html'));

        $dnsRecords = [];

        if ($serverIp !== '') {
            $dnsRecords[] = [
                'type' => 'A',
                'name' => $host,
                'value' => $serverIp,
                'note' => 'Points custom domain to your CRM server IP',
            ];
        }

        $dnsRecords[] = [
            'type' => 'CNAME',
            'name' => $host,
            'value' => $cnameTarget,
            'note' => $serverIp !== ''
                ? 'Alternative to A record — target CRM subdomain'
                : 'Point custom domain to CRM subdomain (set CUSTOM_DOMAIN_SERVER_IP for A record)',
        ];

        $certbotEmail = $sslEmail !== '' ? " -m {$sslEmail}" : '';
        $sslCommands = [
            '# SSH on CRM/web server, then run (Certbot + Nginx example):',
            "sudo certbot certonly --nginx -d {$host}{$certbotEmail}",
            '# Or Apache:',
            "sudo certbot certonly --apache -d {$host}{$certbotEmail}",
            '# Or webroot:',
            "sudo certbot certonly --webroot -w {$webroot} -d {$host}{$certbotEmail}",
            'sudo nginx -t && sudo systemctl reload nginx',
        ];

        $nginxSnippet = $serverIp !== '' ? implode("\n", [
            'server {',
            "    server_name {$host};",
            '    listen 443 ssl http2;',
            "    ssl_certificate     /etc/letsencrypt/live/{$host}/fullchain.pem;",
            "    ssl_certificate_key /etc/letsencrypt/live/{$host}/privkey.pem;",
            '    root /path/to/crm/public;',
            '    # … proxy to PHP / Laravel',
            '}',
        ]) : null;

        return [
            'host' => $host,
            'server_ip' => $serverIp !== '' ? $serverIp : null,
            'cname_target' => $cnameTarget,
            'dns_records' => $dnsRecords,
            'ssl_commands' => $sslCommands,
            'nginx_snippet' => $nginxSnippet,
        ];
    }
}
