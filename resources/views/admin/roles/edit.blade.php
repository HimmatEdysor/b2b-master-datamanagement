@extends('layouts.admin')
@section('title', 'Edit role')
@section('page-title', 'Edit role: '.$role->name)

@section('content')
<form method="POST" action="{{ route('admin.roles.update', $role) }}" class="card form-card">
    @csrf
    @method('PUT')
    @include('admin.roles._form', ['protected' => $protected ?? $role->isProtected(), 'permissionGroups' => $permissionGroups ?? []])
    @if(empty($protected) && ! $role->isProtected())
        <button type="submit" class="btn btn-primary">Save role</button>
    @endif
    <a href="{{ route('admin.roles.index') }}" class="btn btn-outline">Back</a>
</form>
@endsection
