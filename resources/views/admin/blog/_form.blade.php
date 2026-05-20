<div class="form-group">
    <label for="blog_title">Title</label>
    <input type="text" name="title" id="blog_title" class="form-control" value="{{ old('title', $post->title ?? '') }}" required>
    @error('title')<p class="form-hint" style="color:var(--danger)">{{ $message }}</p>@enderror
</div>

<div class="form-group">
    <label for="blog_slug">Slug</label>
    <p class="form-hint">Generated from title. Spaces become <code>-</code>. Duplicates get <code>-2</code> or a short unique id.</p>
    <p class="form-hint"><strong id="blog_slug_preview">/blog/{{ old('slug', $post->slug ?? '…') }}</strong></p>
    <input
        type="text"
        name="slug"
        id="blog_slug"
        class="form-control"
        value="{{ old('slug', $post->slug ?? '') }}"
        pattern="{{ \App\Support\BlogSlug::PATTERN }}"
        placeholder="auto from title"
        autocomplete="off"
        @if(isset($post)) data-manual="1" @endif
    >
    <button type="button" class="btn btn-outline btn-sm" id="blog_slug_sync_title" style="margin-top:8px">Regenerate from title</button>
    @error('slug')<p class="form-hint" style="color:var(--danger)">{{ $message }}</p>@enderror
</div>

@once
    @push('scripts')
        <script src="{{ asset('js/blog-slug-input.js') }}" defer></script>
    @endpush
@endonce

<div class="form-group">
    <label>Excerpt</label>
    <textarea name="excerpt" class="form-control" rows="3" maxlength="500">{{ old('excerpt', $post->excerpt ?? '') }}</textarea>
    <p class="form-hint">Short summary for blog listing cards (max 500 characters).</p>
    @error('excerpt')<p class="form-hint" style="color:var(--danger)">{{ $message }}</p>@enderror
</div>

<div class="form-group">
    <label>Body</label>
    <p class="form-hint" style="margin-bottom:8px">Rich text editor — use the image button or drag & drop to upload images.</p>
    <textarea
        id="blog_body"
        name="body"
        class="form-control tinymce-editor"
        rows="14"
        style="max-width:100%"
    >{{ old('body', $post->body ?? '') }}</textarea>
    @error('body')<p class="form-hint" style="color:var(--danger)">{{ $message }}</p>@enderror
</div>

@include('admin.partials.tinymce-scripts')

@include('partials.media-upload', [
    'name' => 'featured_image_file',
    'removeName' => 'remove_featured_image_file',
    'label' => 'Featured image',
    'existingUrl' => old('featured_image', $post->featured_image ?? null),
    'type' => 'image',
    'profile' => 'blog_featured',
])

<div class="form-group">
    <label>Status</label>
    <select name="status" class="form-control" style="max-width:200px">
        <option value="draft" @selected(old('status', $post->status ?? 'draft') === 'draft')>Draft</option>
        <option value="published" @selected(old('status', $post->status ?? '') === 'published')>Published</option>
    </select>
    @error('status')<p class="form-hint" style="color:var(--danger)">{{ $message }}</p>@enderror
</div>

<div class="form-group">
    <label>Meta title (SEO)</label>
    <input type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $post->meta_title ?? '') }}">
</div>

<div class="form-group">
    <label>Meta description (SEO)</label>
    <textarea name="meta_description" class="form-control" rows="1">{{ old('meta_description', $post->meta_description ?? '') }}</textarea>
</div>
