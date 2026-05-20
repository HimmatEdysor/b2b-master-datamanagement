@php
    $excerpt = $post->cardExcerpt($excerptLimit ?? 160);
@endphp
<article class="blog-card">
    <a href="{{ route('blog.show', $post->slug) }}" class="blog-card-media">
        @if($post->hasFeaturedImage())
            <img src="{{ $post->featured_image }}" alt="{{ $post->title }}" loading="lazy" width="640" height="360">
        @else
            <div class="blog-card-media-placeholder" aria-hidden="true">
                <span>{{ Str::limit($post->title, 48) }}</span>
            </div>
        @endif
    </a>
    <div class="blog-card-body">
        <p class="blog-meta">
            @if($post->published_at)
                <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('M j, Y') }}</time>
            @endif
            @if($post->relationLoaded('author') && $post->author)
                <span class="blog-meta-sep">·</span>
                <span>{{ $post->author->name }}</span>
            @endif
        </p>
        <h3 class="blog-card-title">
            <a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a>
        </h3>
        @if($excerpt)
            <p class="blog-excerpt">{{ $excerpt }}</p>
        @endif
        <a href="{{ route('blog.show', $post->slug) }}" class="blog-card-read-more">Read article →</a>
    </div>
</article>
