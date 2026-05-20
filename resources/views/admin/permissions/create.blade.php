@extends('layouts.admin')
@section('title', 'Add permission')
@section('page-title', 'Add permission')

@section('content')
<form method="POST" action="{{ route('admin.permissions.store') }}" class="card form-card">
    @csrf
    @include('admin.permissions._form')
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="{{ route('admin.permissions.index') }}" class="btn btn-outline">Back</a>
</form>
@endsection
