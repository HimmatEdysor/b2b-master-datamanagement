<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    protected $fillable = [
        'tenant_id',
        'host',
        'type',
        'is_primary',
        'dns_verified_at',
        'dns_target_ip',
        'dns_link_source',
        'ssl_status',
        'ssl_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'dns_verified_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
