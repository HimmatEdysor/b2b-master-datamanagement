<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\TenantDomainDnsService;
use App\Services\TenantDomainSslService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantDomainSslServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_http_skips_ssl_when_dns_linked(): void
    {
        config([
            'master.is_local' => true,
            'master.tenant_url_scheme' => 'http',
            'master.tenant_base_domain' => 'localhost',
            'master.tenant_crm_port' => 8000,
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
            'dns_verified_at' => now(),
            'dns_target_ip' => '127.0.0.1',
        ]);

        $ssl = app(TenantDomainSslService::class);
        $status = $ssl->statusFor($domain, $tenant, true);

        $this->assertTrue($status['complete']);
        $this->assertFalse($status['required']);
        $this->assertStringContainsString('http://apple.localhost', (string) $status['access_url']);
    }

    public function test_setup_ready_when_dns_and_ssl_complete(): void
    {
        config([
            'master.is_local' => false,
            'master.tenant_url_scheme' => 'https',
            'master.tenant_base_domain' => 'guaranteeadmit.com',
            'master.custom_domain_server_ip' => '203.0.113.10',
            'master.dns_provider' => 'manual',
            'master.dns_auto_link_subdomains' => true,
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
            'dns_verified_at' => now(),
            'dns_target_ip' => '203.0.113.10',
            'ssl_status' => 'active',
        ]);

        $dns = app(TenantDomainDnsService::class);
        $status = $dns->statusFor($domain, $tenant);

        $this->assertTrue($status['ready']);
        $this->assertSame('ready', $status['step']);
        $this->assertStringContainsString('https://acme.guaranteeadmit.com', (string) $status['ssl']['access_url']);
    }
}
