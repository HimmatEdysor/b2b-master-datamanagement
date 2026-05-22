<?php

namespace App\Http\Middleware;

use App\Support\MasterAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        foreach (explode(',', $permission) as $name) {
            if (MasterAuth::can(trim($name))) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to perform this action.');
    }
}
