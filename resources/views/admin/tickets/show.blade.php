@extends('layouts.admin')
@section('title', $ticket->ticket_number)
@section('page-title', 'Ticket '.$ticket->ticket_number)

@section('content')
<div class="page-toolbar">
    <a href="{{ route('admin.tickets.index') }}" class="btn btn-outline btn-sm">← All tickets</a>
</div>

<div class="detail-grid" style="display:grid;grid-template-columns:1fr 280px;gap:24px;margin-top:16px">
    <div>
        <h2 style="margin:0 0 8px">{{ $ticket->subject }}</h2>
        <p class="cell-muted">{{ $ticket->guest_name }} · {{ $ticket->guest_email }}
            @if($ticket->company_name) · {{ $ticket->company_name }}@endif
        </p>

        <div id="ticket-messages" class="ticket-thread" data-ticket-id="{{ $ticket->id }}" style="margin:20px 0">
            @foreach($ticket->messages as $msg)
                @include('partials.ticket-message', ['msg' => $msg])
            @endforeach
        </div>

        @if(master_can('tickets.reply') && $ticket->status !== 'closed')
            <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" class="card" style="padding:16px">
                @csrf
                <div class="form-group">
                    <label for="body">Staff reply</label>
                    <textarea id="body" name="body" rows="4" required class="form-control"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Send reply</button>
            </form>
        @endif
    </div>

    @if(master_can('tickets.manage'))
        <aside class="card" style="padding:16px">
            <h3 style="margin-top:0">Manage</h3>
            <form method="POST" action="{{ route('admin.tickets.update', $ticket) }}">
                @csrf
                @method('PATCH')
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        @foreach(['open','answered','closed'] as $s)
                            <option value="{{ $s }}" @selected($ticket->status === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" class="form-control">
                        @foreach(['low','normal','high'] as $p)
                            <option value="{{ $p }}" @selected($ticket->priority === $p)>{{ ucfirst($p) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Assigned to</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">— Unassigned —</option>
                        @foreach($staffUsers as $u)
                            <option value="{{ $u->id }}" @selected($ticket->assigned_to == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Save</button>
            </form>
        </aside>
    @endif
</div>
@endsection

@push('scripts')
@include('partials.support-ticket-echo')
@endpush
