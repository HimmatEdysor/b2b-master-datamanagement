@extends('layouts.website')

@section('title', $page->meta_title ?: $page->title)
@section('meta_description', $page->meta_description ?: Str::limit(strip_tags($page->body ?? ''), 160))

@section('content')
<section class="section" style="padding-top: 48px;">
    <div class="container">
        <article class="post-article">
            <h1>{{ $page->title }}</h1>
            <div class="post-body cms-content">
                {!! $page->body !!}
            </div>
        </article>
    </div>
</section>
@endsection
