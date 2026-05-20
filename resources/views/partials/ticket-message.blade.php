<div class="ticket-message {{ $msg->is_staff ? 'ticket-message-staff' : 'ticket-message-guest' }}" data-message-id="{{ $msg->id }}">
    <div class="ticket-message-meta">
        <strong>{{ $msg->is_staff ? ($msg->user?->name ?? 'Support team') : ($msg->sender_name ?? 'You') }}</strong>
        <span>{{ $msg->created_at?->format('M j, Y g:i A') }}</span>
    </div>
    <div class="ticket-message-body">{!! nl2br(e($msg->body)) !!}</div>
</div>
