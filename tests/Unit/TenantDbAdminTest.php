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
    public function mysql_cli_env_includes_password_when_set(): void
    {
        config(['master.tenant_db_password' => 'secret']);

        $this->assertSame(['MYSQL_PWD' => 'secret'], TenantDbAdmin::mysqlCliEnv());
        $this->assertSame([], TenantDbAdmin::mysqlPasswordArgs());
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
    public function connection_error_message_explains_rds_credentials(): void
    {
        $e = new \PDOException("SQLSTATE[HY000] [1045] Access denied for user 'root'@'10.0.0.1' (using password: YES)");

        $msg = TenantDbAdmin::connectionErrorMessage($e);

        $this->assertStringContainsString('Cannot connect to tenant MySQL', $msg);
        $this->assertStringContainsString("user 'root'", $msg);
        $this->assertStringContainsString("'%'", $msg);
        $this->assertStringContainsString('tenant:db-admin-check', $msg);
    }

    #[Test]
    public function clone_method_accepts_mysqldump(): void
    {
        config(['master.tenant_db_clone_method' => 'mysqldump']);

        $this->assertSame('mysqldump', TenantDbAdmin::cloneMethod());
    }

    #[Test]
    public function mysqldump_flags_are_rds_safe(): void
    {
        $flags = TenantDbAdmin::mysqldumpFlags(schemaOnly: true);

        $this->assertContains('--skip-lock-tables', $flags);
        $this->assertContains('--single-transaction', $flags);
        $this->assertContains('--set-gtid-purged=OFF', $flags);
        $this->assertContains('--no-tablespaces', $flags);
        $this->assertContains('--no-data', $flags);
    }

    #[Test]
    public function strip_mysql_cli_noise_removes_password_warning(): void
    {
        $raw = "mysqldump: [Warning] Using a password on the command line interface can be insecure.\n"
            ."mysqldump: Couldn't execute 'FLUSH TABLES': Access denied (1227)";

        $clean = TenantDbAdmin::stripMysqlCliNoise($raw);

        $this->assertStringNotContainsString('password on the command line', $clean);
        $this->assertStringContainsString('FLUSH TABLES', $clean);
    }

    #[Test]
    public function normalize_mysqldump_error_maps_flush_tables_to_rds_hint(): void
    {
        $msg = TenantDbAdmin::normalizeMysqldumpError("FLUSH TABLES Access denied 1227");

        $this->assertStringContainsString('--skip-lock-tables', $msg);
        $this->assertStringContainsString('horizon:terminate', $msg);
    }
}
