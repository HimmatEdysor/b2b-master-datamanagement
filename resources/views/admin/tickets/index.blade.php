@extends('layouts.admin')
@section('title', 'Support tickets')
@section('page-title', 'Support tickets')

@section('content')
<div class="page-toolbar">
    <p class="page-lead">Queries submitted from the website support form.</p>
    <a href="{{ route('support.create') }}" class="btn btn-outline" target="_blank">Public form</a>
</div>

<form method="GET" class="filter-bar" style="margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search ticket, name, email…" class="form-control" style="min-width:220px">
    <select name="status" onchange="this.form.submit()">
        <option value="">All statuses</option>
        @foreach(['open','answered','closed'] as $s)
            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Filter</button>
</form>

<div class="card table-card">
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ticket</th>
                    <th>Contact</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($tickets as $ticket)
                <tr>
                    <td><code>{{ $ticket->ticket_number }}</code></td>
                    <td>
                        <span class="cell-primary">{{ $ticket->guest_name }}</span>
                        <span class="cell-muted">{{ $ticket->guest_email }}</span>
                    </td>
                    <td>{{ Str::limit($ticket->subject, 50) }}</td>
                    <td><span class="badge badge-{{ $ticket->status === 'open' ? 'pending' : ($ticket->status === 'closed' ? 'draft' : 'active') }}">{{ ucfirst($ticket->status) }}</span></td>
                    <td>{{ $ticket->last_message_at?->diffForHumans() ?? $ticket->created_at->diffForHumans() }}</td>
                    <td><a href="{{ route('admin.tickets.show', $ticket) }}" class="btn btn-outline btn-sm">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="table-empty">No tickets yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $tickets->links() }}
@endsection
