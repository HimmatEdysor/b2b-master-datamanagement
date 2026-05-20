@extends('layouts.admin')
@section('title', 'Add user')
@section('page-title', 'Add admin user')

@section('content')
<form method="POST" action="{{ route('admin.users.store') }}" class="card" style="padding:20px;max-width:520px">
    @csrf
    @include('admin.users._form')
    <button type="submit" class="btn btn-primary">Create user</button>
</form>
@endsection
