<?php

namespace Tests\Unit;

use App\Services\CloudflareDnsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareDnsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_creates_a_record_in_cloudflare_zone(): void
    {
        config([
            'master.cloudflare_api_token' => 'test-token',
            'master.dns_cloudflare_zone_id' => 'zone123',
            'master.dns_cloudflare_base_domain' => 'guaranteeadmit.com',
            'master.dns_cloudflare_proxied' => false,
        ]);

        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(['success' => true, 'result' => []], 200);
            }

            return Http::response(['success' => true, 'result' => ['id' => 'rec1']], 200);
        });

        $ok = app(CloudflareDnsService::class)->upsertARecord('acme.guaranteeadmit.com', '203.0.113.10');

        $this->assertTrue($ok);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/dns_records')
                && $request['type'] === 'A'
                && $request['content'] === '203.0.113.10'
                && $request['name'] === 'acme';
        });
    }

    public function test_record_name_for_api_uses_relative_subdomain(): void
    {
        config(['master.dns_cloudflare_base_domain' => 'guaranteeadmit.com']);

        $service = app(CloudflareDnsService::class);

        $this->assertSame('apple', $service->recordNameForApi('apple.guaranteeadmit.com'));
        $this->assertSame('guaranteeadmit.com', $service->recordNameForApi('guaranteeadmit.com'));
    }

    public function test_skips_host_outside_zone(): void
    {
        config([
            'master.cloudflare_api_token' => 'test-token',
            'master.dns_cloudflare_zone_id' => 'zone123',
            'master.dns_cloudflare_base_domain' => 'guaranteeadmit.com',
        ]);

        Http::fake();

        $ok = app(CloudflareDnsService::class)->upsertARecord('crm.other.com', '1.2.3.4');

        $this->assertFalse($ok);
        Http::assertNothingSent();
    }
}
