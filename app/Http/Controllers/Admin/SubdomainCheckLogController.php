<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSubdomainCheckLog;
use App\Models\TenantSubdomainCheckStat;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubdomainCheckLogController extends Controller
{
    public function index(Request $request): View
    {
        $q = $request->string('q')->trim()->toString();

        $statsQuery = TenantSubdomainCheckStat::query()
            ->with('tenant')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('host', 'like', '%'.$q.'%')
                        ->orWhere('slug', 'like', '%'.$q.'%');
                });
            })
            ->orderByDesc('last_checked_at')
            ->orderByDesc('check_count');

        $stats = $statsQuery->paginate(25)->withQueryString();

        $totals = [
            'hosts' => (int) TenantSubdomainCheckStat::query()->count(),
            'checks' => (int) TenantSubdomainCheckStat::query()->sum('check_count'),
            'allowed' => (int) TenantSubdomainCheckStat::query()->sum('allowed_count'),
            'denied' => (int) TenantSubdomainCheckStat::query()->sum('denied_count'),
        ];

        $recentLogs = TenantSubdomainCheckLog::query()
            ->with('tenant')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('host', 'like', '%'.$q.'%')
                        ->orWhere('slug', 'like', '%'.$q.'%');
                });
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('admin.logs.subdomain-checks', compact('stats', 'totals', 'recentLogs', 'q'));
    }

    public function show(Request $request, string $host): View
    {
        $host = strtolower(trim($host));
        $stat = TenantSubdomainCheckStat::query()
            ->with('tenant')
            ->where('host', $host)
            ->firstOrFail();

        $logs = TenantSubdomainCheckLog::query()
            ->where('host', $host)
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.logs.subdomain-check-host', compact('stat', 'logs', 'host'));
    }
}
