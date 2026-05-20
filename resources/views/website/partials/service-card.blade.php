@php
    $showPoints = $showPoints ?? false;
    $slug = $service['slug'] ?? null;
@endphp
<article class="service-card{{ $showPoints ? ' service-card-expanded' : '' }}"@if($slug) id="{{ $slug }}"@endif>
    <div class="service-card-icon-wrap">
        @include('website.partials.service-icon', ['icon' => $service['icon'] ?? 'crm'])
    </div>
    <h3 class="service-card-title">{{ $service['title'] }}</h3>
    <p class="service-card-summary">{{ $service['summary'] }}</p>
    @if($showPoints && ! empty($service['points']))
        <ul class="service-card-points">
            @foreach($service['points'] as $point)
                <li>{{ $point }}</li>
            @endforeach
        </ul>
    @endif
</article>
