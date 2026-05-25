<?php

namespace Tests\Unit;

use App\Support\TenantDbAdmin;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantDbAdminRdsGrantsTest extends TestCase
{
    #[Test]
    public function grant_on_database_sql_uses_specific_privileges_not_all(): void
    {
        $sql = TenantDbAdmin::buildGrantOnDatabaseSql(
            'b2b_tenant_acme',
            'b2b_master',
            '%',
            TenantDbAdmin::databaseProvisionerPrivileges()
        );

        $this->assertStringStartsWith('GRANT SELECT, INSERT', $sql);
        $this->assertStringContainsString('ON `b2b_tenant_acme`.* TO \'b2b_master\'@\'%\'', $sql);
        $this->assertStringNotContainsString('ALL PRIVILEGES', $sql);
    }

    #[Test]
    public function rds_setup_sql_from_capability_service_avoids_grant_all_on_global(): void
    {
        $sql = app(\App\Services\TenantDbAdminCapabilityService::class)
            ->rdsSetupSql('b2b_master', 'b2b_live_database');

        $this->assertStringNotContainsString('GRANT ALL PRIVILEGES ON *.*', $sql);
        $this->assertStringNotContainsString('GRANT OPTION', $sql);
        $this->assertStringContainsString('GRANT CREATE, DROP', $sql);
        $this->assertStringContainsString('b2b_tenant_%', $sql);
    }
}
