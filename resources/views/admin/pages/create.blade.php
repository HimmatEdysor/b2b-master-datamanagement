@extends('layouts.admin')
@section('title', 'Add page')
@section('page-title', 'Add custom page')

@section('content')
<div class="card">
    <form method="POST" action="{{ route('admin.pages.store') }}" data-tinymce-form>
        @csrf
        @include('admin.pages._form', ['page' => null])
        <p style="margin-top:20px">
            <button type="submit" class="btn btn-primary">Create page</button>
            <a href="{{ route('admin.pages.index') }}" class="btn btn-outline">Cancel</a>
        </p>
    </form>
</div>
@endsection
