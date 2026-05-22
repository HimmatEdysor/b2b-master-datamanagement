<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\TenantDomainDnsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantDomainDnsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_subdomain_auto_links_when_server_ip_configured(): void
    {
        config([
            'master.custom_domain_server_ip' => '203.0.113.10',
            'master.dns_provider' => 'manual',
            'master.dns_auto_link_subdomains' => true,
            'master.tenant_base_domain' => 'guaranteeadmit.com',
            'master.is_local' => false,
        ]);

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

        $dns = app(TenantDomainDnsService::class);
        $result = $dns->provisionForDomain($domain, $tenant);

        $this->assertTrue($result['verified']);
        $domain->refresh();
        $this->assertNotNull($domain->dns_verified_at);
        $this->assertSame('203.0.113.10', $domain->dns_target_ip);

        $status = $dns->statusFor($domain, $tenant);
        $this->assertTrue($status['linked']);
        $this->assertFalse($status['pending']);
    }

    public function test_local_subdomain_auto_links_without_cloudflare(): void
    {
        config([
            'master.is_local' => true,
            'master.custom_domain_server_ip' => '127.0.0.1',
            'master.dns_provider' => 'cloudflare',
            'master.cloudflare_api_token' => 'cf-token',
            'master.dns_cloudflare_zone_id' => 'zone99',
            'master.dns_cloudflare_base_domain' => 'guaranteeadmit.com',
            'master.tenant_base_domain' => 'localhost',
            'master.dns_auto_link_subdomains' => true,
        ]);

        $tenant = Tenant::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'status' => 'active',
            'database_name' => 'b2b_tenant_apple',
        ]);
        $domain = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'apple.localhost',
            'type' => 'subdomain',
            'is_primary' => true,
        ]);

        $dns = app(TenantDomainDnsService::class);
        $result = $dns->provisionForDomain($domain, $tenant);

        $this->assertTrue($result['verified']);
        $domain->refresh();
        $this->assertNotNull($domain->dns_verified_at);
    }

    public function test_custom_domain_stays_pending_without_external_dns(): void
    {
        config([
            'master.custom_domain_server_ip' => '203.0.113.10',
            'master.dns_provider' => 'manual',
        ]);

        $tenant = Tenant::create([
            'name' => 'Acme',
            'slug' => 'acme',
            'status' => 'active',
            'database_name' => 'b2b_tenant_acme',
        ]);
        $domain = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'host' => 'crm.client.com',
            'type' => 'custom',
            'is_primary' => false,
        ]);

        $dns = app(TenantDomainDnsService::class);
        $result = $dns->provisionForDomain($domain, $tenant);

        $this->assertFalse($result['verified']);
        $domain->refresh();
        $this->assertNull($domain->dns_verified_at);
        $this->assertTrue($dns->isPending($domain, $tenant));
    }
}
