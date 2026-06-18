<?php

use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Support\MasterAuth;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant-provision.{tenantId}', function ($user, int $tenantId) {
    if (! $user || ! MasterAuth::can('tenants.view')) {
        return false;
    }

    return Tenant::query()->whereKey($tenantId)->exists()
        ? ['id' => $user->id, 'name' => $user->name]
        : false;
});

Broadcast::channel('support-ticket.{ticketId}', function ($user, int $ticketId) {
    if ($user && MasterAuth::can('tickets.view')) {
        return ['id' => $user->id, 'name' => $user->name];
    }

    $ticket = SupportTicket::query()->find($ticketId);
    if (! $ticket) {
        return false;
    }

    if (
        session('guest_ticket_id') === $ticket->id
        && session('guest_ticket_email') === $ticket->guest_email
    ) {
        return ['id' => 'guest', 'name' => $ticket->guest_name];
    }

    return false;
});
