<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\TenantDomainDnsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TenantDomainDnsCloudflareTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_uses_cloudflare_when_configured(): void
    {
        config([
            'master.dns_provider' => 'cloudflare',
            'master.cloudflare_api_token' => 'cf-token',
            'master.dns_cloudflare_zone_id' => 'zone99',
            'master.dns_cloudflare_base_domain' => 'guaranteeadmit.com',
            'master.custom_domain_server_ip' => '203.0.113.55',
            'master.dns_auto_link_subdomains' => false,
            'master.is_local' => false,
        ]);

        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(['success' => true, 'result' => []], 200);
            }

            return Http::response(['success' => true, 'result' => ['id' => 'r1']], 200);
        });

        $tenant = Tenant::create([
            'name' => 'Acme',
            'slug' => 'acme',
            'status' => 'active',
            'database_name' => 'b2b_tenant_acme',
        ]);

        $domain = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'acme.guaranteeadmit.com',
            'type' => 'subdomain',
            'is_primary' => true,
        ]);

        $result = app(TenantDomainDnsService::class)->provisionForDomain($domain, $tenant);

        $this->assertTrue($result['verified']);
        $this->assertStringContainsString('Cloudflare', $result['message']);
        $domain->refresh();
        $this->assertNotNull($domain->dns_verified_at);
    }
}
