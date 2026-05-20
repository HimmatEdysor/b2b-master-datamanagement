@extends('layouts.website')
@section('title', 'View ticket')

@section('content')
<section class="section">
    <div class="container" style="max-width:480px">
        <h1>View ticket {{ $ticket->ticket_number }}</h1>
        <p>Enter the email address used when you created this ticket.</p>
        <form method="GET" action="{{ route('support.show', $ticket->ticket_number) }}" class="register-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary">Continue</button>
        </form>
    </div>
</section>
@endsection
