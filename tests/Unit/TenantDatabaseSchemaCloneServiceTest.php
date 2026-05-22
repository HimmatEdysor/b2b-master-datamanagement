<?php

namespace Tests\Unit;

use App\Support\TenantDbAdmin;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantDatabaseSchemaCloneServiceTest extends TestCase
{
    #[Test]
    public function default_clone_method_is_pdo(): void
    {
        config(['master.tenant_db_clone_method' => null]);

        $this->assertSame('pdo', TenantDbAdmin::cloneMethod());
    }

    #[Test]
    public function invalid_clone_method_falls_back_to_pdo(): void
    {
        config(['master.tenant_db_clone_method' => 'invalid']);

        $this->assertSame('pdo', TenantDbAdmin::cloneMethod());
    }

    #[Test]
    public function quote_identifier_escapes_backticks(): void
    {
        $this->assertSame('`foo`', TenantDbAdmin::quoteIdentifier('foo'));
        $this->assertSame('`a``b`', TenantDbAdmin::quoteIdentifier('a`b'));
    }
}
