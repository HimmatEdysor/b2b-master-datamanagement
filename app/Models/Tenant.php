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

    public function databaseHost(): string
    {
        return $this->database_host ?: (string) config('master.tenant_db_host');
    }

    public function databaseUsername(): string
    {
        return $this->database_username ?: (string) config('master.tenant_db_username');
    }

    public function databasePassword(): string
    {
        return $this->database_password ?: (string) config('master.tenant_db_password');
    }
}
