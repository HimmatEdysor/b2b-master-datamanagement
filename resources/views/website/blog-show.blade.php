@extends('layouts.website')

@section('title', $post->seoTitle())
@section('meta_description', $post->seoDescription())

@push('head')
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $post->seoTitle() }}">
    <meta property="og:description" content="{{ $post->seoDescription() }}">
    <meta property="og:url" content="{{ route('blog.show', $post->slug) }}">
    @if($post->hasFeaturedImage())
        <meta property="og:image" content="{{ $post->featured_image }}">
    @endif
    @if($post->published_at)
        <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
    @endif
@endpush

@section('content')
<section class="section blog-post-section">
    <div class="container blog-post-container">
        <nav class="blog-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('blog.index') }}">Blog</a>
            <span aria-hidden="true">/</span>
            <span>{{ $post->title }}</span>
        </nav>

        <article class="post-article post-article-single">
            <header class="post-header">
                <p class="blog-meta post-meta">
                    @if($post->published_at)
                        <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('F j, Y') }}</time>
                    @endif
                    @if($post->author)
                        <span class="blog-meta-sep">·</span>
                        <span>{{ $post->author->name }}</span>
                    @endif
                </p>
                <h1 class="post-title">{{ $post->title }}</h1>
                @if(filled($post->excerpt))
                    <p class="post-lead">{{ $post->excerpt }}</p>
                @endif
            </header>

            @if($post->hasFeaturedImage())
                <figure class="post-featured">
                    <img src="{{ $post->featured_image }}" alt="{{ $post->title }}" width="1200" height="630">
                </figure>
            @endif

            @if(filled($post->body))
                <div class="post-body cms-content">
                    {!! $post->body !!}
                </div>
            @else
                <p class="post-empty-body text-muted">Full article content is coming soon.</p>
            @endif
        </article>

        <p class="blog-back-link">
            <a href="{{ route('blog.index') }}">← Back to all posts</a>
        </p>
    </div>
</section>
@endsection
