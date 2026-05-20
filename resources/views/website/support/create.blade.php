@extends('layouts.website')
@section('title', 'Contact support')

@section('content')
<section class="section">
    <div class="container" style="max-width:640px">
        <header style="margin-bottom:24px">
            <h1>Contact support</h1>
            <p>Submit a query about your company, subscription, or CRM setup. We will reply on this ticket thread.</p>
        </header>

        <form method="POST" action="{{ route('support.store') }}" class="register-form">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label for="guest_name">Your name <span class="required">*</span></label>
                    <input type="text" id="guest_name" name="guest_name" value="{{ old('guest_name') }}" required>
                    @error('guest_name')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label for="guest_email">Email <span class="required">*</span></label>
                    <input type="email" id="guest_email" name="guest_email" value="{{ old('guest_email') }}" required>
                    @error('guest_email')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label for="guest_phone">Phone (optional)</label>
                    <input type="text" id="guest_phone" name="guest_phone" value="{{ old('guest_phone') }}">
                </div>
                <div class="form-group form-group-full">
                    <label for="company_name">Company name (optional)</label>
                    <input type="text" id="company_name" name="company_name" value="{{ old('company_name') }}">
                </div>
                <div class="form-group form-group-full">
                    <label for="subject">Subject <span class="required">*</span></label>
                    <input type="text" id="subject" name="subject" value="{{ old('subject') }}" required>
                    @error('subject')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-group form-group-full">
                    <label for="message">Message <span class="required">*</span></label>
                    <textarea id="message" name="message" rows="6" required>{{ old('message') }}</textarea>
                    @error('message')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Submit ticket</button>
        </form>
    </div>
</section>
@endsection
