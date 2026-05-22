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
        's3_folder',
        'brand_name',
        'logo_url',
        'favicon_url',
        'primary_color',
        'support_email',
        'subscription_plan_id',
        'subscription_status',
        'subscription_expires_at',
        'last_migration_at',
        'migration_status',
        'migration_error',
        'provision_error',
        'approved_at',
        'rejected_at',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'database_password' => 'encrypted',
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
    public function isDatabaseProvisioned(): bool
    {
        return $this->database_name !== null
            && $this->database_name !== ''
            && $this->database_host !== null
            && $this->database_host !== ''
            && $this->database_username !== null
            && $this->database_username !== ''
            && $this->database_password !== null
            && $this->database_password !== '';
    }

    /**
     * @return array{host: string, port: int, database: string, username: string, password: string}
     */
    public function connectionConfig(): array
    {
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
