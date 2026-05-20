@extends('layouts.admin')
@section('title', 'Subscription plans')
@section('page-title', 'Subscription plans')

@section('content')
<div class="page-toolbar">
    <p class="page-lead">Plans shown on the public pricing page and registration form.</p>
    <a href="{{ route('admin.plans.create') }}" class="btn btn-primary">Add plan</a>
</div>

<p class="results-summary">
    <strong>{{ $plans->count() }}</strong> {{ Str::plural('plan', $plans->count()) }}
    · <a href="{{ route('pricing') }}" target="_blank">View pricing page →</a>
</p>

<div class="card table-card">
    <div class="table-scroll">
        <table class="data-table data-table-plans">
            <thead>
                <tr>
                    <th class="col-plan">Plan</th>
                    <th class="col-price">Price</th>
                    <th class="col-features">Features</th>
                    <th class="col-status">Status</th>
                    <th class="col-tenants">Companies</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($plans as $plan)
                <tr>
                    <td class="col-plan">
                        <a href="{{ route('admin.plans.edit', $plan) }}" class="cell-primary">{{ $plan->name }}</a>
                        <code class="code-pill">{{ $plan->slug }}</code>
                        @if($plan->is_featured)
                            <span class="badge badge-published" style="margin-top:6px">Featured</span>
                        @endif
                        @if($plan->description)
                            <span class="cell-muted">{!! Str::limit(strip_tags($plan->description), 60) !!}</span>
                        @endif
                    </td>
                    <td class="col-price">
                        @if($plan->price > 0)
                            <strong>{{ $plan->currency }} {{ number_format($plan->price, 0) }}</strong>
                            <span class="cell-muted">/ {{ $plan->interval }}</span>
                        @else
                            <span class="badge badge-active">Free</span>
                        @endif
                        <span class="cell-muted">Order: {{ $plan->sort_order }}</span>
                    </td>
                    <td class="col-features">
                        @if($plan->features)
                            <ul class="feature-mini-list">
                                @foreach(array_slice($plan->features, 0, 3) as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                                @if(count($plan->features) > 3)
                                    <li class="text-muted">+{{ count($plan->features) - 3 }} more</li>
                                @endif
                            </ul>
                        @else
                            <span class="cell-empty">—</span>
                        @endif
                    </td>
                    <td class="col-status">
                        @if($plan->is_active)
                            <span class="badge badge-active">Active</span>
                        @else
                            <span class="badge badge-draft">Inactive</span>
                        @endif
                    </td>
                    <td class="col-tenants">{{ $plan->tenants_count }}</td>
                    <td class="col-actions">
                        <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-outline btn-sm">Edit</a>
                        @if($plan->tenants_count === 0)
                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="inline-delete-form" onsubmit="return confirm('Delete this plan?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline btn-sm btn-danger-text">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="table-empty">
                        <p>No plans yet.</p>
                        <a href="{{ route('admin.plans.create') }}" class="btn btn-primary btn-sm">Create your first plan</a>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
