<?php

namespace Tests\Unit;

use App\Support\TenantDbAdmin;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantDbAdminTest extends TestCase
{
    #[Test]
    public function remote_host_without_password_fails_validation(): void
    {
        config([
            'master.tenant_db_host' => 'b2bcrm.example.rds.amazonaws.com',
            'master.tenant_db_password' => '',
            'master.tenant_db_username' => 'root',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('requires a password');

        TenantDbAdmin::assertCanProvision();
    }

    #[Test]
    public function mysql_password_args_includes_password_flag_when_set(): void
    {
        config(['master.tenant_db_password' => 'secret']);

        $this->assertSame(['-psecret'], TenantDbAdmin::mysqlPasswordArgs());
    }

    #[Test]
    public function loopback_allows_empty_password(): void
    {
        config([
            'master.tenant_db_host' => '127.0.0.1',
            'master.tenant_db_password' => '',
        ]);

        TenantDbAdmin::assertCanProvision();
        $this->assertSame([], TenantDbAdmin::mysqlPasswordArgs());
    }

    #[Test]
    public function mysqldump_flags_are_rds_safe(): void
    {
        $flags = TenantDbAdmin::mysqldumpFlags(schemaOnly: true);

        $this->assertContains('--single-transaction', $flags);
        $this->assertContains('--skip-lock-tables', $flags);
        $this->assertContains('--no-tablespaces', $flags);
        $this->assertContains('--no-data', $flags);
    }
}
