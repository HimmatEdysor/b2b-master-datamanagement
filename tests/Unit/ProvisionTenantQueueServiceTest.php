<?php

namespace Tests\Unit;

use App\Services\ProvisionTenantQueueService;
use Tests\TestCase;

class ProvisionTenantQueueServiceTest extends TestCase
{
    public function test_payload_matches_tenant_from_serialized_job(): void
    {
        $service = app(ProvisionTenantQueueService::class);
        $payload = 'O:27:"App\\Jobs\\ProvisionTenantJob":3:{s:8:"tenantId";i:15;s:5:"queue";s:12:"provisioning";}';

        $this->assertTrue($this->invokePayloadMatches($service, $payload, 15));
        $this->assertFalse($this->invokePayloadMatches($service, $payload, 16));
    }

    public function test_normalize_error_strips_jobs_db_namespace_mistake(): void
    {
        $service = app(ProvisionTenantQueueService::class);
        $message = $service->normalizeProvisionErrorMessage(
            'Error: Class "App\\Jobs\\DB" not found'
        );

        $this->assertStringContainsString('Horizon', $message);
        $this->assertStringNotContainsString('App\\Jobs\\DB', $message);
    }

    private function invokePayloadMatches(ProvisionTenantQueueService $service, string $payload, int $tenantId): bool
    {
        $method = new \ReflectionMethod($service, 'payloadMatchesTenant');

        return $method->invoke($service, $payload, $tenantId);
    }
}
