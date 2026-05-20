@extends('layouts.admin')
@section('title', 'Edit page')
@section('page-title', 'Edit: '.$page->title)

@section('content')
@if($page->status === 'published')
    <p style="margin-bottom:16px"><a href="{{ route('page.show', $page->slug) }}" target="_blank">View live page →</a></p>
@endif
<div class="card">
    <form method="POST" action="{{ route('admin.pages.update', $page) }}" data-tinymce-form>
        @csrf
        @method('PUT')
        @include('admin.pages._form')
        <p style="margin-top:20px">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="{{ route('admin.pages.index') }}" class="btn btn-outline">Back</a>
        </p>
    </form>
</div>
@endsection
