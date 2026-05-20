@extends('layouts.admin')
@section('title', 'Website blog')
@section('page-title', 'Website blog')

@section('content')
<div class="flex-between">
    <p class="page-lead" style="margin:0">Posts shown on the public website blog.</p>
    <a href="{{ route('admin.blog.create') }}" class="btn btn-primary">New post</a>
</div>

<div class="filters" style="margin-top:16px">
    <form method="GET" style="display:flex;gap:8px">
        <select name="status" class="form-control" style="max-width:160px">
            <option value="">All statuses</option>
            <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            <option value="published" @selected(request('status') === 'published')>Published</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    </form>
</div>

<div class="card" style="padding:0;overflow:hidden">
<table class="data-table">
    <thead>
        <tr>
            <th>Post</th>
            <th>Author</th>
            <th>Status</th>
            <th>Published</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    @forelse($posts as $post)
        <tr>
            <td>
                <strong>{{ $post->title }}</strong>
                <br><small style="color:var(--muted)">/blog/{{ $post->slug }}</small>
            </td>
            <td>{{ $post->author?->name ?? '—' }}</td>
            <td><span class="badge badge-{{ $post->status === 'published' ? 'published' : 'draft' }}">{{ $post->status }}</span></td>
            <td>{{ $post->published_at?->format('M j, Y') ?? '—' }}</td>
            <td>
                <a href="{{ route('admin.blog.edit', $post) }}">Edit</a>
                @if($post->status === 'published')
                    · <a href="{{ route('blog.show', $post->slug) }}" target="_blank">View</a>
                @endif
                ·
                <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" style="display:inline" onsubmit="return confirm('Delete this post?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0;font:inherit">Delete</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="5">No blog posts yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div style="padding:16px">{{ $posts->links() }}</div>
@endsection
