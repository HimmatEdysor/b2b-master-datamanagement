<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'B2B CRM') — Guarantee Admit</title>
    <meta name="description" content="@yield('meta_description', 'Multi-tenant B2B CRM platform for education consultancies.')">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @stack('head')
    <link rel="stylesheet" href="{{ asset('css/website.css') }}">
    @stack('styles')
</head>
<body class="website-body">
<div class="site-shell">
    <header class="site-header">
        <div class="container inner">
            <a href="{{ route('home') }}" class="site-logo">{{ config('website.brand', 'Guarantee Admit') }}</a>
            <nav class="site-nav">
                <a href="{{ route('home') }}" class="nav-hide-mobile {{ request()->routeIs('home') ? 'active' : '' }}">Home</a>
                <a href="{{ route('services') }}" class="{{ request()->routeIs('services') ? 'active' : '' }}">Services</a>
                <a href="{{ route('pricing') }}" class="{{ request()->routeIs('pricing') ? 'active' : '' }}">Pricing</a>
                @isset($navPages)
                    @foreach($navPages as $navPage)
                        <a href="{{ route('page.show', $navPage->slug) }}">{{ $navPage->title }}</a>
                    @endforeach
                @endisset
                <a href="{{ route('blog.index') }}" class="nav-hide-mobile">Blog</a>
                <a href="{{ route('support.create') }}" class="{{ request()->routeIs('support.*') ? 'active' : '' }}">Support</a>
                <a href="{{ route('register') }}" class="btn btn-primary">Register</a>
            </nav>
        </div>
    </header>

    <main class="site-main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container inner">
            <span>&copy; {{ date('Y') }} Guarantee Admit — Master portal</span>
            <span>
                <a href="{{ route('home') }}">Home</a>
                &middot;
                <a href="{{ route('services') }}">Services</a>
                &middot;
                <a href="{{ route('pricing') }}">Pricing</a>
                &middot;
                <a href="{{ route('blog.index') }}">Blog</a>
                &middot;
                <a href="{{ route('support.create') }}">Support</a>
                &middot;
                <a href="{{ route('register') }}">Register</a>
            </span>
        </div>
    </footer>
</div>
@stack('scripts')
</body>
</html>
