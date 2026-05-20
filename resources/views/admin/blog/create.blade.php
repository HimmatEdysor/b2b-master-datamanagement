@extends('layouts.admin')
@section('title', 'New blog post')
@section('page-title', 'New blog post')

@section('content')
<div class="card">
    <form method="POST" action="{{ route('admin.blog.store') }}" data-tinymce-form enctype="multipart/form-data">
        @csrf
        @include('admin.blog._form', ['post' => null])
        <p style="margin-top:20px">
            <button type="submit" class="btn btn-primary">Create post</button>
            <a href="{{ route('admin.blog.index') }}" class="btn btn-outline">Cancel</a>
        </p>
    </form>
</div>
@endsection
