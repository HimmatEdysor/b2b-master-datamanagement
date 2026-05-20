@extends('layouts.website')
@section('title', 'Ticket '.$ticket->ticket_number)

@section('content')
<section class="section">
    <div class="container" style="max-width:720px">
        <header style="margin-bottom:20px">
            <p class="text-muted"><code>{{ $ticket->ticket_number }}</code> · {{ ucfirst($ticket->status) }}</p>
            <h1>{{ $ticket->subject }}</h1>
        </header>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div id="ticket-messages" class="ticket-thread" data-ticket-id="{{ $ticket->id }}">
            @foreach($ticket->messages as $msg)
                @include('partials.ticket-message', ['msg' => $msg])
            @endforeach
        </div>

        <form method="POST" action="{{ route('support.reply', $ticket->ticket_number) }}" class="register-form" style="margin-top:24px">
            @csrf
            <div class="form-group">
                <label for="body">Your reply</label>
                <textarea id="body" name="body" rows="4" required @if($ticket->status === 'closed') disabled @endif></textarea>
            </div>
            @if($ticket->status !== 'closed')
                <button type="submit" class="btn btn-primary">Send reply</button>
            @else
                <p class="text-muted">This ticket is closed.</p>
            @endif
        </form>
    </div>
</section>
@endsection

@push('scripts')
@include('partials.support-ticket-echo')
@endpush
