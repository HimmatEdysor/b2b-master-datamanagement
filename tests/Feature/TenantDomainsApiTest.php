<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantDomainsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['master.crm_api_token' => 'test-token']);
    }

    public function test_list_domains_for_tenant(): void
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

        $this->withToken('test-token')
            ->getJson('/api/v1/tenants/testco/domains')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'testco')
            ->assertJsonCount(1, 'data.domains');
    }

    public function test_add_custom_domain(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'slug' => 'testco',
            'status' => 'active',
            'database_name' => 'b2b_tenant_testco',
        ]);

        $this->withToken('test-token')
            ->postJson('/api/v1/tenants/testco/domains', [
                'type' => 'custom',
                'host' => 'crm.testco.example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.domain.host', 'crm.testco.example.com');

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'host' => 'crm.testco.example.com',
            'type' => 'custom',
        ]);
    }

    public function test_add_subdomain_alias(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'slug' => 'testco',
            'status' => 'active',
            'database_name' => 'b2b_tenant_testco',
        ]);

        $this->withToken('test-token')
            ->postJson('/api/v1/tenants/testco/domains', [
                'type' => 'subdomain_alias',
                'alias' => 'sales',
            ])
            ->assertCreated()
            ->assertJsonPath('data.domain.host', 'sales.guaranteeadmit.com');
    }

    public function test_cannot_remove_canonical_subdomain(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'slug' => 'testco',
            'status' => 'active',
            'database_name' => 'b2b_tenant_testco',
        ]);

        $domain = TenantDomain::create([
            'tenant_id' => $tenant->id,
            'host' => 'testco.guaranteeadmit.com',
            'type' => 'subdomain',
            'is_primary' => true,
        ]);

        $this->withToken('test-token')
            ->deleteJson('/api/v1/tenants/testco/domains/'.$domain->id)
            ->assertStatus(422);
    }
}
