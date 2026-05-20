@extends('layouts.website')

@section('title', 'Our services')
@section('meta_description', 'Services provided by Guarantee Admit: dedicated CRM, database hosting, branding, lead management, onboarding, and support for education consultancies.')

@section('content')
<section class="page-hero">
    <div class="container">
        <p class="hero-eyebrow">What we provide</p>
        <h1>Our services</h1>
        <p class="page-hero-lead">Guarantee Admit partners with education consultancies and study abroad agencies. We deliver the full technology stack — hosting, provisioning, branding, and support — so you can run a world-class CRM under your own brand.</p>
    </div>
</section>

<section class="section services-page-section">
    <div class="container">
        <div class="services-grid services-page-grid">
            @foreach($services as $service)
                @include('website.partials.service-card', ['service' => $service, 'showPoints' => true])
            @endforeach
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <h2 class="section-title text-center">Why partner with us?</h2>
        <p class="section-sub text-center">Built for education consultancies, not generic sales tools</p>
        <div class="why-grid">
            <article class="why-card">
                <span class="why-card-icon" aria-hidden="true">🎓</span>
                <h3>Built for education</h3>
                <p>CRM workflows designed for consultancies, agents, and international student journeys — not generic sales pipelines.</p>
            </article>
            <article class="why-card">
                <span class="why-card-icon" aria-hidden="true">🔒</span>
                <h3>Secure & isolated</h3>
                <p>Each company gets its own database. Your student data never mixes with other tenants.</p>
            </article>
            <article class="why-card">
                <span class="why-card-icon" aria-hidden="true">⚡</span>
                <h3>Managed for you</h3>
                <p>We handle infrastructure, updates, and provisioning. You get a turnkey CRM without DevOps overhead.</p>
            </article>
        </div>
    </div>
</section>

<section class="cta-band">
    <div class="container cta-inner">
        <div>
            <h2>Let's get your CRM live</h2>
            <p>Register today — our team will review and provision your tenant within 1–2 business days.</p>
        </div>
        <div class="cta-actions">
            <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Register now</a>
            <a href="{{ route('pricing') }}" class="btn btn-outline btn-lg cta-outline">View pricing</a>
        </div>
    </div>
</section>
@endsection
