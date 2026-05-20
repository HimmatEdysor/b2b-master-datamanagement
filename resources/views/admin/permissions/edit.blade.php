@extends('layouts.admin')
@section('title', 'Edit permission')
@section('page-title', 'Edit permission')

@section('content')
<form method="POST" action="{{ route('admin.permissions.update', $permission) }}" class="card form-card">
    @csrf
    @method('PUT')
    @include('admin.permissions._form')
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="{{ route('admin.permissions.index') }}" class="btn btn-outline">Back</a>
</form>
@endsection
