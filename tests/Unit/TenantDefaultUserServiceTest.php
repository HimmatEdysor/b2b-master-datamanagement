<?php

namespace Tests\Unit;

use App\Services\TenantDefaultUserService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantDefaultUserServiceTest extends TestCase
{
    #[Test]
    public function build_user_row_skips_columns_not_on_tenant_users_table(): void
    {
        $service = new class extends TenantDefaultUserService
        {
            protected function usersTableColumns(string $database): array
            {
                return [
                    'id', 'name', 'email', 'password', 'phone_no', 'permission_ids',
                    'is_active', 'type', 'email_verified_at', 'created_at', 'updated_at',
                ];
            }
        };

        $method = new \ReflectionMethod($service, 'buildUserRowForDatabase');
        $method->setAccessible(true);

        $row = $method->invoke($service, 'b2b_tenant_test', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'hashed',
            'roles_ids' => '1,2',
            'permission_ids' => '0',
            'is_active' => 1,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        $this->assertArrayNotHasKey('roles_ids', $row);
        $this->assertSame('admin@example.com', $row['email']);
        $this->assertSame('0', $row['permission_ids']);
    }
}
