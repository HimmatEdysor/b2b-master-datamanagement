<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'contact_designation',
        'registration_notes',
        'company_website',
        'address_line',
        'city',
        'state',
        'country',
        'business_type',
        'slug',
        'status',
        'database_name',
        'database_host',
        'database_port',
        'database_username',
        'database_password',
        'crm_admin_email',
        'crm_admin_password',
        's3_folder',
        'brand_name',
        'logo_url',
        'favicon_url',
        'primary_color',
        'support_email',
        'subscription_plan_id',
        'subscription_status',
        'subscription_billed_at',
        'subscription_expires_at',
        'last_migration_at',
        'migration_status',
        'migration_error',
        'provision_error',
        'provisioning_stage',
        'approved_at',
        'rejected_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'database_password' => 'encrypted',
            'crm_admin_password' => 'encrypted',
            'subscription_billed_at' => 'datetime',
            'subscription_expires_at' => 'datetime',
            'last_migration_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function operationLogs(): HasMany
    {
        return $this->hasMany(TenantOperationLog::class);
    }

    public function primaryDomain(): ?TenantDomain
    {
        return $this->domains()->where('is_primary', true)->first()
            ?? $this->domains()->first();
    }

    /**
     * CRM / migrate use only values stored on this tenant row (never .env fallbacks).
     */
    public function crmAdminEmail(): string
    {
        $email = trim((string) ($this->crm_admin_email ?? ''));

        if ($email !== '') {
            return strtolower($email);
        }

        return strtolower(trim((string) config('master.tenant_default_user.email', '')));
    }

    public function hasCrmAdminPassword(): bool
    {
        $password = $this->crm_admin_password;

        return $password !== null && $password !== '';
    }

    public function hasDatabasePassword(): bool
    {
        $password = $this->database_password;

        return $password !== null && $password !== '';
    }

    public function hasDatabaseUsername(): bool
    {
        $username = $this->database_username;

        return $username !== null && $username !== '';
    }

    public function isDatabaseProvisioned(): bool
    {
        if ($this->database_name === null || $this->database_name === '') {
            return false;
        }

        if (\App\Support\TenantDbAdmin::usesSharedTenantCredentials()) {
            return $this->database_host !== null
                && $this->database_host !== ''
                && \App\Support\TenantDbAdmin::username() !== ''
                && \App\Support\TenantDbAdmin::password() !== '';
        }

        return $this->database_host !== null
            && $this->database_host !== ''
            && $this->hasDatabaseUsername()
            && $this->hasDatabasePassword();
    }

    /** MySQL user exists on tenant row but password missing (re-run “Create DB user”). */
    public function hasPartialDatabaseCredentials(): bool
    {
        return $this->hasDatabaseUsername() && ! $this->hasDatabasePassword();
    }

    /**
     * @return array{host: string, port: int, database: string, username: string, password: string}
     */
    public function connectionConfig(): array
    {
        if (\App\Support\TenantDbAdmin::usesSharedTenantCredentials()) {
            if (! $this->isDatabaseProvisioned()) {
                throw new \RuntimeException(
                    "Company [{$this->slug}] database is not ready. Approve provisioning (uses shared MySQL user "
                    .\App\Support\TenantDbAdmin::username().').'
                );
            }

            return \App\Support\TenantDbAdmin::tenantConnectionConfig($this);
        }

        if (! $this->isDatabaseProvisioned()) {
            throw new \RuntimeException(
                "Company [{$this->slug}] has no complete database connection stored. Approve provisioning or set host, username, and password on the company record."
            );
        }

        return [
            'host' => (string) $this->database_host,
            'port' => (int) ($this->database_port ?: 3306),
            'database' => (string) $this->database_name,
            'username' => (string) $this->database_username,
            'password' => (string) $this->database_password,
        ];
    }

    public function s3Folder(): string
    {
        $folder = trim((string) ($this->s3_folder ?? ''), '/');

        return $folder !== '' ? $folder : (string) $this->slug;
    }
}
