<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\AdminSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->limit($request, strtolower($data['email']));

        $user = User::query()->where('email', strtolower($data['email']))->first();
        $hash = $user?->password ?? '$2y$12$8f59N6CziH0gJEaTrcgX9u5b6O7uRXZqB7GwUWUrUvYAIomx/u5ku';
        $valid = Hash::check($data['password'], $hash) && $user?->is_admin;
        if (! $valid) {
            abort(401, 'E-posta veya şifre doğrulanamadı.');
        }

        $token = Str::random(64);
        AdminSession::query()->where('expires_at', '<=', now())->delete();
        AdminSession::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(8),
        ]);

        return response()->json([
            'admin' => ['email' => $user->email],
            'sessionToken' => $token,
            'expiresIn' => 8 * 60 * 60,
        ]);
    }

    public function session(Request $request): JsonResponse
    {
        $admin = $request->attributes->get('admin');

        return response()->json(['authenticated' => true, 'admin' => ['email' => $admin->email]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('admin_session')->delete();

        return response()->json(['ok' => true]);
    }

    private function limit(Request $request, string $email): void
    {
        $client = (string) $request->header('X-Client-Key', 'unknown');
        $client = preg_match('/^[a-f0-9]{64}$/', $client) ? $client : 'unknown';
        $key = 'admin-login:'.hash('sha256', $client.'|'.$email);
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Çok fazla giriş denemesi.');
        RateLimiter::hit($key, 15 * 60);
    }
}
