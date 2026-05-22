<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Support\TenantDomainHost;
use App\Support\TenantUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TenantDomainSslService
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING = 'pending';

    public function __construct(
        protected MasterActivityLogService $activityLog,
    ) {}

    public function needsSsl(): bool
    {
        if (! config('master.is_local', false)) {
            return true;
        }

        return TenantUrl::scheme() === 'https';
    }

    public function isComplete(TenantDomain $domain): bool
    {
        if (! $this->needsSsl()) {
            return $domain->dns_verified_at !== null;
        }

        return $domain->ssl_status === self::STATUS_ACTIVE;
    }

    /**
     * @return array{
     *     complete: bool,
     *     pending: bool,
     *     required: bool,
     *     label: string,
     *     badge: string,
     *     can_check: bool,
     *     can_mark_complete: bool,
     *     access_url: ?string,
     *     access_label: string
     * }
     */
    public function statusFor(TenantDomain $domain, Tenant $tenant, bool $dnsLinked): array
    {
        $displayHost = TenantUrl::normalizeHostForEnvironment($domain->host, $tenant->slug);
        $required = $this->needsSsl();
        $complete = $required
            ? $domain->ssl_status === self::STATUS_ACTIVE
            : $dnsLinked;

        $pending = $dnsLinked && $required && ! $complete;
        $accessUrl = ($dnsLinked && $complete) ? $this->accessUrlFor($displayHost) : null;

        $label = match (true) {
            ! $dnsLinked => 'Waiting for DNS',
            $complete && ! $required => 'Ready (HTTP local)',
            $complete => 'SSL complete',
            $pending => 'SSL pending',
            default => 'SSL',
        };

        $badge = match (true) {
            ! $dnsLinked => 'badge-draft',
            $complete => 'badge-active badge-ssl-complete',
            $pending => 'badge-pending badge-ssl-pending',
            default => 'badge-draft',
        };

        return [
            'complete' => $complete,
            'pending' => $pending,
            'required' => $required,
            'label' => $label,
            'badge' => $badge,
            'can_check' => $dnsLinked && $required && ! $complete,
            'can_mark_complete' => $dnsLinked && $required && ! $complete,
            'can_apply_ssl' => $dnsLinked,
            'access_url' => $accessUrl,
            'access_label' => $accessUrl && str_starts_with($accessUrl, 'https://')
                ? 'Open HTTPS'
                : 'Open CRM',
        ];
    }

    /**
     * @return array{ok: bool, message: string, active: bool}
     */
    public function checkForDomain(TenantDomain $domain, Tenant $tenant): array
    {
        if ($domain->dns_verified_at === null) {
            return [
                'ok' => false,
                'message' => 'Complete DNS linking first.',
                'active' => false,
            ];
        }

        if (! $this->needsSsl()) {
            return [
                'ok' => true,
                'message' => 'SSL is not required for local HTTP — CRM is ready to open.',
                'active' => true,
            ];
        }

        $host = TenantDomainHost::normalize($domain->host);
        $probe = $this->probeHttps($host);

        if ($probe['ok']) {
            $domain->update([
                'ssl_status' => self::STATUS_ACTIVE,
                'ssl_expires_at' => $probe['expires_at'],
            ]);
            $this->activityLog->dns('ssl_apply', 'ok', "SSL active for {$host}", $tenant, null, ['host' => $host]);

            return [
                'ok' => true,
                'message' => "HTTPS is responding for {$host}.",
                'active' => true,
            ];
        }

        $domain->update(['ssl_status' => self::STATUS_PENDING]);
        $this->activityLog->dns('ssl_check', 'failed', $probe['message'], $tenant, null, ['host' => $host]);

        return [
            'ok' => false,
            'message' => $probe['message'],
            'active' => false,
        ];
    }

    /**
     * @return array{ok: bool, message: string, active: bool}
     */
    public function markComplete(TenantDomain $domain, Tenant $tenant): array
    {
        if ($domain->dns_verified_at === null) {
            return [
                'ok' => false,
                'message' => 'Complete DNS linking first.',
                'active' => false,
            ];
        }

        if (! $this->needsSsl()) {
            return [
                'ok' => true,
                'message' => 'CRM is available over HTTP in local mode.',
                'active' => true,
            ];
        }

        $host = TenantDomainHost::normalize($domain->host);
        $domain->update([
            'ssl_status' => self::STATUS_ACTIVE,
            'ssl_expires_at' => null,
        ]);
        $this->activityLog->dns('ssl_apply', 'ok', "SSL applied for {$host}", $tenant, null, ['host' => $host]);

        return [
            'ok' => true,
            'message' => "SSL applied for {$host}. Open the site to confirm HTTPS works.",
            'active' => true,
        ];
    }

    protected function accessUrlFor(string $displayHost): ?string
    {
        return TenantUrl::urlForHost($displayHost);
    }

    /**
     * @return array{ok: bool, message: string, expires_at: ?\Illuminate\Support\Carbon}
     */
    protected function probeHttps(string $host): array
    {
        $url = 'https://'.$host.TenantUrl::portSuffix();

        try {
            $response = Http::withOptions([
                'verify' => false,
                'allow_redirects' => true,
                'timeout' => 8,
            ])->get($url);

            if ($response->successful() || $response->status() < 500) {
                return [
                    'ok' => true,
                    'message' => 'HTTPS reachable.',
                    'expires_at' => null,
                ];
            }

            return [
                'ok' => false,
                'message' => "HTTPS returned status {$response->status()}. Run Certbot on the server, then check again.",
                'expires_at' => null,
            ];
        } catch (\Throwable $e) {
            Log::debug('SSL probe failed', ['host' => $host, 'error' => $e->getMessage()]);

            return [
                'ok' => false,
                'message' => 'HTTPS not reachable yet. After DNS propagates, run Certbot (see SSL steps below), then Check SSL or Mark SSL complete.',
                'expires_at' => null,
            ];
        }
    }
}
