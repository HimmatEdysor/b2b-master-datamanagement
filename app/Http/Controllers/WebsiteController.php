<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\SubscriptionPlan;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function home(): View
    {
        $posts = BlogPost::query()
            ->with('author:id,name')
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        $services = collect(config('website.services', []))->take(6);
        $stats = config('website.stats', []);
        $plans = SubscriptionPlan::query()->activeOrdered()->limit(3)->get();

        return view('website.home', compact('posts', 'services', 'stats', 'plans'));
    }

    public function services(): View
    {
        $services = config('website.services', []);

        return view('website.services', compact('services'));
    }

    public function blog(): View
    {
        $posts = BlogPost::query()
            ->with('author:id,name')
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->paginate(9);

        return view('website.blog', compact('posts'));
    }

    public function blogShow(string $slug): View
    {
        $post = BlogPost::query()
            ->with('author:id,name')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return view('website.blog-show', compact('post'));
    }

    public function pricing(): View
    {
        $plans = SubscriptionPlan::query()->activeOrdered()->get();

        return view('website.pricing', compact('plans'));
    }

    public function page(string $slug): View
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        return view('website.page', compact('page'));
    }
}
