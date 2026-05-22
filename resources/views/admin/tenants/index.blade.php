@extends('layouts.admin')
@php
    use App\Support\TenantUrl;
    $canMigrate = master_can('tenants.edit');
    $from = $tenants->total() ? $tenants->firstItem() : 0;
    $to = $tenants->lastItem() ?? 0;
    $activeFilterCount = (request('q') ? 1 : 0) + (request('status') ? 1 : 0);
@endphp
@section('title', 'Companies')
@section('page-title', 'Companies')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/companies-list.css') }}">
@endpush

@section('content')
<div class="list-page list-page-companies" id="companies-list-page">

    @if($errors->any())
        <div class="alert alert-error">
            <ul class="validation-errors-list">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('migrate_results'))
        @php
            $mOk = (int) session('migrate_ok_count', 0);
            $mFail = (int) session('migrate_fail_count', 0);
        @endphp
        <div class="card migrate-results-card list-panel-card">
            <h2 class="migrate-results-title">Migration run complete</h2>
            <p class="migrate-results-summary">
                <span class="migrate-stat migrate-stat-ok"><strong>{{ $mOk }}</strong> succeeded</span>
                <span class="migrate-stat migrate-stat-fail"><strong>{{ $mFail }}</strong> failed</span>
            </p>
            @if((int) session('migrate_total_eligible', 0) > (int) session('migrate_run_count', 0))
                <p class="migrate-cap-notice">
                    Only the first <strong>{{ session('migrate_run_count') }}</strong> of
                    <strong>{{ session('migrate_total_eligible') }}</strong> companies were run (safety cap).
                </p>
            @endif
            <div class="table-scroll">
                <table class="data-table migrate-results-table">
                    <thead>
                        <tr>
                            <th>Result</th>
                            <th>Company</th>
                            <th>Slug</th>
                            <th>Database</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach(session('migrate_results') as $row)
                        <tr>
                            <td>
                                @if($row['ok'])
                                    <span class="badge badge-migrate-ok">OK</span>
                                @else
                                    <span class="badge badge-migrate-fail">Failed</span>
                                @endif
                            </td>
                            <td>{{ $row['name'] }}</td>
                            <td><code class="code-pill code-pill-muted">{{ $row['slug'] }}</code></td>
                            <td><code class="code-pill code-pill-muted">{{ $row['database_name'] ?? '—' }}</code></td>
                            <td class="migrate-msg-cell">{{ $row['message'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="list-page-header">
        <div class="list-page-heading">
            <h2 class="list-page-title">
                Companies
                <span class="list-page-count">({{ $totalCount }})</span>
            </h2>
            <p class="list-page-lead">Approve pending companies, filter by status, and run CRM database migrations.</p>
        </div>
        @if(master_can('tenants.create'))
            <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary">+ Add company</a>
        @endif
    </div>

    <div class="list-toolbar" role="toolbar" aria-label="Companies list tools">
        <button type="button" class="list-tool-btn is-active" data-panel-toggle="counts" aria-expanded="true" aria-controls="panel-counts">
            <svg class="list-tool-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M4 19h4v-7H4v7zm6 0h4V5h-4v14zm6 0h4v-9h-4v9z"/></svg>
            <span>Counts</span>
        </button>
        <button type="button" class="list-tool-btn {{ $activeFilterCount ? 'is-active has-badge' : '' }}" data-panel-toggle="filters" aria-expanded="{{ $activeFilterCount ? 'true' : 'false' }}" aria-controls="panel-filters">
            <svg class="list-tool-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
            <span>Filters</span>
            @if($activeFilterCount)
                <span class="list-tool-badge">{{ $activeFilterCount }}</span>
            @endif
        </button>
        @if($canMigrate && config('master.tenant_crm_path'))
            <button type="button" class="list-tool-btn" data-panel-toggle="migrate" aria-expanded="false" aria-controls="panel-migrate">
                <svg class="list-tool-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
                <span>Migrations</span>
                <span class="list-tool-badge list-tool-badge-muted">{{ $migrateableTenantCount }}</span>
            </button>
        @endif
        <span class="list-toolbar-spacer"></span>
        <span class="list-toolbar-meta">
            @if($tenants->total() === 0)
                <strong>0</strong> results
            @else
                <strong>{{ $from }}–{{ $to }}</strong> of <strong>{{ $tenants->total() }}</strong>
                @if($hasFilters)<span class="list-toolbar-meta-muted">(filtered)</span>@endif
            @endif
        </span>
    </div>

    {{-- Counts panel --}}
    <div class="list-panel is-open" id="panel-counts" data-panel="counts">
        <div class="count-chips count-chips-bar">
            <a href="{{ route('admin.tenants.index', request()->only('q')) }}"
               class="count-chip {{ !request('status') ? 'active' : '' }}">
                <span class="count-chip-value">{{ $totalCount }}</span>
                <span class="count-chip-label">All</span>
            </a>
            @foreach($statuses as $status)
                @php $count = (int) ($statusCounts[$status] ?? 0); @endphp
                <a href="{{ route('admin.tenants.index', array_merge(request()->only('q'), ['status' => $status])) }}"
                   class="count-chip count-chip-{{ $status }} {{ request('status') === $status ? 'active' : '' }} {{ $count === 0 ? 'is-zero' : '' }}">
                    <span class="count-chip-value">{{ $count }}</span>
                    <span class="count-chip-label">{{ ucfirst($status) }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Filters panel --}}
    <div class="list-panel {{ $activeFilterCount ? 'is-open' : '' }}" id="panel-filters" data-panel="filters">
        <div class="list-panel-card filter-panel-inner">
            <form method="GET" class="filter-form filter-form-grid" id="companies-filter-form">
                <div class="filter-field filter-field-search">
                    <label for="tenant-q">Search</label>
                    <input type="search" id="tenant-q" name="q" value="{{ request('q') }}"
                           placeholder="Name, slug, email, database…" class="form-control">
                </div>
                <div class="filter-field">
                    <label for="tenant-status">Status</label>
                    <select id="tenant-status" name="status" class="form-control">
                        <option value="">All statuses</option>
                        @foreach($statuses as $s)
                            <option value="{{ $s }}" @selected(request('status') === $s)>
                                {{ ucfirst($s) }} ({{ (int) ($statusCounts[$s] ?? 0) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Apply filters</button>
                    @if($hasFilters)
                        <a href="{{ route('admin.tenants.index') }}" class="btn btn-outline btn-sm">Clear all</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Migrations panel --}}
    @if($canMigrate && ! config('master.tenant_crm_path'))
        <div class="alert alert-error migrate-path-warning">
            Could not find tenant CRM app. Set path in <a href="{{ route('admin.settings.edit') }}">Web settings</a> or <code>TENANT_CRM_PATH</code> in <code>.env</code>.
        </div>
    @elseif($canMigrate && config('master.tenant_crm_path'))
        <div class="list-panel" id="panel-migrate" data-panel="migrate">
            <div class="card migrate-runner-card list-panel-card" id="tenant-migrate-panel"
                 data-queue-url="{{ route('admin.tenants.migration-queue') }}"
                 data-migrate-url-template="{{ url('admin/tenants') }}/__ID__/migrate-database"
                 data-csrf="{{ csrf_token() }}"
                 data-migrateable-count="{{ $migrateableTenantCount }}">
                <div class="migrate-panel-head">
                    <div>
                        <h3 class="migrate-panel-title">B2B CRM migrations</h3>
                        <p class="migrate-panel-desc">
                            Load company databases from master, then run <code>php artisan migrate --force</code> one at a time in
                            <code class="migrate-path-code">{{ config('master.tenant_crm_path') }}</code>.
                        </p>
                    </div>
                    <div class="migrate-panel-actions">
                        <button type="button" class="btn btn-primary btn-sm" id="btn-migrate-refresh"
                                @disabled($migrateableTenantCount === 0)>
                            <span class="btn-label-load">Load databases</span>
                            <span class="btn-label-loading d-none">Loading…</span>
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" id="btn-migrate-run" disabled>
                            Run migrations
                        </button>
                        <form method="POST" action="{{ route('admin.tenants.migrate-databases') }}" class="migrate-sync-form"
                              onsubmit="return confirm('Run all {{ (int) $migrateableTenantCount }} migrations in one request (may time out)?');">
                            @csrf
                            <input type="hidden" name="migrate_all" value="1">
                            @foreach(request()->only(['q', 'status', 'page']) as $k => $v)
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endforeach
                            <button type="submit" class="btn btn-outline btn-sm"
                                    @disabled($migrateableTenantCount === 0) title="Single request, no live progress">
                                Migrate all (sync)
                            </button>
                        </form>
                    </div>
                </div>

                <div class="migrate-empty-state" id="migrate-empty-state">
                    <p class="migrate-queue-meta" id="migrate-queue-meta">
                        <strong>{{ $migrateableTenantCount }}</strong> {{ Str::plural('database', $migrateableTenantCount) }} available.
                        Click <strong>Load databases</strong> to fetch the queue and enable <strong>Run migrations</strong>.
                    </p>
                </div>

                <p class="migrate-queue-progress d-none" id="migrate-queue-progress" aria-live="polite"></p>

                <div class="table-scroll migrate-queue-scroll d-none" id="migrate-queue-wrap">
                    <table class="data-table migrate-queue-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Result</th>
                                <th>Company</th>
                                <th>Slug</th>
                                <th>Database</th>
                                <th>Domain(s)</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody id="migrate-queue-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Companies table --}}
    <div class="card table-card list-table-card">
        <div class="table-scroll">
            <table class="data-table data-table-tenants">
                <thead>
                    <tr>
                        <th class="col-company">Company</th>
                        <th class="col-slug">Slug</th>
                        <th class="col-db">Database</th>
                        <th class="col-domain">Domain</th>
                        <th class="col-plan">Plan</th>
                        <th class="col-status">Status</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($tenants as $tenant)
                    @php
                        $host = TenantUrl::hostForTenant($tenant);
                        $fullUrl = TenantUrl::urlForHost($host);
                    @endphp
                    <tr class="data-table-row">
                        <td class="col-company">
                            <div class="cell-stack">
                                <a href="{{ route('admin.tenants.show', $tenant) }}" class="cell-primary">{{ $tenant->name }}</a>
                                @if($tenant->contact_email)
                                    <span class="cell-muted">{{ $tenant->contact_email }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="col-slug">
                            <div class="cell-copyable">
                                <code class="code-pill code-pill-truncate" title="{{ $tenant->slug }}">{{ $tenant->slug }}</code>
                                @include('admin.partials.copy-btn', ['text' => $tenant->slug, 'title' => 'Copy slug'])
                            </div>
                        </td>
                        <td class="col-db">
                            <div class="cell-copyable">
                                <code class="code-pill code-pill-muted code-pill-truncate" title="{{ $tenant->database_name }}">{{ $tenant->database_name ?: '—' }}</code>
                                @if($tenant->database_name)
                                    @include('admin.partials.copy-btn', ['text' => $tenant->database_name, 'title' => 'Copy database'])
                                @endif
                            </div>
                        </td>
                        <td class="col-domain">
                            @if($host)
                                <div class="cell-stack">
                                    <span class="cell-domain-text" title="{{ $host }}">{{ $host }}</span>
                                    <span class="cell-copy-actions">
                                        @include('admin.partials.copy-btn', ['text' => $host, 'title' => 'Copy domain'])
                                        @if($fullUrl)
                                            <a href="{{ $fullUrl }}" target="_blank" rel="noopener" class="cell-link-sm">Open CRM ↗</a>
                                        @endif
                                    </span>
                                </div>
                            @else
                                <span class="cell-empty">—</span>
                            @endif
                        </td>
                        <td class="col-plan">
                            @if($tenant->subscriptionPlan)
                                <span class="plan-pill">{{ $tenant->subscriptionPlan->name }}</span>
                            @else
                                <span class="cell-empty">—</span>
                            @endif
                        </td>
                        <td class="col-status">
                            <span class="badge badge-{{ $tenant->status }} badge-status">{{ ucfirst($tenant->status) }}</span>
                        </td>
                        <td class="col-actions">
                            <a href="{{ route('admin.tenants.show', $tenant) }}" class="btn btn-outline btn-sm">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="table-empty">
                            <div class="table-empty-inner">
                                <p class="table-empty-title">No companies found</p>
                                @if($hasFilters)
                                    <p class="table-empty-hint">Try changing filters or <a href="{{ route('admin.tenants.index') }}">clear all</a>.</p>
                                @else
                                    <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary btn-sm">Add company</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($tenants->hasPages())
            <div class="table-footer table-footer-centered">
                {{ $tenants->links() }}
            </div>
        @endif
    </div>
</div>

@push('scripts')
    <script src="{{ asset('js/copy-to-clipboard.js') }}"></script>
    <script src="{{ asset('js/companies-list.js') }}"></script>
    @if($canMigrate && config('master.tenant_crm_path'))
        <script src="{{ asset('js/tenant-migrate-runner.js') }}"></script>
    @endif
@endpush
@endsection
