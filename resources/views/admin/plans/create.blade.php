@extends('layouts.admin')
@section('title', 'Add plan')
@section('page-title', 'Add subscription plan')

@section('content')
<div class="page-toolbar">
    <p class="page-lead">Create a tier for the public pricing page and company registration.</p>
    <a href="{{ route('admin.plans.index') }}" class="btn btn-outline btn-sm">← Back to plans</a>
</div>

<div class="card admin-form-card">
    <form method="POST" action="{{ route('admin.plans.store') }}" class="admin-form" data-tinymce-form>
        @csrf
        @include('admin.plans._form', ['isCreate' => true, 'plan' => null])

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create plan</button>
            <a href="{{ route('admin.plans.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
@endsection
