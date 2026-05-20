@extends('layouts.website')

@section('title', 'Home')
@section('meta_description', config('website.tagline'))

@section('content')
{{-- Hero --}}
<section class="hero hero-home">
    <div class="container hero-grid">
        <div class="hero-content">
            <p class="hero-eyebrow">B2B CRM Master Platform</p>
            <h1>Power your consultancy with a <span class="hero-highlight">dedicated CRM</span></h1>
            <p class="hero-lead">{{ config('website.tagline') }}. We host, provision, and support your branded portal — you focus on students and growth.</p>
            <div class="hero-actions">
                <a href="{{ route('register') }}" class="btn btn-primary">Start registration</a>
                <a href="{{ route('services') }}" class="btn btn-outline">Our services</a>
            </div>
        </div>
        <div class="hero-panel">
            <div class="hero-panel-card">
                <p class="hero-panel-title">What you get</p>
                <ul class="hero-checklist">
                    <li>Private MySQL database</li>
                    <li>Branded subdomain CRM</li>
                    <li>Lead & application tools</li>
                    <li>Admin approval & go-live</li>
                </ul>
                <a href="{{ route('pricing') }}" class="btn btn-primary btn-block">See pricing →</a>
            </div>
        </div>
    </div>
</section>

{{-- Stats --}}
@if(!empty($stats))
<section class="stats-bar">
    <div class="container stats-grid">
        @foreach($stats as $stat)
            <div class="stat-item">
                <span class="stat-value">{{ $stat['value'] }}</span>
                <span class="stat-label">{{ $stat['label'] }}</span>
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- Services preview --}}
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <h2 class="section-title">Services we provide</h2>
            <p class="section-sub">Everything Guarantee Admit delivers to partner education consultancies — from hosting to onboarding.</p>
            <a href="{{ route('services') }}" class="section-link">View all services →</a>
        </div>
        <div class="services-grid">
            @foreach($services as $service)
                @include('website.partials.service-card', ['service' => $service])
            @endforeach
        </div>
    </div>
</section>

{{-- How it works --}}
<section class="section">
    <div class="container">
        <h2 class="section-title text-center">How it works</h2>
        <p class="section-sub text-center">Go live in three simple steps</p>
        <div class="steps-grid">
            <div class="step-card">
                <span class="step-num">1</span>
                <h3>Register online</h3>
                <p>Submit your company, contact, and branding details. Choose a subscription plan.</p>
            </div>
            <div class="step-card">
                <span class="step-num">2</span>
                <h3>We review & provision</h3>
                <p>Our team approves your application, creates your database, and configures your subdomain.</p>
            </div>
            <div class="step-card">
                <span class="step-num">3</span>
                <h3>Start using your CRM</h3>
                <p>Log in at your branded URL. Manage leads, applications, and agents from day one.</p>
            </div>
        </div>
    </div>
</section>

{{-- Plans teaser --}}
@if($plans->isNotEmpty())
<section class="section section-alt">
    <div class="container">
        <h2 class="section-title text-center">Plans for every stage</h2>
        <p class="section-sub text-center">Flexible tiers — from free starter to enterprise white-label.</p>
        <div class="plans-teaser">
            @foreach($plans as $plan)
                <div class="plan-teaser-card {{ $plan->is_featured ? 'featured' : '' }}">
                    <h3>{{ $plan->name }}</h3>
                    <p class="plan-teaser-price">
                        @if($plan->price > 0)
                            {{ $plan->currency }} {{ number_format($plan->price, 0) }}<small>/{{ $plan->interval }}</small>
                        @else
                            Free
                        @endif
                    </p>
                    @if($plan->description)
                        <p class="plan-teaser-desc">{{ Str::limit($plan->description, 80) }}</p>
                    @endif
                </div>
            @endforeach
        </div>
        <p class="text-center" style="margin-top:28px">
            <a href="{{ route('pricing') }}" class="btn btn-primary">Compare all plans</a>
        </p>
    </div>
</section>
@endif

{{-- CTA --}}
<section class="cta-band">
    <div class="container cta-inner">
        <div>
            <h2>Ready to launch your CRM?</h2>
            <p>Join education consultancies running on Guarantee Admit's multi-tenant platform.</p>
        </div>
        <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Register your company</a>
    </div>
</section>

{{-- Blog --}}
@if($posts->isNotEmpty())
<section class="section">
    <div class="container">
        <h2 class="section-title">Latest updates</h2>
        <p class="section-sub">News from the Guarantee Admit team</p>
        <div class="blog-grid">
            @foreach($posts as $post)
                @include('partials.blog-card', ['post' => $post, 'excerptLimit' => 140])
            @endforeach
        </div>
        <p class="text-center" style="margin-top:32px">
            <a href="{{ route('blog.index') }}" class="btn btn-outline">View all posts</a>
        </p>
    </div>
</section>
@endif
@endsection
