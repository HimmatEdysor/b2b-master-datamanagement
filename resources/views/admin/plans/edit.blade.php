@extends('layouts.admin')
@section('title', 'Edit plan')
@section('page-title', 'Edit: '.$plan->name)

@section('content')
<div class="page-toolbar">
    <p class="page-lead">Update pricing, features, and visibility for this subscription tier.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a href="{{ route('pricing', ['plan' => $plan->slug]) }}" target="_blank" class="btn btn-outline btn-sm">Preview on site</a>
        <a href="{{ route('admin.plans.index') }}" class="btn btn-outline btn-sm">← All plans</a>
    </div>
</div>

@if($plan->tenants_count > 0)
    <div class="alert alert-info">
        This plan is assigned to <strong>{{ $plan->tenants_count }}</strong> {{ Str::plural('company', $plan->tenants_count) }}.
    </div>
@endif

<div class="card admin-form-card">
    <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="admin-form" data-tinymce-form>
        @csrf
        @method('PUT')
        @include('admin.plans._form', ['isCreate' => false, 'plan' => $plan])

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="{{ route('admin.plans.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
@endsection
