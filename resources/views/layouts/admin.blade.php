<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') — B2B CRM Master</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="tinymce-upload-url" content="{{ route('admin.upload-image') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @stack('styles')
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="sidebar-brand">B2B CRM Master</div>
        <nav class="sidebar-nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
            @if(master_can('tenants.view'))
            <a href="{{ route('admin.tenants.index') }}" class="{{ request()->routeIs('admin.tenants.*') ? 'active' : '' }}">
                Companies
                @php $pendingCount = \App\Models\Tenant::where('status', 'pending')->count(); @endphp
                @if($pendingCount > 0)
                    <span class="badge-count">{{ $pendingCount }}</span>
                @endif
            </a>
            @if(master_can('tenants.create'))
            <a href="{{ route('admin.tenants.create') }}">+ Add company</a>
            @endif
            @endif
            @if(master_can_view_activity_logs())
            <a href="{{ route('admin.logs.index') }}" class="{{ request()->routeIs('admin.logs.*') ? 'active' : '' }}">Activity logs</a>
            @endif
            @if(master_can('tickets.view'))
            <a href="{{ route('admin.tickets.index') }}" class="{{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}">Support tickets</a>
            @endif
            @if(master_can('plans.view'))
            <a href="{{ route('admin.plans.index') }}" class="{{ request()->routeIs('admin.plans.*') ? 'active' : '' }}">Subscription plans</a>
            @endif
            @if(master_can('pages.view'))
            <a href="{{ route('admin.pages.index') }}" class="{{ request()->routeIs('admin.pages.*') ? 'active' : '' }}">Custom pages</a>
            @endif
            @if(master_can('blog.view'))
            <a href="{{ route('admin.blog.index') }}" class="{{ request()->routeIs('admin.blog.*') ? 'active' : '' }}">Website blog</a>
            @if(master_can('blog.create'))
            <a href="{{ route('admin.blog.create') }}">+ New blog post</a>
            @endif
            @endif
            @if(master_can('users.view') || master_can('roles.view') || master_can('permissions.view'))
            <div class="sidebar-section-label">Access control</div>
            @endif
            @if(master_can('users.view'))
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Users</a>
            @endif
            @if(master_can('roles.view'))
            <a href="{{ route('admin.roles.index') }}" class="{{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">Roles</a>
            @endif
            @if(master_can('permissions.view'))
            <a href="{{ route('admin.permissions.index') }}" class="{{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}">Permissions</a>
            @endif
            @if(master_can('settings.view'))
            <a href="{{ route('admin.settings.edit') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">Web settings</a>
            @endif
            @if(master_can_view_horizon())
            <a href="{{ url('/horizon') }}" target="_blank" rel="noopener" class="{{ request()->is('horizon*') ? 'active' : '' }}">Queue (Horizon) ↗</a>
            @endif
            <hr style="border:0;border-top:1px solid rgba(255,255,255,.15);margin:12px 0">
            <a href="{{ route('home') }}" target="_blank">View website</a>
            <a href="{{ route('register') }}" target="_blank">Registration page</a>
        </nav>
        <div class="sidebar-footer">Master control panel</div>
    </aside>

    <div class="admin-main">
        <header class="admin-header">
            <h1>@yield('page-title', 'Dashboard')</h1>
            <div class="header-actions">
                <span class="env-url-badge env-url-badge-{{ $urlEnvironment['is_local'] ? 'local' : 'production' }}" title="Tenant CRM URLs use this base domain">
                    {{ $urlEnvironment['label'] }} · {{ $urlEnvironment['scheme'] }}://*{{ $urlEnvironment['tenant_base'] }}{{ $urlEnvironment['port'] }}
                </span>
                @include('admin.partials.user-menu')
            </div>
        </header>

        <main class="admin-content">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
