@extends('layouts.website')
@php use App\Support\TenantUrl; @endphp

@section('title', 'Register your company')

@section('content')
<section class="section register-section">
    <div class="container register-container">
        <header class="register-header">
            <h1>Register your company</h1>
            <p>Complete all sections below. Our team will review your application and provision your dedicated B2B CRM tenant.</p>
        </header>

        <div id="register-alert" class="register-alert alert alert-error" role="alert" hidden></div>

        <div id="register-success-panel" class="register-success-panel" hidden>
            <div class="alert alert-success">
                <strong>Thank you, <span data-success-company>your company</span>!</strong>
                <p data-success-message style="margin:10px 0 0">Your registration has been received.</p>
            </div>
            <p class="register-success-note">Our team will review your application and provision your CRM tenant. You will receive an email once your account is approved.</p>
            <a href="{{ route('home') }}" class="btn btn-primary">Back to home</a>
        </div>

        <form id="register-form"
              method="POST"
              action="{{ route('register.store') }}"
              class="register-form"
              enctype="multipart/form-data"
              novalidate
              data-plans-required="{{ $plans->isNotEmpty() ? '1' : '0' }}"
              data-base-domain="{{ TenantUrl::baseDomain() }}"
              data-tenant-scheme="{{ TenantUrl::scheme() }}"
              data-tenant-port-suffix="{{ TenantUrl::portSuffix() }}">
            @csrf

            {{-- Company --}}
            <fieldset class="form-section">
                <legend>Company information</legend>
                <div class="form-grid">
                    <div class="form-group form-group-full">
                        <label for="company_name">Legal / registered company name <span class="required">*</span></label>
                        <input type="text" id="company_name" name="company_name" value="{{ old('company_name') }}" autofocus placeholder="e.g. Edysor Education Pvt Ltd">
                        @error('company_name')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <label for="business_type">Business type <span class="required">*</span></label>
                        <select id="business_type" name="business_type">
                            <option value="">— Select —</option>
                            @foreach(['Education consultancy', 'Study abroad agency', 'Immigration consultant', 'Language institute', 'University / institution', 'Corporate / other'] as $type)
                                <option value="{{ $type }}" @selected(old('business_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('business_type')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <label for="company_website">Company website</label>
                        <input type="url" id="company_website" name="company_website" value="{{ old('company_website') }}" placeholder="https://example.com">
                        @error('company_website')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group form-group-full">
                        <label for="address_line">Office address <span class="required">*</span></label>
                        <input type="text" id="address_line" name="address_line" value="{{ old('address_line') }}" placeholder="Street, building, suite">
                        @error('address_line')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <label for="city">City <span class="required">*</span></label>
                        <input type="text" id="city" name="city" value="{{ old('city') }}">
                        @error('city')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <label for="state">State / province <span class="required">*</span></label>
                        <input type="text" id="state" name="state" value="{{ old('state') }}">
                        @error('state')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <label for="country">Country <span class="required">*</span></label>
                        <input type="text" id="country" name="country" value="{{ old('country', 'India') }}">
                        @error('country')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            {{-- CRM access --}}
            <fieldset class="form-section">
                <legend>CRM access & subdomain</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="slug">Subdomain slug <span class="required">*</span></label>
                        <input type="text" id="slug" name="slug" value="{{ old('slug') }}" placeholder="data" autocomplete="off" spellcheck="false">
                        <p class="form-hint">Lowercase letters, numbers, and hyphens only — no spaces (e.g. <code>data</code> → <code>{{ TenantUrl::urlForSlug('data') }}</code>).</p>
                        @error('slug')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <label>Your CRM URL</label>
                        <p class="form-readonly" id="slug-preview">{{ TenantUrl::urlForSlug(old('slug', 'your-slug')) }}</p>
                    </div>

                    <div class="form-group form-group-full">
                        <label for="custom_domain">Custom domain (optional)</label>
                        <input type="text" id="custom_domain" name="custom_domain" value="{{ old('custom_domain') }}" placeholder="crm.yourcompany.com">
                        <p class="form-hint">White-label domain — we will configure DNS after approval.</p>
                        @error('custom_domain')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    </div>
            </fieldset>

            {{-- Primary contact --}}
            <fieldset class="form-section">
                <legend>Primary contact person</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="contact_name">Full name <span class="required">*</span></label>
                        <input type="text" id="contact_name" name="contact_name" value="{{ old('contact_name') }}">
                        @error('contact_name')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <label for="contact_designation">Job title / designation <span class="required">*</span></label>
                        <input type="text" id="contact_designation" name="contact_designation" value="{{ old('contact_designation') }}" placeholder="e.g. Director, Operations Manager">
                        @error('contact_designation')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <label for="contact_email">Work email <span class="required">*</span></label>
                        <input type="email" id="contact_email" name="contact_email" value="{{ old('contact_email') }}">
                        @error('contact_email')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <label for="contact_phone">Mobile / phone <span class="required">*</span></label>
                        <input type="tel" id="contact_phone" name="contact_phone" value="{{ old('contact_phone') }}" placeholder="+91 98765 43210">
                        @error('contact_phone')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </fieldset>

            {{-- Branding --}}
            <fieldset class="form-section">
                <legend>Branding (optional)</legend>
                <p class="form-section-intro">How your CRM portal should appear to your team. You can change these later.</p>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="brand_name">CRM display name</label>
                        <input type="text" id="brand_name" name="brand_name" value="{{ old('brand_name') }}" placeholder="Same as company name if blank">
                        @error('brand_name')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group">
                        <label for="support_email">Support email shown in CRM</label>
                        <input type="email" id="support_email" name="support_email" value="{{ old('support_email') }}" placeholder="Defaults to contact email">
                        @error('support_email')<p class="form-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="form-group form-group-full">
                        @include('partials.logo-upload', ['errorClass' => 'form-error'])
                    </div>

                </div>
            </fieldset>

            {{-- Plan --}}
            @if($plans->isNotEmpty())
            <fieldset class="form-section">
                <legend>Subscription plan <span class="required">*</span></legend>
                <div class="plan-select-grid">
                    @foreach($plans as $plan)
                        <label class="plan-option {{ old('subscription_plan_id', $selectedPlanId ?? null) == $plan->id ? 'selected' : '' }}">
                            <input type="radio" name="subscription_plan_id" value="{{ $plan->id }}" @checked(old('subscription_plan_id', $selectedPlanId ?? null) == $plan->id)>
                            <span class="plan-option-name">{{ $plan->name }}</span>
                            <span class="plan-option-price">
                                @if($plan->price > 0)
                                    {{ $plan->currency }} {{ number_format($plan->price, 0) }}/{{ $plan->interval }}
                                @else
                                    Free
                                @endif
                            </span>
                            @if($plan->description)
                                <span class="plan-option-desc">{{ $plan->description }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
                @error('subscription_plan_id')<p class="form-error">{{ $message }}</p>@enderror
            </fieldset>
            @endif

            {{-- Additional --}}
            <fieldset class="form-section">
                <legend>Additional information</legend>
                <div class="form-group form-group-full">
                    <label for="notes">Notes for our team</label>
                    <textarea id="notes" name="notes" rows="4" placeholder="Expected number of users, go-live date, integrations needed, etc.">{{ old('notes') }}</textarea>
                    @error('notes')<p class="form-error">{{ $message }}</p>@enderror
                </div>

                <div class="form-group form-group-full">
                    <label class="checkbox-label">
                        <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                        I agree to the
                        @if(\App\Models\Page::query()->where('slug', 'terms')->published()->exists())
                            <a href="{{ route('page.show', 'terms') }}" target="_blank">Terms of service</a>
                        @else
                            terms of service
                        @endif
                        and confirm the information provided is accurate. <span class="required">*</span>
                    </label>
                    @error('terms')<p class="form-error">{{ $message }}</p>@enderror
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">Submit registration</button>
                <p class="form-hint" style="margin:12px 0 0;text-align:center">Review usually takes 1–2 business days. You will be notified at your work email.</p>
            </div>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/tenant-slug-input.js') }}"></script>
<script src="{{ asset('js/register-form.js') }}"></script>
@endpush
