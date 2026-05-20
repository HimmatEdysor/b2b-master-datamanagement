<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function create(): View
    {
        return view('website.support.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_email' => ['required', 'email', 'max:190'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:190'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = SupportTicket::create([
            ...$validated,
            'ticket_number' => SupportTicket::generateTicketNumber(),
            'status' => 'open',
            'priority' => 'normal',
            'last_message_at' => now(),
        ]);

        $ticket->messages()->create([
            'sender_name' => $validated['guest_name'],
            'body' => $validated['message'],
            'is_staff' => false,
        ]);

        session([
            'guest_ticket_id' => $ticket->id,
            'guest_ticket_email' => $ticket->guest_email,
        ]);

        return redirect()
            ->route('support.show', $ticket->ticket_number)
            ->with('success', 'Your ticket has been submitted. Our team will reply shortly.');
    }

    public function show(Request $request, string $ticketNumber, SupportTicketService $tickets): View|RedirectResponse
    {
        $ticket = SupportTicket::query()
            ->where('ticket_number', $ticketNumber)
            ->with(['messages.user'])
            ->firstOrFail();

        if ($request->filled('email')) {
            if (strtolower($request->string('email')->toString()) !== strtolower($ticket->guest_email)) {
                abort(403, 'Email does not match this ticket.');
            }
            session([
                'guest_ticket_id' => $ticket->id,
                'guest_ticket_email' => $ticket->guest_email,
            ]);
        } elseif (
            session('guest_ticket_id') !== $ticket->id
            || session('guest_ticket_email') !== $ticket->guest_email
        ) {
            return view('website.support.verify', compact('ticket'));
        }

        return view('website.support.show', compact('ticket'));
    }

    public function reply(Request $request, string $ticketNumber, SupportTicketService $tickets): RedirectResponse
    {
        $ticket = SupportTicket::query()->where('ticket_number', $ticketNumber)->firstOrFail();

        if (
            session('guest_ticket_id') !== $ticket->id
            || session('guest_ticket_email') !== $ticket->guest_email
        ) {
            abort(403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $tickets->addMessage(
            $ticket,
            $validated['body'],
            false,
            null,
            $ticket->guest_name,
        );

        $ticket->update(['status' => 'open']);

        return back()->with('success', 'Reply sent.');
    }
}
