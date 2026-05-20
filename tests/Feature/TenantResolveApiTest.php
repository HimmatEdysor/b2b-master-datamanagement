<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantResolveApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['master.crm_api_token' => 'test-token']);
    }

    public function test_resolve_requires_token(): void
    {
        $this->getJson('/api/v1/tenant/resolve?host=test.example.com')
            ->assertUnauthorized();
    }

    public function test_resolve_returns_tenant_config(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'slug' => 'testco',
            'status' => 'active',
            'database_name' => 'b2b_tenant_testco',
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'host' => 'testco.guaranteeadmit.com',
            'type' => 'subdomain',
            'is_primary' => true,
        ]);

        $response = $this->withToken('test-token')
            ->getJson('/api/v1/tenant/resolve?host=testco.guaranteeadmit.com');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'testco')
            ->assertJsonPath('data.database.database', 'b2b_tenant_testco')
            ->assertJsonPath('data.storage.s3_folder', 'testco');
    }
}
