@extends('layouts.admin')
@section('title', 'Custom pages')
@section('page-title', 'Custom pages')

@section('content')
<div class="flex-between">
    <p style="margin:0;color:var(--muted)">About, privacy, terms, and other static pages for the public website.</p>
    <a href="{{ route('admin.pages.create') }}" class="btn btn-primary">Add page</a>
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
            <th>Page</th>
            <th>Nav</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    @forelse($pages as $page)
        <tr>
            <td>
                <strong>{{ $page->title }}</strong>
                <br><small style="color:var(--muted)">/pages/{{ $page->slug }}</small>
            </td>
            <td>{{ $page->show_in_nav ? 'Yes' : '—' }}</td>
            <td><span class="badge badge-{{ $page->status === 'published' ? 'published' : 'draft' }}">{{ $page->status }}</span></td>
            <td>
                <a href="{{ route('admin.pages.edit', $page) }}">Edit</a>
                @if($page->status === 'published')
                    · <a href="{{ route('page.show', $page->slug) }}" target="_blank">View</a>
                @endif
                ·
                <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" style="display:inline" onsubmit="return confirm('Delete this page?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0;font:inherit">Delete</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="4">No pages yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<div style="padding:16px">{{ $pages->links() }}</div>
@endsection
