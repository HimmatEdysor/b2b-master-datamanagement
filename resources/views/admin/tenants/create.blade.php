@extends('layouts.admin')

@section('title', 'Add company')
@section('page-title', 'Add company')

@section('content')
<div class="page-toolbar">
    <p class="page-lead">Create a new tenant company. Subdomain and database can be auto-generated from the slug.</p>
    <a href="{{ route('admin.tenants.index') }}" class="btn btn-outline btn-sm">← Back to list</a>
</div>

<div class="card admin-form-card">
    <form method="POST" action="{{ route('admin.tenants.store') }}" class="admin-form" enctype="multipart/form-data"
          data-clone-db-prompt>
        @csrf
        @include('admin.tenants._form', ['isCreate' => true])

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create company</button>
            <a href="{{ route('admin.tenants.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
@endsection
