<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubdomainCheckLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'host',
        'tenant_id',
        'slug',
        'outcome',
        'http_status',
        'code',
        'message',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
