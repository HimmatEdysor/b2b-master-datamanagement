@extends('layouts.website')

@section('title', 'Blog')
@section('meta_description', 'Updates, guides, and announcements from Guarantee Admit.')

@section('content')
<section class="page-hero blog-hero">
    <div class="container">
        <h1>Blog</h1>
        <p class="lead">Updates, guides, and announcements for education consultancies on our platform.</p>
    </div>
</section>

<section class="section blog-list-section">
    <div class="container">
        @if($posts->isEmpty())
            <div class="empty-state">
                <p>No published posts yet. Check back soon.</p>
            </div>
        @else
            <div class="blog-grid">
                @foreach($posts as $post)
                    @include('partials.blog-card', ['post' => $post])
                @endforeach
            </div>

            @if($posts->hasPages())
                <nav class="pagination" aria-label="Blog pagination">
                    @if($posts->onFirstPage())
                        <span class="pagination-disabled">&laquo; Prev</span>
                    @else
                        <a href="{{ $posts->previousPageUrl() }}">&laquo; Prev</a>
                    @endif

                    @foreach($posts->getUrlRange(1, $posts->lastPage()) as $page => $url)
                        @if($page == $posts->currentPage())
                            <span class="active">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if($posts->hasMorePages())
                        <a href="{{ $posts->nextPageUrl() }}">Next &raquo;</a>
                    @else
                        <span class="pagination-disabled">Next &raquo;</span>
                    @endif
                </nav>
            @endif
        @endif
    </div>
</section>
@endsection
