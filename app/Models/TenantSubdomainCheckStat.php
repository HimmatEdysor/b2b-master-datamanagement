<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantSubdomainCheckStat extends Model
{
    protected $fillable = [
        'host',
        'tenant_id',
        'slug',
        'check_count',
        'allowed_count',
        'denied_count',
        'not_found_count',
        'last_http_status',
        'last_outcome',
        'last_code',
        'last_message',
        'first_checked_at',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'check_count' => 'integer',
            'allowed_count' => 'integer',
            'denied_count' => 'integer',
            'not_found_count' => 'integer',
            'first_checked_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TenantSubdomainCheckLog::class, 'host', 'host');
    }
}
