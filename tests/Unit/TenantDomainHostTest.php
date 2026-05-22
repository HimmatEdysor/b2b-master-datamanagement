<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Support\TenantDomainHost;
use Tests\TestCase;

class TenantDomainHostTest extends TestCase
{
    public function test_prepare_nullable_host_accepts_empty_and_strips_scheme(): void
    {
        $this->assertNull(TenantDomainHost::prepareNullableHost(null));
        $this->assertNull(TenantDomainHost::prepareNullableHost(''));
        $this->assertNull(TenantDomainHost::prepareNullableHost('   '));
        $this->assertSame('crm.example.com', TenantDomainHost::prepareNullableHost('HTTPS://CRM.Example.com/path'));
    }

    public function test_optional_rules_allow_null(): void
    {
        $rules = TenantDomainHost::optionalCustomDomainRules();
        $this->assertContains('nullable', $rules);
    }

    public function test_setup_guide_includes_dns_and_ssl(): void
    {
        config([
            'master.custom_domain_server_ip' => '203.0.113.10',
            'master.custom_domain_ssl_email' => 'admin@example.com',
        ]);

        $tenant = new Tenant(['slug' => 'acme', 'name' => 'Acme']);

        $guide = TenantDomainHost::setupGuide('crm.client.com', $tenant);

        $this->assertSame('crm.client.com', $guide['host']);
        $this->assertSame('203.0.113.10', $guide['server_ip']);
        $this->assertStringContainsString('certbot', implode("\n", $guide['ssl_commands']));
        $this->assertNotEmpty($guide['dns_records']);
    }
}
