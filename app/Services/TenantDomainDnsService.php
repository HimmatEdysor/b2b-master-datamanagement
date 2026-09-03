<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Support\TenantDomainHost;
use App\Support\TenantUrl;
use Aws\Route53\Route53Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TenantDomainDnsService
{
    public function __construct(
        protected MasterActivityLogService $activityLog,
        protected CloudflareDnsService $cloudflare,
        protected TenantDomainSslService $ssl,
    ) {}

    public function serverIp(): ?string
    {
        $ip = trim((string) config('master.custom_domain_server_ip', ''));

        if ($ip !== '') {
            return $ip;
        }

        if (config('master.is_local', false)) {
            return '127.0.0.1';
        }

        return null;
    }

    public function dnsProvider(): string
    {
        $preferred = strtolower(trim((string) config('master.dns_provider', 'cloudflare')));

        if ($preferred === 'manual' || $preferred === 'none') {
            return 'manual';
        }

        if ($preferred === 'cloudflare' && $this->cloudflare->isConfigured()) {
            return 'cloudflare';
        }

        if ($preferred === 'route53' && $this->route53IsConfigured()) {
            return 'route53';
        }

        if ($this->cloudflare->isConfigured()) {
            return 'cloudflare';
        }

        if ($this->route53IsConfigured()) {
            return 'route53';
        }

        return 'manual';
    }

    public function dnsProviderLabel(): string
    {
        return match ($this->dnsProvider()) {
            'cloudflare' => 'Cloudflare',
            'route53' => 'Route53',
            default => 'Manual',
        };
    }

    public function autoProvisionEnabled(): bool
    {
        return in_array($this->dnsProvider(), ['cloudflare', 'route53'], true);
    }

    public function cloudflareCanProvisionHost(string $host): bool
    {
        return $this->dnsProvider() === 'cloudflare'
            && $this->cloudflare->isConfigured()
            && $this->cloudflare->hostIsInZone($host);
    }

    /**
     * Auto-link pending domains (Cloudflare API, Route53, or local subdomain fallback).
     *
     * @return list<array{host: string, verified: bool, message: string}>
     */
    public function autoProvisionPendingForTenant(Tenant $tenant): array
    {
        $tenant->loadMissing('domains');
        $results = [];

        foreach ($tenant->domains as $domain) {
            if (! $this->isPending($domain, $tenant)) {
                continue;
            }

            $result = $this->provisionForDomain($domain, $tenant);
            $results[] = [
                'host' => $domain->host,
                'verified' => $result['verified'],
                'message' => $result['message'],
            ];
        }

        return $results;
    }

    public function isPending(TenantDomain $domain, ?Tenant $tenant = null): bool
    {
        if ($domain->dns_verified_at !== null) {
            return false;
        }

        if (TenantDomainHost::isBaseDomainHost($domain->host)) {
            return false;
        }

        return in_array($domain->type, ['custom', 'subdomain'], true);
    }

    /**
     * @return array{
     *     linked: bool,
     *     pending: bool,
     *     label: string,
     *     badge: string,
     *     target_ip: ?string,
     *     a_record: ?array{name: string, value: string},
     *     can_provision: bool,
     *     can_verify: bool,
     *     auto_linked: bool,
     *     dns_provider: string
     * }
     */
    public function statusFor(TenantDomain $domain, Tenant $tenant): array
    {
        $ip = $domain->dns_target_ip ?: $this->serverIp();
        $linked = $domain->dns_verified_at !== null;
        $pending = $this->isPending($domain, $tenant);
        $autoLinked = $linked && $domain->type === 'subdomain' && $this->isManagedSubdomainHost($domain->host);
        $provider = $this->dnsProvider();

        $linkSource = $domain->dns_link_source;
        $label = match (true) {
            $linked && $linkSource === 'local' => 'Linked (local dev only)',
            $linked && $linkSource === 'cloudflare' => 'DNS linked (Cloudflare)',
            $linked && $linkSource === 'route53' => 'DNS linked (Route53)',
            $linked && $linkSource === 'marked' => 'DNS marked linked',
            $linked && $autoLinked && $provider !== 'manual' => 'DNS linked ('.$this->dnsProviderLabel().')',
            $linked && $autoLinked => 'DNS linked (server)',
            $linked => 'DNS linked',
            $pending => 'DNS linking pending',
            default => 'DNS',
        };

        $badge = $linked ? 'badge-active badge-dns-linked' : ($pending ? 'badge-pending badge-dns-pending' : 'badge-draft');

        $ssl = $this->ssl->statusFor($domain, $tenant, $linked);
        $ready = $linked && $ssl['complete'];

        $canLocalLink = $this->shouldAutoLinkLocalSubdomain($domain);
        $canCloudflareApi = $ip && $this->cloudflareCanProvisionHost($domain->host);
        $canRoute53Api = $ip && $this->dnsProvider() === 'route53';
        $canUpdateDns = ($ip !== null || $canLocalLink)
            && ! TenantDomainHost::isBaseDomainHost($domain->host);

        $provisionLabel = match (true) {
            $canLocalLink && $pending => 'Link DNS locally ('.$ip.')',
            $canCloudflareApi && $pending => 'Add DNS via Cloudflare API',
            $canRoute53Api && $pending => 'Add DNS via Route53 API',
            $linked => 'DNS Update',
            default => 'DNS Update',
        };

        return [
            'linked' => $linked,
            'pending' => $pending,
            'label' => $label,
            'badge' => $badge,
            'target_ip' => $ip,
            'a_record' => $ip && ! TenantDomainHost::isBaseDomainHost($domain->host)
                ? ['name' => TenantUrl::normalizeHostForEnvironment($domain->host, $tenant->slug), 'value' => $ip]
                : null,
            'can_provision' => $pending && $canUpdateDns,
            'can_update_dns' => $canUpdateDns,
            'can_verify' => $pending && $this->serverIp() !== null,
            'can_auto_cloudflare' => $canCloudflareApi,
            'can_local_link' => $canLocalLink,
            'provision_button_label' => $provisionLabel,
            'auto_linked' => $autoLinked,
            'dns_provider' => $provider,
            'dns_link_source' => $linkSource,
            'cloudflare_applicable' => $this->cloudflareCanProvisionHost($domain->host),
            'ssl' => $ssl,
            'ready' => $ready,
            'step' => match (true) {
                $ready => 'ready',
                $linked => 'ssl',
                $pending => 'dns',
                default => 'dns',
            },
        ];
    }

    /**
     * @return array{ok: bool, message: string, verified: bool}
     */
    public function provisionForDomain(TenantDomain $domain, Tenant $tenant): array
    {
        $ip = $this->serverIp();
        if ($ip === null) {
            $message = 'Set CRM server IP in Web settings (or CUSTOM_DOMAIN_SERVER_IP in .env).';
            $this->activityLog->dns('dns_update', 'failed', $message, $tenant, null, ['host' => $domain->host]);

            return [
                'ok' => false,
                'message' => $message,
                'verified' => false,
            ];
        }

        $domain->update(['dns_target_ip' => $ip]);

        if ($this->shouldAutoLinkLocalSubdomain($domain)) {
            $this->writeLocalHostsSnippet($domain->host, $ip, $tenant);

            return $this->markVerified(
                $domain,
                $tenant,
                'Marked linked for local dev only ('.$domain->host.' → '.$ip.'). No record was added in Cloudflare — for live DNS use '
                .$this->productionHostForTenant($tenant).' on production master with a public server IP.',
                'dns_update',
                'local'
            );
        }

        if ($this->cloudflareCanProvisionHost($domain->host) && $this->isLoopbackIp($ip)) {
            $message = "Cannot create Cloudflare A record with IP {$ip}. Set CRM server IP to your public server address in Web settings (not 127.0.0.1).";
            $this->activityLog->dns('dns_update', 'failed', $message, $tenant, null, ['host' => $domain->host, 'ip' => $ip]);

            return ['ok' => false, 'message' => $message, 'verified' => false];
        }

        if ($this->cloudflareCanProvisionHost($domain->host)) {
            if ($this->upsertDnsARecord($domain->host, $ip)) {
                return $this->markVerified(
                    $domain,
                    $tenant,
                    'Cloudflare A record created/updated: '.$domain->host.' → '.$ip.'. Check DNS records in your Cloudflare dashboard.',
                    'dns_update',
                    'cloudflare'
                );
            }

            $cfHint = $this->cloudflare->lastError()
                ? ' '.$this->cloudflare->lastError()
                : '';
            $message = 'Cloudflare DNS API failed for '.$domain->host.'.'.$cfHint
                .' Regenerate CLOUDFLARE_API_TOKEN (Zone → DNS → Edit) in .env or add the A record manually in Cloudflare.';
            $this->activityLog->dns('dns_update', 'failed', trim($message), $tenant, null, [
                'host' => $domain->host,
                'ip' => $ip,
                'link_source' => 'cloudflare',
                'cloudflare_record_created' => false,
            ]);

            return ['ok' => false, 'message' => $message, 'verified' => false];
        }

        if ($this->upsertDnsARecord($domain->host, $ip)) {
            $via = $this->dnsProvider() === 'route53' ? 'route53' : 'cloudflare';

            return $this->markVerified(
                $domain,
                $tenant,
                $this->dnsProviderLabel()." A record created/updated: {$domain->host} → {$ip}.",
                'dns_update',
                $via
            );
        }

        if ($domain->type === 'subdomain' && $this->isManagedSubdomainHost($domain->host) && config('master.dns_auto_link_subdomains', true)) {
            $cfHint = $this->cloudflare->lastError()
                ? ' Cloudflare: '.$this->cloudflare->lastError()
                : '';

            if (! $this->cloudflareCanProvisionHost($domain->host)) {
                $this->writeLocalHostsSnippet($domain->host, $ip, $tenant);

                return $this->markVerified(
                    $domain,
                    $tenant,
                    "Subdomain {$domain->host} marked linked ({$ip}). Host is not in Cloudflare zone ".$this->cloudflare->zoneBaseDomain().' — add the A record manually in Cloudflare.',
                    'dns_update',
                    'marked'
                );
            }

            $message = 'Cloudflare DNS could not be updated for '.$domain->host.'.'.$cfHint
                .' Check CLOUDFLARE_API_TOKEN (Zone.DNS Edit) and zone ID in Web settings.';
            $this->activityLog->dns('dns_update', 'failed', $message, $tenant, null, ['host' => $domain->host, 'ip' => $ip]);

            return [
                'ok' => false,
                'message' => $message,
                'verified' => false,
            ];
        }

        if ($domain->type === 'custom') {
            $providerHint = $this->autoProvisionEnabled()
                ? ' Configure Cloudflare zone in Web settings for auto A records.'
                : ' Add the A record in Cloudflare dashboard.';

            $this->activityLog->dns(
                'dns_pending',
                'pending',
                "Custom domain {$domain->host}: A record → {$ip}",
                $tenant,
                null,
                ['host' => $domain->host, 'ip' => $ip]
            );

            return [
                'ok' => true,
                'message' => "DNS pending — point {$domain->host} A record to {$ip} in Cloudflare, then Verify DNS.{$providerHint}",
                'verified' => false,
            ];
        }

        $cfHint = $this->cloudflare->lastError() ? ' '.$this->cloudflare->lastError() : '';

        $message = 'DNS could not be provisioned. Check Web settings → DNS provider (Cloudflare zone + API token).'.$cfHint;
        $this->activityLog->dns('dns_update', 'failed', $message, $tenant, null, ['host' => $domain->host, 'ip' => $ip]);

        return [
            'ok' => false,
            'message' => $message,
            'verified' => false,
        ];
    }

    protected function shouldAutoLinkLocalSubdomain(TenantDomain $domain): bool
    {
        if (! config('master.is_local', false)) {
            return false;
        }

        if ($domain->type !== 'subdomain' || ! config('master.dns_auto_link_subdomains', true)) {
            return false;
        }

        // *.guaranteeadmit.com (Cloudflare zone) must use API — never “local only” mark
        if ($this->cloudflareCanProvisionHost($domain->host) || $this->hostIsProductionZone($domain->host)) {
            return false;
        }

        return $this->isManagedSubdomainHost($domain->host);
    }

    protected function hostIsProductionZone(string $host): bool
    {
        $zone = TenantDomainHost::normalize(
            (string) config('master.dns_cloudflare_base_domain')
            ?: TenantDomainHost::registrableDomainFromCrmBase()
        );
        $host = TenantDomainHost::normalize($host);

        return $host === $zone || str_ends_with($host, '.'.$zone);
    }

    /**
     * @return array{ok: bool, message: string, verified: bool}
     */
    public function verifyForDomain(TenantDomain $domain, Tenant $tenant): array
    {
        $expected = $domain->dns_target_ip ?: $this->serverIp();
        if ($expected === null) {
            return [
                'ok' => false,
                'message' => 'Server IP is not configured (Web settings or .env).',
                'verified' => false,
            ];
        }

        if ($this->resolvesToIp($domain->host, $expected)) {
            return $this->markVerified(
                $domain,
                $tenant,
                "DNS verified: {$domain->host} → {$expected}",
                'dns_verify',
                $domain->dns_link_source ?: 'cloudflare'
            );
        }

        if (config('master.is_local', false)
            && $this->isManagedSubdomainHost($domain->host)
            && $domain->dns_target_ip === $expected) {
            return $this->markVerified($domain, $tenant, "DNS verified (local): {$domain->host} → {$expected}", 'dns_verify', 'local');
        }

        $proxiedNote = config('master.dns_cloudflare_proxied')
            ? ' If Cloudflare proxy (orange cloud) is on, A record may show Cloudflare IPs — disable proxy for direct IP verify or use proxied=false.'
            : '';

        $message = "DNS not pointing to {$expected} yet. Set A record in Cloudflare for {$domain->host}.{$proxiedNote}";
        $this->activityLog->dns('dns_verify', 'failed', $message, $tenant, null, ['host' => $domain->host, 'ip' => $expected]);

        return [
            'ok' => false,
            'message' => $message,
            'verified' => false,
        ];
    }

    public function provisionAllForTenant(Tenant $tenant): void
    {
        $tenant->loadMissing('domains');

        foreach ($tenant->domains as $domain) {
            if ($this->isPending($domain, $tenant) || ($domain->type === 'subdomain' && $domain->dns_verified_at === null)) {
                $this->provisionForDomain($domain, $tenant);
            }
        }
    }

    public function isManagedSubdomainHost(string $host): bool
    {
        $host = TenantDomainHost::normalize($host);
        $base = TenantUrl::baseDomain();
        $suffix = '.'.$base;

        return $host !== $base && str_ends_with($host, $suffix);
    }

    protected function productionHostForTenant(Tenant $tenant): string
    {
        $prodBase = TenantDomainHost::normalize(
            (string) config('master.tenant_base_domain_production', 'main.guaranteeadmit.com')
        );

        if (TenantUrl::isPlatformDefaultSlug($tenant->slug)) {
            return $prodBase;
        }

        return $tenant->slug.'.'.$prodBase;
    }

    protected function upsertDnsARecord(string $host, string $ip): bool
    {
        return match ($this->dnsProvider()) {
            'cloudflare' => $this->cloudflare->upsertARecord($host, $ip),
            'route53' => $this->upsertRoute53ARecord($host, $ip),
            default => false,
        };
    }

    protected function route53IsConfigured(): bool
    {
        return trim((string) config('master.dns_route53_hosted_zone_id', '')) !== ''
            && env('AWS_ACCESS_KEY_ID')
            && env('AWS_SECRET_ACCESS_KEY');
    }

    protected function markVerified(
        TenantDomain $domain,
        Tenant $tenant,
        string $logMessage,
        string $action = 'dns_update',
        ?string $linkSource = null,
    ): array {
        $domain->update([
            'dns_verified_at' => now(),
            'dns_link_source' => $linkSource,
        ]);
        $domain->refresh();

        $logStatus = match ($linkSource) {
            'local' => 'local',
            'marked' => 'pending',
            default => 'ok',
        };

        $this->activityLog->dns(
            $action,
            $logStatus,
            $logMessage,
            $tenant,
            null,
            [
                'host' => $domain->host,
                'ip' => $domain->dns_target_ip,
                'link_source' => $linkSource,
                'cloudflare_record_created' => $linkSource === 'cloudflare',
            ]
        );

        return [
            'ok' => true,
            'message' => $logMessage,
            'verified' => true,
            'link_source' => $linkSource,
        ];
    }

    protected function isLoopbackIp(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'], true)
            || str_starts_with($ip, '127.');
    }

    protected function resolvesToIp(string $host, string $expectedIp): bool
    {
        $host = TenantDomainHost::normalize($host);
        $records = @dns_get_record($host, DNS_A);

        if (! is_array($records)) {
            return false;
        }

        foreach ($records as $record) {
            if (($record['ip'] ?? '') === $expectedIp) {
                return true;
            }
        }

        return false;
    }

    protected function upsertRoute53ARecord(string $host, string $ip): bool
    {
        $zoneId = trim((string) config('master.dns_route53_hosted_zone_id', ''));
        if ($zoneId === '') {
            return false;
        }

        $zoneBase = TenantDomainHost::normalize(
            (string) config('master.dns_route53_base_domain')
            ?: TenantDomainHost::registrableDomainFromCrmBase()
        );

        $recordName = $this->recordFqdnInZone($host, $zoneBase);
        if ($recordName === null) {
            return false;
        }

        $key = env('AWS_ACCESS_KEY_ID');
        $secret = env('AWS_SECRET_ACCESS_KEY');
        if (! $key || ! $secret) {
            return false;
        }

        try {
            $client = new Route53Client([
                'version' => 'latest',
                'region' => config('master.dns_route53_region', env('AWS_DEFAULT_REGION', 'us-east-1')),
                'credentials' => [
                    'key' => $key,
                    'secret' => $secret,
                ],
            ]);

            $client->changeResourceRecordSets([
                'HostedZoneId' => $zoneId,
                'ChangeBatch' => [
                    'Changes' => [[
                        'Action' => 'UPSERT',
                        'ResourceRecordSet' => [
                            'Name' => $recordName,
                            'Type' => 'A',
                            'TTL' => 300,
                            'ResourceRecords' => [['Value' => $ip]],
                        ],
                    ]],
                ],
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Route53 DNS provision failed', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function recordFqdnInZone(string $host, string $zoneBase): ?string
    {
        $host = TenantDomainHost::normalize($host);
        $zoneBase = TenantDomainHost::normalize($zoneBase);

        if ($host === $zoneBase) {
            return $zoneBase.'.';
        }

        $suffix = '.'.$zoneBase;
        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        return $host.'.';
    }

    protected function writeLocalHostsSnippet(string $host, string $ip, Tenant $tenant): void
    {
        if (! config('master.is_local', false)) {
            return;
        }

        $line = "{$ip}\t{$host}\n";
        Storage::disk('local')->put("dns-hosts/{$tenant->slug}.txt", $line);
    }
}
