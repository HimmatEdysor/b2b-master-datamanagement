<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCrmApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('master.crm_api_token');

        if (! is_string($configured) || $configured === '') {
            return response()->json([
                'success' => false,
                'message' => 'Master API token is not configured.',
            ], 503);
        }

        $token = $request->bearerToken()
            ?? $request->header('X-Master-Api-Token');

        if (! is_string($token) || ! hash_equals($configured, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
