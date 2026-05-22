@extends('layouts.admin')

@section('title', 'Subdomain check logs')
@section('page-title', 'Subdomain check logs')

@section('content')
<div class="logs-page">
    <p class="page-lead">
        CRM calls <code>/api/v1/tenant/resolve</code> per subdomain/host. Counts are stored in the master database
        and appended to the <a href="{{ route('admin.logs.index', ['channel' => 'resolve']) }}">resolve activity log</a>.
    </p>

    <div class="card" style="margin-bottom:1.25rem;">
        <div class="tenant-detail-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:1rem;">
            <div><strong>{{ number_format($totals['hosts']) }}</strong><br><span class="form-hint">Unique hosts</span></div>
            <div><strong>{{ number_format($totals['checks']) }}</strong><br><span class="form-hint">Total checks</span></div>
            <div><strong>{{ number_format($totals['allowed']) }}</strong><br><span class="form-hint">Allowed</span></div>
            <div><strong>{{ number_format($totals['denied']) }}</strong><br><span class="form-hint">Denied / not found</span></div>
        </div>
    </div>

    <form method="GET" class="page-toolbar" style="margin-bottom:1rem;">
        <input type="search" name="q" class="form-control" value="{{ $q }}" placeholder="Search host or slug…" style="max-width:280px;">
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        @if($q)<a href="{{ route('admin.subdomain-checks.index') }}" class="btn btn-outline btn-sm">Clear</a>@endif
    </form>

    <div class="card span-full">
        <h2 class="tenant-detail-heading">Checks per host</h2>
        <div class="table-scroll">
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Host</th>
                        <th>Company</th>
                        <th>Total checks</th>
                        <th>Allowed</th>
                        <th>Denied</th>
                        <th>Last result</th>
                        <th>Last checked</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stats as $row)
                        <tr>
                            <td>
                                <a href="{{ route('admin.subdomain-checks.show', ['host' => $row->host]) }}">
                                    <code>{{ $row->host }}</code>
                                </a>
                            </td>
                            <td>
                                @if($row->tenant)
                                    <a href="{{ route('admin.tenants.show', $row->tenant) }}">{{ $row->tenant->name }}</a>
                                @elseif($row->slug)
                                    <code>{{ $row->slug }}</code>
                                @else
                                    —
                                @endif
                            </td>
                            <td><strong>{{ number_format($row->check_count) }}</strong></td>
                            <td>{{ number_format($row->allowed_count) }}</td>
                            <td>{{ number_format($row->denied_count + $row->not_found_count) }}</td>
                            <td>
                                <span class="badge badge-{{ $row->last_outcome === 'allowed' ? 'active' : 'failed' }}">
                                    {{ $row->last_outcome ?? '—' }}
                                </span>
                                @if($row->last_code)<br><code class="code-pill code-pill-muted">{{ $row->last_code }}</code>@endif
                            </td>
                            <td>{{ $row->last_checked_at?->format('d M Y H:i:s') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="detail-empty">No subdomain checks recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($stats->hasPages())
            <div style="margin-top:1rem;">{{ $stats->links() }}</div>
        @endif
    </div>

    <div class="card span-full" style="margin-top:1.25rem;">
        <h2 class="tenant-detail-heading">Recent checks (last 50)</h2>
        <div class="table-scroll">
            <table class="detail-table detail-table-logs">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Host</th>
                        <th>Outcome</th>
                        <th>HTTP</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentLogs as $log)
                        <tr>
                            <td>{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                            <td><code>{{ $log->host }}</code></td>
                            <td><span class="badge badge-{{ $log->outcome === 'allowed' ? 'active' : 'failed' }}">{{ $log->outcome }}</span></td>
                            <td>{{ $log->http_status }}</td>
                            <td>{{ Str::limit($log->message, 60) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
