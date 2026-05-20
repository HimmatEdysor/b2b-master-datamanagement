<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = SupportTicket::query()
            ->with('assignee')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('ticket_number', 'like', $term)
                        ->orWhere('guest_name', 'like', $term)
                        ->orWhere('guest_email', 'like', $term)
                        ->orWhere('subject', 'like', $term);
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.tickets.index', compact('tickets'));
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load(['messages.user', 'tenant', 'assignee']);
        $staffUsers = User::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.tickets.show', compact('ticket', 'staffUsers'));
    }

    public function reply(Request $request, SupportTicket $ticket, SupportTicketService $service): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $service->addMessage($ticket, $validated['body'], true, Auth::user());

        return back()->with('success', 'Reply sent.');
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:open,answered,closed'],
            'priority' => ['required', 'in:low,normal,high'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket->update($validated);

        return back()->with('success', 'Ticket updated.');
    }
}
