<?php

namespace App\Http\Middleware;

use App\Models\AdminSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! is_string($token) || strlen($token) < 32 || strlen($token) > 128) {
            abort(401, 'Unauthorized.');
        }

        $session = AdminSession::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->whereHas('user', fn ($query) => $query->where('is_admin', true))
            ->first();

        if (! $session) {
            abort(401, 'Unauthorized.');
        }

        $request->attributes->set('admin_session', $session);
        $request->attributes->set('admin', $session->user);

        return $next($request);
    }
}
