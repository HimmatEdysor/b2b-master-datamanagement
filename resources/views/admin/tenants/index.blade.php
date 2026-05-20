@extends('layouts.admin')
@php use App\Support\TenantUrl; @endphp
@section('title', 'Companies')
@section('page-title', 'Companies')

@section('content')
@php $canMigrate = master_can('tenants.edit'); @endphp
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
    <div class="card migrate-results-card">
        <h2 class="migrate-results-title">Migration run complete</h2>
        <p class="migrate-results-summary">
            <span class="migrate-stat migrate-stat-ok"><strong>{{ $mOk }}</strong> succeeded</span>
            <span class="migrate-stat migrate-stat-fail"><strong>{{ $mFail }}</strong> failed</span>
        </p>
        @if((int) session('migrate_total_eligible', 0) > (int) session('migrate_run_count', 0))
            <p class="migrate-cap-notice">
                Only the first <strong>{{ session('migrate_run_count') }}</strong> of
                <strong>{{ session('migrate_total_eligible') }}</strong> companies were run (safety cap).
                Raise <code>TENANT_CRM_MIGRATE_BULK_MAX_TENANTS</code> in <code>.env</code> if you need more in one request.
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
<div class="page-toolbar">
    <div>
        <p class="page-lead">Approve pending companies before the database is provisioned.</p>
    </div>
    <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary">Add company</a>
</div>

{{-- Status counts --}}
<div class="count-chips">
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

