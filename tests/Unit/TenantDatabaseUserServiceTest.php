<?php

namespace Tests\Unit;

use App\Services\TenantDatabaseUserService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantDatabaseUserServiceTest extends TestCase
{
    #[Test]
    public function derive_username_matches_database_name_when_short_enough(): void
    {
        $service = new TenantDatabaseUserService;

        $this->assertSame('b2b_tenant_acme', $service->deriveUsername('b2b_tenant_acme'));
    }

    #[Test]
    public function derive_username_truncates_long_database_names(): void
    {
        $service = new TenantDatabaseUserService;
        $long = 'b2b_tenant_very_long_company_slug_here';

        $this->assertSame(32, strlen($service->deriveUsername($long)));
        $this->assertSame(substr($long, 0, 32), $service->deriveUsername($long));
    }

    #[Test]
    public function build_provision_sql_creates_user_grants_and_flush(): void
    {
        $service = new TenantDatabaseUserService;
        config(['master.tenant_db_user_hosts' => ['%', 'localhost']]);

        $sql = implode("\n", $service->buildProvisionSql('b2b_tenant_x', "pa'ss\\word", 'b2b_tenant_x'));

        $this->assertStringContainsString("IDENTIFIED BY 'pa''ss\\\\word'", $sql);
        $this->assertStringContainsString("GRANT ALL PRIVILEGES ON `b2b_tenant_x`.* TO 'b2b_tenant_x'@'localhost'", $sql);
        $this->assertStringContainsString('FLUSH PRIVILEGES', $sql);
    }
}
