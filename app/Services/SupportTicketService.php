<?php

namespace App\Services;

use App\Events\SupportTicketMessageSent;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Support\SafeBroadcast;

class SupportTicketService
{
    public function addMessage(
        SupportTicket $ticket,
        string $body,
        bool $isStaff,
        ?User $user = null,
        ?string $senderName = null,
    ): SupportTicketMessage {
        $message = $ticket->messages()->create([
            'user_id' => $user?->id,
            'sender_name' => $senderName ?? $user?->name,
            'body' => $body,
            'is_staff' => $isStaff,
        ]);

        $ticket->update([
            'last_message_at' => now(),
            'status' => $isStaff && $ticket->status === 'open' ? 'answered' : $ticket->status,
        ]);

        SafeBroadcast::dispatch(new SupportTicketMessageSent($message));

        return $message;
    }
}
