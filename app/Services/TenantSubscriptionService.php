<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TenantSubscriptionService
{
    public function planHasNoExpiry(?SubscriptionPlan $plan): bool
    {
        if ($plan === null) {
            return true;
        }

        if ($plan->interval === 'none') {
            return true;
        }

        $freeSlugs = config('master.subscription_free_plan_slugs', ['free']);

        return in_array($plan->slug, $freeSlugs, true);
    }

    /**
     * Expiry date (end of day) from last billing + plan interval, or null when plan never expires.
     */
    public function expiresAtFromBilling(SubscriptionPlan $plan, Carbon $billedAt): ?Carbon
    {
        if ($this->planHasNoExpiry($plan)) {
            return null;
        }

        $from = $billedAt->copy()->startOfDay();

        return match ($plan->interval) {
            'yearly' => $from->addYear()->endOfDay(),
            default => $from->addMonth()->endOfDay(),
        };
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon} expires_at, billed_at
     */
    public function resolveForManageSave(
        Tenant $tenant,
        ?SubscriptionPlan $plan,
        ?Carbon $billedAtInput,
        ?Carbon $manualExpiresInput,
        bool $renewBilling,
    ): array {
        if ($plan === null) {
            return [null, null];
        }

        if ($this->planHasNoExpiry($plan)) {
            return [null, null];
        }

        $planChanged = (int) $tenant->subscription_plan_id !== (int) $plan->id;

        if ($renewBilling || $planChanged || $billedAtInput !== null) {
            $billedAt = ($billedAtInput ?? now())->copy()->startOfDay();
        } else {
            $billedAt = $tenant->subscription_billed_at?->copy()->startOfDay() ?? now()->startOfDay();
        }

        if ($manualExpiresInput !== null && ! $renewBilling && ! $planChanged && $billedAtInput === null) {
            return [$manualExpiresInput->copy()->endOfDay(), $billedAt];
        }

        return [$this->expiresAtFromBilling($plan, $billedAt), $billedAt];
    }

    /**
     * @return array<string, mixed>
     */
    public function planBillingMeta(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'slug' => $plan->slug,
            'name' => $plan->name,
            'interval' => $plan->interval,
            'no_expiry' => $this->planHasNoExpiry($plan),
            'interval_label' => $this->intervalLabel($plan),
        ];
    }

    public function intervalLabel(SubscriptionPlan $plan): string
    {
        return match ($plan->interval) {
            'yearly' => 'year',
            'none' => 'no billing',
            default => 'month',
        };
    }

    public function expiryLabelForTenant(Tenant $tenant): string
    {
        $plan = $tenant->subscriptionPlan;

        if ($plan && $this->planHasNoExpiry($plan)) {
            return 'No expiry (free plan)';
        }

        if ($tenant->subscription_expires_at === null) {
            return 'Not set';
        }

        $expires = $tenant->subscription_expires_at->format('d M Y');
        if ($tenant->subscription_billed_at) {
            return $expires.' (billed '.$tenant->subscription_billed_at->format('d M Y').')';
        }

        return $expires;
    }
}
