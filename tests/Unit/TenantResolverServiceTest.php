<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\TenantResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function apex_host_resolves_to_platform_default_tenant(): void
    {
        config([
            'master.tenant_base_domain' => 'main.guaranteeadmit.com',
            'master.tenant_base_domain_production' => 'main.guaranteeadmit.com',
            'master.platform_default_slug' => 'guaranteeadmit',
        ]);

        $default = Tenant::create([
            'name' => 'Guarantee Admit',
            'slug' => 'guaranteeadmit',
            'status' => 'active',
            'database_name' => 'b2b_crm',
        ]);
        Tenant::create([
            'name' => 'New Company',
            'slug' => 'newcompany',
            'status' => 'active',
            'database_name' => 'b2b_tenant_newcompany',
        ]);

        $resolver = app(TenantResolverService::class);

        $this->assertSame($default->id, $resolver->resolveByHost('main.guaranteeadmit.com')?->id);
        $this->assertSame($default->id, $resolver->resolveByHost('www.main.guaranteeadmit.com')?->id);
    }

    #[Test]
    public function company_slug_resolves_under_main_guaranteeadmit_base(): void
    {
        config([
            'master.tenant_base_domain' => 'main.guaranteeadmit.com',
            'master.tenant_base_domain_production' => 'main.guaranteeadmit.com',
            'master.platform_default_slug' => 'guaranteeadmit',
        ]);

        Tenant::create([
            'name' => 'Guarantee Admit',
            'slug' => 'guaranteeadmit',
            'status' => 'active',
            'database_name' => 'b2b_crm',
        ]);
        $partner = Tenant::create([
            'name' => 'New Company',
            'slug' => 'newcompany',
            'status' => 'active',
            'database_name' => 'b2b_tenant_newcompany',
        ]);

        $resolver = app(TenantResolverService::class);

        $this->assertSame($partner->id, $resolver->resolveByHost('newcompany.main.guaranteeadmit.com')?->id);
        $this->assertNull($resolver->resolveByHost('unknown.main.guaranteeadmit.com'));
    }

    #[Test]
    public function reserved_main_label_is_not_a_company_slug_on_legacy_base(): void
    {
        config([
            'master.tenant_base_domain' => 'guaranteeadmit.com',
            'master.tenant_base_domain_production' => 'main.guaranteeadmit.com',
            'master.platform_default_slug' => 'guaranteeadmit',
            'master.reserved_tenant_slugs' => ['main', 'www', 'master'],
        ]);

        $default = Tenant::create([
            'name' => 'Guarantee Admit',
            'slug' => 'guaranteeadmit',
            'status' => 'active',
            'database_name' => 'b2b_crm',
        ]);

        $resolver = app(TenantResolverService::class);

        $this->assertSame($default->id, $resolver->resolveByHost('main.guaranteeadmit.com')?->id);
        $this->assertNull(TenantDomain::query()->where('host', 'main.guaranteeadmit.com')->first());
    }
}
