<?php

namespace Tests\Unit;

use App\Models\SubscriptionPlan;
use App\Services\TenantSubscriptionService;
use Carbon\Carbon;
use Tests\TestCase;

class TenantSubscriptionServiceTest extends TestCase
{
    protected TenantSubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantSubscriptionService::class);
    }

    public function test_free_plan_has_no_expiry(): void
    {
        $plan = new SubscriptionPlan([
            'slug' => 'free',
            'interval' => 'none',
            'price' => 0,
        ]);

        $this->assertTrue($this->service->planHasNoExpiry($plan));
        $this->assertNull($this->service->expiresAtFromBilling($plan, Carbon::parse('2026-01-15')));
    }

    public function test_monthly_plan_expires_one_month_after_billing(): void
    {
        $plan = new SubscriptionPlan([
            'slug' => 'growth',
            'interval' => 'monthly',
            'price' => 4999,
        ]);

        $expires = $this->service->expiresAtFromBilling($plan, Carbon::parse('2026-01-15'));

        $this->assertSame('2026-02-15', $expires?->format('Y-m-d'));
    }

    public function test_yearly_plan_expires_one_year_after_billing(): void
    {
        $plan = new SubscriptionPlan([
            'slug' => 'enterprise',
            'interval' => 'yearly',
            'price' => 14999,
        ]);

        $expires = $this->service->expiresAtFromBilling($plan, Carbon::parse('2026-01-15'));

        $this->assertSame('2027-01-15', $expires?->format('Y-m-d'));
    }
}
