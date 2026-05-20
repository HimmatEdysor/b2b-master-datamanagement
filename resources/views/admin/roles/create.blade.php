@extends('layouts.admin')
@section('title', 'Add role')
@section('page-title', 'Add role')

@section('content')
<form method="POST" action="{{ route('admin.roles.store') }}" class="card form-card">
    @csrf
    @include('admin.roles._form', ['permissionGroups' => $permissionGroups])
    <button type="submit" class="btn btn-primary">Create role</button>
    <a href="{{ route('admin.roles.index') }}" class="btn btn-outline">Back</a>
</form>
@endsection
