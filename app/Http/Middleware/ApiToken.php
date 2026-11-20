<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** API integrasi ESS: nonaktif secara default, diamankan token (US-30). */
class ApiToken
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('blj.api.enabled') || blank(config('blj.api.token'))) {
            abort(404);
        }
        $token = (string) $request->bearerToken();
        if (! hash_equals((string) config('blj.api.token'), $token)) {
            return response()->json(['message' => 'Token tidak valid.'], 401);
        }

        return $next($request);
    }
}
