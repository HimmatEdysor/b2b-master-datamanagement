<?php

namespace App\Events;

use App\Models\SupportTicketMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTicketMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SupportTicketMessage $message)
    {
        $this->message->loadMissing('user');
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support-ticket.'.$this->message->support_ticket_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'support_ticket_id' => $this->message->support_ticket_id,
            'body' => $this->message->body,
            'is_staff' => $this->message->is_staff,
            'sender_name' => $this->message->sender_name ?? $this->message->user?->name,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
