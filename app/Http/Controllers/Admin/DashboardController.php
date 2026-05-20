<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Tenant;
use App\Models\TenantOperationLog;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'total' => Tenant::count(),
            'active' => Tenant::where('status', 'active')->count(),
            'pending' => Tenant::where('status', 'pending')->count(),
            'provisioning' => Tenant::where('status', 'provisioning')->count(),
            'failed' => Tenant::where('status', 'failed')->count(),
            'suspended' => Tenant::where('status', 'suspended')->count(),
            'rejected' => Tenant::where('status', 'rejected')->count(),
            'blog_published' => BlogPost::where('status', 'published')->count(),
            'blog_draft' => BlogPost::where('status', 'draft')->count(),
        ];

        $pendingTenants = Tenant::query()
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $recentLogs = TenantOperationLog::query()
            ->with('tenant')
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'pendingTenants', 'recentLogs'));
    }
}
