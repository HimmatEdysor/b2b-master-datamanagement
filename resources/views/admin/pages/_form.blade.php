<div class="form-group">
    <label>Title</label>
    <input type="text" name="title" class="form-control" value="{{ old('title', $page->title ?? '') }}" required>
    @error('title')<p class="form-hint" style="color:var(--danger)">{{ $message }}</p>@enderror
</div>

<div class="form-group">
    <label>Slug (URL: /pages/{slug})</label>
    <input type="text" name="slug" class="form-control" value="{{ old('slug', $page->slug ?? '') }}" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="about-us">
    @error('slug')<p class="form-hint" style="color:var(--danger)">{{ $message }}</p>@enderror
</div>

<div class="form-group">
    <label>Body</label>
    <p class="form-hint" style="margin-bottom:8px">Rich text editor — use the image button or drag & drop to upload images.</p>
    <textarea
        id="page_body"
        name="body"
        class="form-control tinymce-editor"
        rows="12"
        style="max-width:100%"
    >{{ old('body', $page->body ?? '') }}</textarea>
    @error('body')<p class="form-hint" style="color:var(--danger)">{{ $message }}</p>@enderror
</div>

@include('admin.partials.tinymce-scripts')

<div class="form-group" style="display:flex;gap:16px;flex-wrap:wrap">
    <div style="flex:1;min-width:160px">
        <label>Status</label>
        <select name="status" class="form-control">
            <option value="draft" @selected(old('status', $page->status ?? 'draft') === 'draft')>Draft</option>
            <option value="published" @selected(old('status', $page->status ?? '') === 'published')>Published</option>
        </select>
    </div>
    <div style="flex:1;min-width:120px">
        <label>Sort order</label>
        <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', $page->sort_order ?? 0) }}">
    </div>
</div>

<div class="form-group">
    <label><input type="checkbox" name="show_in_nav" value="1" @checked(old('show_in_nav', $page->show_in_nav ?? false))> Show in website header navigation</label>
</div>

<div class="form-group">
    <label>Meta title (SEO)</label>
    <input type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $page->meta_title ?? '') }}">
</div>

<div class="form-group">
    <label>Meta description (SEO)</label>
    <textarea name="meta_description" class="form-control" rows="1">{{ old('meta_description', $page->meta_description ?? '') }}</textarea>
</div>
