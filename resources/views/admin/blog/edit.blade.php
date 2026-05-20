@extends('layouts.admin')
@section('title', 'Edit blog post')
@section('page-title', 'Edit: '.$post->title)

@section('content')
@if($post->status === 'published')
    <p style="margin-bottom:16px"><a href="{{ route('blog.show', $post->slug) }}" target="_blank">View live post →</a></p>
@endif
<div class="card">
    <form method="POST" action="{{ route('admin.blog.update', $post) }}" data-tinymce-form enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.blog._form')
        <p style="margin-top:20px">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="{{ route('admin.blog.index') }}" class="btn btn-outline">Back</a>
        </p>
    </form>
</div>
@endsection
