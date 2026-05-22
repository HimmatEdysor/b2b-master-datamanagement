<?php

namespace Tests\Unit;

use App\Services\TenantDbAdminCapabilityService;
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
}
