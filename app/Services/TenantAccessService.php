<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Validates company + subscription from master DB before CRM may connect to tenant database.
 */
class TenantAccessService
{
    /**
     * @return array{allowed: true}|array{allowed: false, message: string, http_status: int, code: string, company_status: string, subscription_status: string|null}
     */
    public function evaluate(Tenant $tenant): array
    {
        $tenant->loadMissing('subscriptionPlan');

        $companyStatus = (string) $tenant->status;
        $subscriptionStatus = $tenant->subscription_status !== null
            ? (string) $tenant->subscription_status
            : null;

        if ($companyStatus === 'suspended') {
            return $this->deny(
                'This company account is suspended.',
                403,
                'company_suspended',
                $companyStatus,
                $subscriptionStatus,
            );
        }

        $provisioner = app(\App\Services\TenantProvisionerService::class);
        if (in_array($companyStatus, ['failed', 'provisioning'], true)) {
            $provisioner->syncSchemaReadyProvisionState($tenant->fresh());
            $tenant->refresh();
            $companyStatus = (string) $tenant->status;
        }

        if ($companyStatus !== 'active') {
            return $this->deny(
                $this->messageForCompanyStatus($companyStatus),
                503,
                'company_not_active',
                $companyStatus,
                $subscriptionStatus,
            );
        }

        if ($subscriptionStatus === 'suspended') {
            return $this->deny(
                'This company subscription is suspended.',
                403,
                'subscription_suspended',
                $companyStatus,
                $subscriptionStatus,
            );
        }

        if (in_array($subscriptionStatus, ['expired', 'cancelled'], true)) {
            return $this->deny(
                'Subscription is '.$subscriptionStatus.'. Please renew your plan in the master portal.',
                403,
                'subscription_'.$subscriptionStatus,
                $companyStatus,
                $subscriptionStatus,
            );
        }

        $allowedSubscription = config('master.crm_allowed_subscription_statuses', ['active', 'trial']);

        if ($subscriptionStatus === null || $subscriptionStatus === '' || ! in_array($subscriptionStatus, $allowedSubscription, true)) {
            return $this->deny(
                'Subscription is not active. Current status: '.($subscriptionStatus ?: 'not set').'.',
                403,
                'subscription_not_active',
                $companyStatus,
                $subscriptionStatus,
            );
        }

        if ($tenant->subscription_expires_at !== null && $tenant->subscription_expires_at->isPast()) {
            return $this->deny(
                'Subscription plan expired on '.$tenant->subscription_expires_at->format('d M Y').'.',
                403,
                'subscription_expired',
                $companyStatus,
                $subscriptionStatus,
            );
        }

        if ($tenant->subscription_plan_id !== null) {
            $plan = $tenant->subscriptionPlan;

            if ($plan === null) {
                return $this->deny(
                    'Subscription plan is missing or was removed.',
                    403,
                    'plan_not_found',
                    $companyStatus,
                    $subscriptionStatus,
                );
            }

            if (! $plan->is_active) {
                return $this->deny(
                    'Subscription plan "'.$plan->name.'" is no longer active.',
                    403,
                    'plan_inactive',
                    $companyStatus,
                    $subscriptionStatus,
                );
            }
        }

        if (! $tenant->isDatabaseProvisioned() && ! $provisioner->isProvisionWorkflowComplete($tenant)) {
            return $this->deny(
                'Company database is not provisioned yet.',
                503,
                'database_not_provisioned',
                $companyStatus,
                $subscriptionStatus,
            );
        }

        if (in_array($companyStatus, ['failed', 'provisioning'], true)
            && $provisioner->isProvisionWorkflowComplete($tenant)) {
            $provisioner->syncCompletedProvisionState($tenant->fresh());
        }

        return ['allowed' => true];
    }

    protected function messageForCompanyStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Company is pending approval.',
            'provisioning' => 'Company is still being provisioned.',
            'failed' => 'Company provisioning failed.',
            'rejected' => 'Company registration was rejected.',
            default => 'Company is not available (status: '.$status.').',
        };
    }

    /**
     * @return array{allowed: false, message: string, http_status: int, code: string, company_status: string, subscription_status: string|null}
     */
    protected function deny(
        string $message,
        int $httpStatus,
        string $code,
        string $companyStatus,
        ?string $subscriptionStatus,
    ): array {
        return [
            'allowed' => false,
            'message' => $message,
            'http_status' => $httpStatus,
            'code' => $code,
            'company_status' => $companyStatus,
            'subscription_status' => $subscriptionStatus,
        ];
    }
}
