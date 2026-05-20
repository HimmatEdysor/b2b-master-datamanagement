<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesAdminMedia;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\AdminMediaService;
use App\Support\BlogSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    use HandlesAdminMedia;

    public function index(Request $request): View
    {
        $posts = BlogPost::query()
            ->with('author')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.blog.index', compact('posts'));
    }

    public function create(): View
    {
        return view('admin.blog.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->resolveSlug($request);
        $validated['author_id'] = Auth::id();

        if ($validated['status'] === 'published' && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $validated['featured_image'] = $this->resolveMediaUrl(
            $request,
            'featured_image_file',
            'remove_featured_image_file',
            null,
            'blog_featured',
            $validated['slug']
        );

        $post = BlogPost::create($validated);

        return redirect()->route('admin.blog.edit', $post)->with('success', 'Blog post created.');
    }

    public function edit(BlogPost $blog): View
    {
        return view('admin.blog.edit', ['post' => $blog]);
    }

    public function update(Request $request, BlogPost $blog): RedirectResponse
    {
        $validated = $this->validated($request, $blog->id);

        if ($validated['status'] === 'published' && ! $blog->published_at) {
            $validated['published_at'] = now();
        }

        $validated['slug'] = $this->resolveSlug($request, $blog);

        $validated['featured_image'] = $this->resolveMediaUrl(
            $request,
            'featured_image_file',
            'remove_featured_image_file',
            $blog->featured_image,
            'blog_featured',
            $validated['slug']
        );

        $blog->update($validated);

        return back()->with('success', 'Blog post updated.');
    }

    public function destroy(BlogPost $blog): RedirectResponse
    {
        app(AdminMediaService::class)->deleteIfStored($blog->featured_image);
        $blog->delete();

        return redirect()->route('admin.blog.index')->with('success', 'Blog post deleted.');
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate(array_merge([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^'.BlogSlug::PATTERN.'$/'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,published'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ], $this->mediaFileRules('blog_featured', 'featured_image_file')));
    }

    protected function resolveSlug(Request $request, ?BlogPost $post = null): string
    {
        $title = (string) $request->input('title', '');
        $manual = trim((string) $request->input('slug', ''));

        $seed = $manual !== '' ? $manual : $title;

        return BlogSlug::unique($seed, $post?->id);
    }
}
