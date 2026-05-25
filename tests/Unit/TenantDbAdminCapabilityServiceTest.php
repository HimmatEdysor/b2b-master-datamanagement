<?php

namespace Tests\Unit;

use App\Services\TenantDbAdminCapabilityService;
use App\Support\TenantDbAdmin;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantDbAdminCapabilityServiceTest extends TestCase
{
    #[Test]
    public function rds_setup_sql_includes_username_and_template(): void
    {
        config([
            'master.tenant_database_prefix' => 'b2b_tenant_',
            'master.template_database' => 'b2b_live_database',
        ]);

        $sql = app(TenantDbAdminCapabilityService::class)->rdsSetupSql('b2b_master', 'b2b_live_database');

        $this->assertStringContainsString("'b2b_master'@'%'", $sql);
        $this->assertStringContainsString('b2b_live_database', $sql);
        $this->assertStringContainsString('CREATE USER', $sql);
        $this->assertStringContainsString('GRANT CREATE', $sql);
        $this->assertStringContainsString('b2b_tenant_%', $sql);
    }

    #[Test]
    public function provision_check_database_name_matches_real_tenant_pattern(): void
    {
        config([
            'master.tenant_database_prefix' => 'b2b_tenant_',
            'master.tenant_provision_check_slug' => 'provisioncheck',
        ]);

        $this->assertSame('b2b_tenant_edysor', TenantDbAdmin::tenantDatabaseNameFromSlug('edysor'));
        $this->assertSame('b2b_tenant_provisioncheck', TenantDbAdmin::provisionCheckDatabaseName());
        $this->assertSame('b2b_tenant_%', TenantDbAdmin::tenantDatabaseGrantPattern());
    }

    #[Test]
    public function check_build_constant_is_set(): void
    {
        $this->assertStringContainsString('b2b-tenant', TenantDbAdminCapabilityService::CHECK_BUILD);
    }
}
