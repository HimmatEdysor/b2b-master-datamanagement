@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="stats-grid">
    <div class="stat-card pending">
        <div class="value">{{ $stats['pending'] }}</div>
        <div class="label">Pending approval</div>
    </div>
    <div class="stat-card">
        <div class="value">{{ $stats['active'] }}</div>
        <div class="label">Active companies</div>
    </div>
    <div class="stat-card">
        <div class="value">{{ $stats['total'] }}</div>
        <div class="label">Total companies</div>
    </div>
    <div class="stat-card">
        <div class="value">{{ $stats['blog_published'] }}</div>
        <div class="label">Published posts</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <div class="card">
        <h2 class="card-title">Pending registrations</h2>
        @forelse($pendingTenants as $t)
            <div style="padding:12px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
                <div>
                    <strong>{{ $t->name }}</strong>
                    <div style="font-size:13px;color:var(--muted)">{{ $t->contact_email }} · <code>{{ $t->slug }}</code></div>
                </div>
                <a href="{{ route('admin.tenants.show', $t) }}" class="btn btn-primary btn-sm">Review</a>
            </div>
        @empty
            <p style="color:var(--muted);margin:0">No pending companies.</p>
        @endforelse
        @if($stats['pending'] > 0)
            <p style="margin-top:16px"><a href="{{ route('admin.tenants.index', ['status' => 'pending']) }}">View all pending →</a></p>
        @endif
    </div>

    <div class="card">
        <h2 class="card-title">Recent activity</h2>
        @if(master_can('logs.view'))
            <p style="margin:0 0 12px"><a href="{{ route('admin.logs.index') }}">View all activity logs (database, S3, domain, DNS) →</a></p>
        @endif
        <table class="data-table">
            <thead><tr><th>Action</th><th>Company</th><th>Status</th></tr></thead>
            <tbody>
            @foreach($recentLogs as $log)
                <tr>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->tenant?->name ?? '—' }}</td>
                    <td>{{ $log->status }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2 class="card-title">Quick links</h2>
    <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary">Add company (admin)</a>
    <a href="{{ route('admin.blog.create') }}" class="btn btn-outline" style="margin-left:8px">Write blog post</a>
    <a href="{{ route('register') }}" class="btn btn-outline" style="margin-left:8px" target="_blank">Public registration</a>
</div>
@endsection
