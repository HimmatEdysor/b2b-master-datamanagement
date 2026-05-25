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
        $service = $this->app->make(TenantDatabaseUserService::class);

        $this->assertSame('b2b_tenant_acme', $service->deriveUsername('b2b_tenant_acme'));
    }

    #[Test]
    public function derive_username_truncates_long_database_names(): void
    {
        $service = $this->app->make(TenantDatabaseUserService::class);
        $long = 'b2b_tenant_very_long_company_slug_here';

        $this->assertSame(32, strlen($service->deriveUsername($long)));
        $this->assertSame(substr($long, 0, 32), $service->deriveUsername($long));
    }

    #[Test]
    public function build_provision_sql_creates_user_grants_and_flush(): void
    {
        $service = $this->app->make(TenantDatabaseUserService::class);
        config(['master.tenant_db_user_hosts' => ['%', 'localhost']]);

        $statements = $service->buildProvisionSql('b2b_tenant_x', "pa'ss\\word", 'b2b_tenant_x');
        $sql = $service->statementsToSqlBatch($statements);

        $this->assertStringContainsString("CREATE USER 'b2b_tenant_x'@'%' IDENTIFIED BY 'pa''ss\\\\word'", $sql);
        $this->assertStringContainsString('GRANT SELECT, INSERT, UPDATE, DELETE', $sql);
        $this->assertStringContainsString("ON `b2b_tenant_x`.* TO 'b2b_tenant_x'@'localhost'", $sql);
        $this->assertStringNotContainsString('GRANT ALL PRIVILEGES', $sql);
        $this->assertStringContainsString("CREATE USER 'b2b_tenant_x'@'localhost'", $sql);
        $this->assertStringEndsWith(';', $sql);
        $this->assertMatchesRegularExpression('/CREATE USER[^;]+;\s*\nGRANT/', $sql);
    }

    #[Test]
    public function build_password_update_sql_alters_user_and_flush(): void
    {
        $service = $this->app->make(TenantDatabaseUserService::class);
        config(['master.tenant_db_user_hosts' => ['%']]);

        $statements = $service->buildPasswordUpdateSql('b2b_tenant_x', 'new-secret');
        $sql = $service->statementsToSqlBatch($statements);

        $this->assertStringContainsString("ALTER USER 'b2b_tenant_x'@'%' IDENTIFIED BY 'new-secret'", $sql);
        $this->assertStringContainsString('FLUSH PRIVILEGES', $sql);
        $this->assertStringNotContainsString('CREATE USER', $sql);
    }
}
