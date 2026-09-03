<?php

namespace Tests\Unit;

use App\Support\TenantUrl;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'master.is_local' => true,
            'master.tenant_base_domain' => 'localhost',
            'master.tenant_base_domain_production' => 'main.guaranteeadmit.com',
            'master.platform_default_slug' => 'guaranteeadmit',
            'master.tenant_url_scheme' => 'http',
            'master.tenant_crm_port' => '8000',
            'master.tenant_crm_port_force' => false,
        ]);
    }

    protected function tearDown(): void
    {
        config([
            'master.is_local' => true,
            'master.tenant_base_domain' => 'localhost',
            'master.tenant_base_domain_production' => 'main.guaranteeadmit.com',
            'master.tenant_url_scheme' => 'http',
            'master.tenant_crm_port' => '8000',
            'master.tenant_crm_port_force' => false,
        ]);

        parent::tearDown();
    }

    #[Test]
    public function local_localhost_urls_include_dev_port(): void
    {
        config([
            'master.is_local' => true,
            'master.tenant_base_domain' => 'localhost',
            'master.tenant_url_scheme' => 'http',
            'master.tenant_crm_port' => '8000',
            'master.tenant_crm_port_force' => false,
        ]);

        $this->assertSame(
            'http://acme.localhost:8000',
            TenantUrl::urlForSlug('acme')
        );
    }

    #[Test]
    public function production_env_never_appends_port(): void
    {
        config([
            'master.is_local' => false,
            'master.tenant_base_domain' => 'main.guaranteeadmit.com',
            'master.tenant_url_scheme' => 'https',
            'master.tenant_crm_port' => '8000',
        ]);

        $this->assertSame('', TenantUrl::portSuffix());
        $this->assertSame(
            'https://acme.main.guaranteeadmit.com',
            TenantUrl::urlForSlug('acme')
        );
    }

    #[Test]
    public function platform_default_slug_uses_crm_apex_not_nested_subdomain(): void
    {
        config([
            'master.is_local' => false,
            'master.tenant_base_domain' => 'main.guaranteeadmit.com',
            'master.tenant_base_domain_production' => 'main.guaranteeadmit.com',
            'master.platform_default_slug' => 'guaranteeadmit',
            'master.tenant_url_scheme' => 'https',
        ]);

        $this->assertSame('main.guaranteeadmit.com', TenantUrl::subdomainHost('guaranteeadmit'));
        $this->assertSame('newcompany.main.guaranteeadmit.com', TenantUrl::subdomainHost('newcompany'));
        $this->assertTrue(TenantUrl::isApexHost('main.guaranteeadmit.com'));
        $this->assertTrue(TenantUrl::isApexHost('www.main.guaranteeadmit.com'));
        $this->assertFalse(TenantUrl::isApexHost('newcompany.main.guaranteeadmit.com'));
        $this->assertSame(
            'https://main.guaranteeadmit.com',
            TenantUrl::urlForSlug('guaranteeadmit')
        );
    }

    #[Test]
    public function local_env_with_production_base_domain_omits_port(): void
    {
        config([
            'master.is_local' => true,
            'master.tenant_base_domain' => 'main.guaranteeadmit.com',
            'master.tenant_base_domain_production' => 'main.guaranteeadmit.com',
            'master.tenant_url_scheme' => 'http',
            'master.tenant_crm_port' => '8000',
        ]);

        $this->assertFalse(TenantUrl::usesPortInUrls());
        $this->assertSame(
            'http://inrationeutseddo.main.guaranteeadmit.com',
            TenantUrl::urlForSlug('inrationeutseddo')
        );
    }

    #[Test]
    public function trailing_slash_on_host_does_not_produce_slash_before_port(): void
    {
        config([
            'master.is_local' => true,
            'master.tenant_base_domain' => 'main.guaranteeadmit.com',
            'master.tenant_crm_port' => '8000',
        ]);

        $this->assertSame(
            'http://inrationeutseddo.main.guaranteeadmit.com',
            TenantUrl::urlForHost('inrationeutseddo.main.guaranteeadmit.com/')
        );
    }

    #[Test]
    public function normalize_host_strips_embedded_port(): void
    {
        $this->assertSame(
            'crm.example.com',
            TenantUrl::normalizeHost('crm.example.com:8000')
        );
    }
}
