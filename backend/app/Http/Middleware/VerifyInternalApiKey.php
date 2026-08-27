<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.internal_api_key');
        $provided = (string) $request->header('X-Internal-Key');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            abort(401, 'Unauthorized.');
        }

        return $next($request);
    }
}
