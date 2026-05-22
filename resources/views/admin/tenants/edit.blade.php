@extends('layouts.admin')

@section('title', 'Edit '.$tenant->name)
@section('page-title', 'Edit: '.$tenant->name)

@section('content')
<div class="page-toolbar">
    <p class="page-lead">Company profile, contact, and branding only. Approval, database provisioning, and subscription are on the <a href="{{ route('admin.tenants.show', $tenant) }}#tenant-manage">company view</a>.</p>
    <a href="{{ route('admin.tenants.show', $tenant) }}" class="btn btn-outline btn-sm">← View company</a>
</div>

<div class="card admin-form-card">
    <form method="POST" action="{{ route('admin.tenants.update', $tenant) }}" class="admin-form" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.tenants._form', ['isCreate' => false, 'tenant' => $tenant])

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="{{ route('admin.tenants.show', $tenant) }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
@endsection
