<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyProvisionToken
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->isJson()) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_request',
                'details' => ['Request content type must be application/json.'],
            ], 415);
        }

        $configuredToken = (string) env('LINKSTACK_PROVISION_TOKEN', '');
        $providedToken = (string) $request->bearerToken();

        if ($configuredToken === '' || !hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'ok' => false,
                'error' => 'unauthorized',
            ], 401);
        }

        return $next($request);
    }
}
