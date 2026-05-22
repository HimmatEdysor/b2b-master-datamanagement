<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantDomainDnsService;
use App\Support\TenantUrl;
use Illuminate\Console\Command;

class TenantDnsUpdateCommand extends Command
{
    protected $signature = 'tenant:dns-update
        {slug : Tenant slug}
        {--host= : Override hostname (e.g. sasada.guaranteeadmit.com for production test)}';

    protected $description = 'Run DNS Update for a tenant subdomain (Cloudflare A record or local link) — same as admin DNS Update button';

    public function handle(TenantDomainDnsService $dns): int
    {
        $tenant = Tenant::query()->where('slug', $this->argument('slug'))->first();

        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $tenant->load('domains');

        $host = $this->option('host');
        $domain = $host
            ? $tenant->domains->first(fn ($d) => $d->host === $host)
                ?? $tenant->domains->firstWhere('is_primary', true)
            : $tenant->domains->firstWhere('is_primary', true);

        if ($domain === null) {
            $expected = TenantUrl::subdomainHost($tenant->slug);
            $this->error("No domain row found. Expected primary host like [{$expected}]. Approve/provision the company first.");

            return self::FAILURE;
        }

        if ($host && $domain->host !== $host) {
            $this->warn("Using primary domain [{$domain->host}] — pass matching --host if you meant another row.");
        }

        $this->table(
            ['Setting', 'Value'],
            [
                ['APP_ENV', config('app.env')],
                ['Base domain', TenantUrl::baseDomain()],
                ['Domain host (DB)', $domain->host],
                ['CRM server IP', $dns->serverIp() ?? '(not set)'],
                ['DNS provider', $dns->dnsProviderLabel()],
                ['Cloudflare configured', $dns->cloudflareCanProvisionHost($domain->host) ? 'yes' : 'no'],
                ['Zone base', config('master.dns_cloudflare_base_domain', '—')],
            ]
        );

        if ($dns->serverIp() === null) {
            $this->error('Set CUSTOM_DOMAIN_SERVER_IP or Web settings → CRM server IP (production CRM server, not 127.0.0.1).');

            return self::FAILURE;
        }

        if (! $dns->cloudflareCanProvisionHost($domain->host) && $dns->dnsProvider() === 'cloudflare' && ! config('master.is_local')) {
            $this->warn("Host [{$domain->host}] may be outside Cloudflare zone ".config('master.dns_cloudflare_base_domain').'.');
        }

        $this->info('Running DNS Update…');
        $result = $dns->provisionForDomain($domain, $tenant);
        $domain->refresh();

        if ($result['verified']) {
            $this->info('OK: '.$result['message']);
            $this->line('dns_verified_at: '.($domain->dns_verified_at?->toDateTimeString() ?? '—'));
            $this->line('dns_target_ip: '.($domain->dns_target_ip ?? '—'));
            $this->line('Log: storage/logs/master-activity/dns/'.now()->format('Y-m-d').'.log');

            return self::SUCCESS;
        }

        $this->warn($result['ok'] ? 'Pending' : 'Failed');
        $this->line($result['message']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
