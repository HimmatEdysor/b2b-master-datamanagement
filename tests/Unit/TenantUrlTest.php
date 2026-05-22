<?php

namespace Tests\Unit;

use App\Support\TenantUrl;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantUrlTest extends TestCase
{
    protected function tearDown(): void
    {
        config([
            'master.is_local' => true,
            'master.tenant_base_domain' => 'localhost',
            'master.tenant_base_domain_production' => 'guaranteeadmit.com',
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
            'master.tenant_base_domain' => 'guaranteeadmit.com',
            'master.tenant_url_scheme' => 'https',
            'master.tenant_crm_port' => '8000',
        ]);

        $this->assertSame('', TenantUrl::portSuffix());
        $this->assertSame(
            'https://acme.guaranteeadmit.com',
            TenantUrl::urlForSlug('acme')
        );
    }

    #[Test]
    public function local_env_with_production_base_domain_omits_port(): void
    {
        config([
            'master.is_local' => true,
            'master.tenant_base_domain' => 'guaranteeadmit.com',
            'master.tenant_url_scheme' => 'http',
            'master.tenant_crm_port' => '8000',
        ]);

        $this->assertFalse(TenantUrl::usesPortInUrls());
        $this->assertSame(
            'http://inrationeutseddo.guaranteeadmit.com',
            TenantUrl::urlForSlug('inrationeutseddo')
        );
    }

    #[Test]
    public function trailing_slash_on_host_does_not_produce_slash_before_port(): void
    {
        config([
            'master.is_local' => true,
            'master.tenant_base_domain' => 'guaranteeadmit.com',
            'master.tenant_crm_port' => '8000',
        ]);

        $this->assertSame(
            'http://inrationeutseddo.guaranteeadmit.com',
            TenantUrl::urlForHost('inrationeutseddo.guaranteeadmit.com/')
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
