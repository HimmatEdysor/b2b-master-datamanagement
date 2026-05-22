@extends('layouts.admin')

@section('title', 'Subdomain checks — '.$host)
@section('page-title', 'Subdomain checks')

@section('content')
<div class="page-toolbar" style="margin-bottom:1rem;">
    <a href="{{ route('admin.subdomain-checks.index') }}" class="btn btn-outline btn-sm">← All hosts</a>
</div>

<div class="card" style="margin-bottom:1.25rem;">
    <h2 class="tenant-detail-heading"><code>{{ $stat->host }}</code></h2>
    <table class="detail-table">
        @include('admin.tenants._detail-row', ['label' => 'Total checks', 'value' => number_format($stat->check_count)])
        @include('admin.tenants._detail-row', ['label' => 'Allowed', 'value' => number_format($stat->allowed_count)])
        @include('admin.tenants._detail-row', ['label' => 'Denied / not found', 'value' => number_format($stat->denied_count + $stat->not_found_count)])
        @include('admin.tenants._detail-row', ['label' => 'First check', 'value' => $stat->first_checked_at?->format('d M Y H:i:s')])
        @include('admin.tenants._detail-row', ['label' => 'Last check', 'value' => $stat->last_checked_at?->format('d M Y H:i:s')])
        @include('admin.tenants._detail-row', ['label' => 'Last message', 'value' => $stat->last_message])
    </table>
</div>

<div class="card span-full">
    <h2 class="tenant-detail-heading">Check history</h2>
    <div class="table-scroll">
        <table class="detail-table detail-table-logs">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Outcome</th>
                    <th>HTTP</th>
                    <th>Code</th>
                    <th>Message</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                        <td>{{ $log->outcome }}</td>
                        <td>{{ $log->http_status }}</td>
                        <td>{{ $log->code ?? '—' }}</td>
                        <td>{{ $log->message }}</td>
                        <td>{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
        <div style="margin-top:1rem;">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
