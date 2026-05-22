<?php

namespace App\Console\Commands;

use App\Services\CloudflareDnsService;
use App\Support\TenantDomainHost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CloudflareDnsVerifyCommand extends Command
{
    protected $signature = 'cloudflare:verify';

    protected $description = 'Verify CLOUDFLARE_API_TOKEN and zone ID against the Cloudflare API';

    public function handle(CloudflareDnsService $cloudflare): int
    {
        $token = $cloudflare->apiToken();
        $zoneId = trim((string) config('master.dns_cloudflare_zone_id', ''));
        $zoneBase = $cloudflare->zoneBaseDomain();

        if ($token === null) {
            $this->error('CLOUDFLARE_API_TOKEN is missing in .env');

            return self::FAILURE;
        }

        if ($zoneId === '') {
            $this->error('DNS_CLOUDFLARE_ZONE_ID is missing in .env');

            return self::FAILURE;
        }

        $this->line('Token length: '.strlen($token).' (starts with '.substr($token, 0, 4).'…)');
        $this->line('Zone ID: '.$zoneId);
        $this->line('Zone base domain: '.$zoneBase);

        $verify = Http::withToken($token)
            ->acceptJson()
            ->get('https://api.cloudflare.com/client/v4/user/tokens/verify');

        if (! ($verify->successful() && ($verify->json('success') ?? false))) {
            $msg = $verify->json('errors.0.message') ?? ('HTTP '.$verify->status());
            $this->error('Token verify failed: '.$msg);
            $this->line('');
            $this->line('Create a new token: Cloudflare → My Profile → API Tokens → Create Token');
            $this->line('  Template: Edit zone DNS');
            $this->line('  Zone Resources: guaranteeadmit.com (or Include → Specific zone)');
            $this->line('  Permission: Zone → DNS → Edit');
            $this->line('Put the new token in .env as CLOUDFLARE_API_TOKEN= (not the Global API Key).');

            return self::FAILURE;
        }

        $this->info('Token is valid.');

        $zone = Http::withToken($token)
            ->acceptJson()
            ->get('https://api.cloudflare.com/client/v4/zones/'.$zoneId);

        if (! ($zone->successful() && ($zone->json('success') ?? false))) {
            $msg = $zone->json('errors.0.message') ?? ('HTTP '.$zone->status());
            $this->error('Zone ID check failed: '.$msg);
            $this->line('Copy Zone ID from Cloudflare → guaranteeadmit.com → Overview (right column).');

            return self::FAILURE;
        }

        $zoneName = $zone->json('result.name') ?? '?';
        $this->info("Zone OK: {$zoneName} ({$zoneId})");

        if (TenantDomainHost::normalize($zoneName) !== TenantDomainHost::normalize($zoneBase)) {
            $this->warn("DNS_CLOUDFLARE_BASE_DOMAIN is [{$zoneBase}] but zone name is [{$zoneName}] — align these in .env / Web settings.");
        }

        return self::SUCCESS;
    }
}