{{-- Filters --}}
<div class="card filter-card">
    <form method="GET" class="filter-form">
        @if(request('status'))
            <input type="hidden" name="status" value="{{ request('status') }}">
        @endif
        <div class="filter-field filter-field-search">
            <label for="tenant-q">Search</label>
            <input type="search" id="tenant-q" name="q" value="{{ request('q') }}"
                   placeholder="Name, slug, email, database…" class="form-control">
        </div>
        <div class="filter-field">
            <label for="tenant-status">Status</label>
            <select id="tenant-status" name="status" class="form-control" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach($statuses as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>
                        {{ ucfirst($s) }} ({{ (int) ($statusCounts[$s] ?? 0) }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            @if($hasFilters)
                <a href="{{ route('admin.tenants.index') }}" class="btn btn-outline btn-sm">Clear</a>
            @endif
        </div>
    </form>
</div>

{{-- Results summary --}}
@php
    $from = $tenants->total() ? $tenants->firstItem() : 0;
    $to = $tenants->lastItem() ?? 0;
@endphp
<p class="results-summary">
    @if($tenants->total() === 0)
        <strong>0</strong> companies
        @if($hasFilters) matching your filters @endif
    @else
        Showing <strong>{{ $from }}–{{ $to }}</strong> of <strong>{{ $tenants->total() }}</strong>
        {{ Str::plural('company', $tenants->total()) }}
        @if($hasFilters)
            <span class="results-filtered">(filtered from {{ $totalCount }} total)</span>
        @endif
    @endif
</p>

@if($canMigrate && ! config('master.tenant_crm_path'))
    <div class="alert alert-error migrate-path-warning">
        Could not find a tenant CRM Laravel app. Set <code>TENANT_CRM_PATH</code> in <code>.env</code> to the folder that contains <code>artisan</code>, or install the master portal inside your monorepo so the parent directory is the CRM project.
    </div>
@elseif($canMigrate && config('master.tenant_crm_path'))
    <div class="card migrate-runner-card" id="tenant-migrate-panel"
         data-queue-url="{{ route('admin.tenants.migration-queue') }}"
         data-migrate-url-template="{{ url('admin/tenants') }}/__ID__/migrate-database"
         data-csrf="{{ csrf_token() }}">
        <div class="migrate-toolbar">
            <div class="migrate-toolbar-text">
                <strong>B2B CRM migrations (one database at a time)</strong>
                <span class="migrate-toolbar-hint">
                    Master loads companies from this portal (e.g. <code>guaranteeadmit</code> + tenant domains), then runs
                    <code>php artisan migrate --force</code> in
                    <code>{{ config('master.tenant_crm_path') }}</code> per company DB.
                    After adding a company or domain, click <strong>Refresh list</strong> and run again.
                </span>
            </div>
            <div class="migrate-toolbar-actions">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-migrate-refresh"
                        @disabled($migrateableTenantCount === 0)>
                    Refresh list
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-migrate-run" disabled>
                    Run migrations
                </button>
                <form method="POST" action="{{ route('admin.tenants.migrate-databases') }}" class="d-inline"
                      onsubmit="return confirm('Run all {{ (int) $migrateableTenantCount }} migrations in one page request (no live progress)?');">
                    @csrf
                    <input type="hidden" name="migrate_all" value="1">
                    @foreach(request()->only(['q', 'status', 'page']) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                    @endforeach
                    <button type="submit" class="btn btn-outline-secondary btn-sm"
                            @disabled($migrateableTenantCount === 0) title="Legacy: single POST, may time out on many DBs">
                        Migrate all (sync)
                    </button>
                </form>
            </div>
        </div>
        <p class="migrate-queue-meta" id="migrate-queue-meta">
            Click <strong>Refresh list</strong> to load {{ $migrateableTenantCount }} {{ Str::plural('database', $migrateableTenantCount) }} from master.
        </p>
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
@endif

{{-- Table --}}
<div class="card table-card">
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
                <tr>
                    <td class="col-company">
                        <a href="{{ route('admin.tenants.show', $tenant) }}" class="cell-primary">{{ $tenant->name }}</a>
                        @if($tenant->contact_email)
                            <span class="cell-muted">{{ $tenant->contact_email }}</span>
                        @endif
                    </td>
                    <td class="col-slug">
                        <div class="cell-copyable">
                            <code class="code-pill" title="{{ $tenant->slug }}">{{ $tenant->slug }}</code>
                            @include('admin.partials.copy-btn', ['text' => $tenant->slug, 'title' => 'Copy slug'])
                        </div>
                    </td>
                    <td class="col-db">
                        <div class="cell-copyable">
                            <code class="code-pill code-pill-muted" title="{{ $tenant->database_name }}">{{ $tenant->database_name }}</code>
                            @include('admin.partials.copy-btn', ['text' => $tenant->database_name, 'title' => 'Copy database name'])
                        </div>
                    </td>
                    <td class="col-domain">
                        @if($host)
                            <div class="cell-copyable cell-copyable-stack">
                                <span class="cell-domain" title="{{ $host }}">{{ $host }}</span>
                                <span class="cell-copy-actions">
                                    @include('admin.partials.copy-btn', ['text' => $host, 'title' => 'Copy domain'])
                                    @if($fullUrl)
                                        @include('admin.partials.copy-btn', ['text' => $fullUrl, 'title' => 'Copy full URL', 'label' => 'URL'])
                                    @endif
                                </span>
                            </div>
                        @else
                            <span class="cell-empty">—</span>
                        @endif
                    </td>
                    <td class="col-plan">
                        {{ $tenant->subscriptionPlan?->name ?? '—' }}
                    </td>
                    <td class="col-status">
                        <span class="badge badge-{{ $tenant->status }}">{{ ucfirst($tenant->status) }}</span>
                    </td>
                    <td class="col-actions">
                        <a href="{{ route('admin.tenants.show', $tenant) }}" class="btn btn-outline btn-sm">View</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="table-empty">
                        <p>No companies found.</p>
                        @if($hasFilters)
                            <a href="{{ route('admin.tenants.index') }}" class="btn btn-outline btn-sm">Clear filters</a>
                        @else
                            <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary btn-sm">Add company</a>
                        @endif
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($tenants->hasPages())
        <div class="table-footer">
            {{ $tenants->links() }}
        </div>
    @endif
</div>

@push('scripts')
    <script src="{{ asset('js/copy-to-clipboard.js') }}"></script>
    @if($canMigrate && config('master.tenant_crm_path'))
        <script src="{{ asset('js/tenant-migrate-runner.js') }}"></script>
    @endif
@endpush
@endsection
