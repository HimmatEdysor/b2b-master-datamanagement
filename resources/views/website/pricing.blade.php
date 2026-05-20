@extends('layouts.website')

@section('title', 'Pricing')

@section('content')
<section class="section" style="padding-top: 48px;">
    <div class="container">
        <h1 class="section-title" style="text-align:center">Subscription plans</h1>
        <p class="section-sub" style="text-align:center">Choose a plan and register your company. Our team will review and activate your CRM tenant.</p>

        @if($plans->isEmpty())
            <div class="empty-state">
                <p>Pricing plans are being configured. Please check back soon or <a href="{{ route('register') }}">contact us via registration</a>.</p>
            </div>
        @else
            <div class="pricing-grid">
                @foreach($plans as $plan)
                    <article class="pricing-card {{ $plan->is_featured ? 'featured' : '' }}">
                        @if($plan->is_featured)
                            <span class="pricing-badge">Popular</span>
                        @endif
                        <h3>{{ $plan->name }}</h3>
                        <p class="pricing-price">
                            @if($plan->price > 0)
                                <span class="amount">{{ $plan->currency }} {{ number_format($plan->price, 0) }}</span>
                                <span class="period">/{{ $plan->interval }}</span>
                            @else
                                <span class="amount">Free</span>
                            @endif
                        </p>
                        @if($plan->description)
                            <div class="pricing-desc cms-content">{!! $plan->description !!}</div>
                        @endif
                        @if($plan->features)
                            <ul class="pricing-features">
                                @foreach($plan->features as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <a href="{{ route('register', ['plan' => $plan->slug]) }}" class="btn btn-primary" style="width:100%;margin-top:auto">Get started</a>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
