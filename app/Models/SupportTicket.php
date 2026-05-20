<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_number',
        'guest_name',
        'guest_email',
        'guest_phone',
        'company_name',
        'tenant_id',
        'subject',
        'message',
        'status',
        'priority',
        'assigned_to',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->orderBy('created_at');
    }

    public static function generateTicketNumber(): string
    {
        do {
            $number = 'TKT-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } while (static::where('ticket_number', $number)->exists());

        return $number;
    }
}
