<?php

namespace App\Services;

use App\Support\TenantDomainHost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareDnsService
{
    protected ?string $lastError = null;

    public function isConfigured(): bool
    {
        return $this->apiToken() !== null
            && trim((string) config('master.dns_cloudflare_zone_id', '')) !== '';
    }

    public function apiToken(): ?string
    {
        $token = trim((string) config('master.cloudflare_api_token', ''));

        return $token !== '' ? $token : null;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function hostIsInZone(string $host, ?string $zoneBase = null): bool
    {
        $zoneBase = TenantDomainHost::normalize($zoneBase ?? $this->zoneBaseDomain());
        $host = TenantDomainHost::normalize($host);

        return $host === $zoneBase || str_ends_with($host, '.'.$zoneBase);
    }

    /**
     * Create or update an A record in the configured Cloudflare zone.
     */
    public function upsertARecord(string $host, string $ip): bool
    {
        $this->lastError = null;
        $zoneId = trim((string) config('master.dns_cloudflare_zone_id', ''));
        $token = $this->apiToken();

        if ($zoneId === '' || $token === null) {
            $this->lastError = 'Cloudflare API token or zone ID is missing. Set CLOUDFLARE_API_TOKEN and DNS_CLOUDFLARE_ZONE_ID in .env.';

            return false;
        }

        $zoneBase = $this->zoneBaseDomain();
        if (! $this->hostIsInZone($host, $zoneBase)) {
            $this->lastError = "Host {$host} is not under Cloudflare zone {$zoneBase}.";

            return false;
        }

        $recordName = $this->recordNameForApi($host, $zoneBase);
        $proxied = (bool) config('master.dns_cloudflare_proxied', false);

        try {
            $existing = $this->findARecordId($zoneId, $token, $host, $zoneBase);

            $payload = [
                'type' => 'A',
                'name' => $recordName,
                'content' => $ip,
                'proxied' => $proxied,
                'ttl' => $proxied ? 1 : 300,
            ];

            if ($existing !== null) {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->patch("https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records/{$existing}", $payload);
            } else {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records", $payload);
            }

            if (! $response->successful() || ! ($response->json('success') ?? false)) {
                $errors = $response->json('errors') ?? [];
                $message = isset($errors[0]['message'])
                    ? (string) $errors[0]['message']
                    : 'HTTP '.$response->status();
                $this->lastError = 'Cloudflare API: '.$message;

                Log::warning('Cloudflare DNS provision failed', [
                    'host' => $host,
                    'record_name' => $recordName,
                    'status' => $response->status(),
                    'errors' => $errors,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            Log::warning('Cloudflare DNS provision exception', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function zoneBaseDomain(): string
    {
        $base = trim((string) config('master.dns_cloudflare_base_domain', ''));

        if ($base !== '') {
            return TenantDomainHost::normalize($base);
        }

        return TenantDomainHost::registrableDomainFromCrmBase();
    }

    /**
     * Cloudflare accepts relative record names inside the zone (e.g. "apple" for apple.example.com).
     */
    public function recordNameForApi(string $host, ?string $zoneBase = null): string
    {
        $host = TenantDomainHost::normalize($host);
        $zoneBase = TenantDomainHost::normalize($zoneBase ?? $this->zoneBaseDomain());

        if ($host === $zoneBase) {
            return $zoneBase;
        }

        $suffix = '.'.$zoneBase;
        if (str_ends_with($host, $suffix)) {
            $relative = substr($host, 0, -strlen($suffix));

            return $relative !== '' ? $relative : $host;
        }

        return $host;
    }

    /**
     * @return list<string>
     */
    protected function recordNameSearchTerms(string $host, string $zoneBase): array
    {
        $host = TenantDomainHost::normalize($host);
        $zoneBase = TenantDomainHost::normalize($zoneBase);
        $apiName = $this->recordNameForApi($host, $zoneBase);

        $terms = [$apiName, $host, $host.'.'];
        if ($apiName === $zoneBase) {
            $terms[] = '@';
        }

        return array_values(array_unique(array_filter($terms, fn ($t) => $t !== null && $t !== '')));
    }

    protected function findARecordId(string $zoneId, string $token, string $host, string $zoneBase): ?string
    {
        foreach ($this->recordNameSearchTerms($host, $zoneBase) as $term) {
            if ($term === null || $term === '') {
                continue;
            }

            $response = Http::withToken($token)
                ->acceptJson()
                ->get("https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records", [
                    'type' => 'A',
                    'name' => $term,
                ]);

            if (! $response->successful()) {
                continue;
            }

            $results = $response->json('result') ?? [];

            if (isset($results[0]['id'])) {
                return (string) $results[0]['id'];
            }
        }

        return null;
    }
}
