@extends('layouts.website')

@section('title', 'Registration received')

@section('content')
<section class="section" style="padding-top: 48px;">
    <div class="container">
        <div class="form-card" style="text-align: center;">
            <div class="alert alert-success">
                Thank you
                @if(session('company'))
                    , <strong>{{ session('company') }}</strong>
                @endif
                ! Your registration has been received.
            </div>
            <p style="color: var(--muted); margin-bottom: 24px;">
                Our team will review your application and provision your CRM tenant. You will receive an email once your account is approved.
            </p>
            <a href="{{ route('home') }}" class="btn btn-primary">Back to home</a>
        </div>
    </div>
</section>
@endsection
