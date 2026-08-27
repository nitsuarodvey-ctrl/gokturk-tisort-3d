<?php

namespace Tests\Feature\Http\Controllers\Internal;

use App\Models\AdminSession;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const KEY = 'test-internal-key-with-enough-entropy';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.internal_api_key' => self::KEY]);
    }

    public function test_invalid_credentials_return_a_generic_error(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $this->withHeaders($this->headers('d'))->postJson('/api/internal/v1/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'E-posta veya şifre doğrulanamadı.');
    }

    public function test_non_admin_user_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $this->withHeaders($this->headers('e'))->postJson('/api/internal/v1/admin/login', [
            'email' => 'member@example.com',
            'password' => 'correct-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'E-posta veya şifre doğrulanamadı.');
    }

    public function test_admin_can_log_in_check_session_and_log_out(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('correct-password'),
        ]);
        $headers = $this->headers('f');

        $login = $this->withHeaders($headers)->postJson('/api/internal/v1/admin/login', [
            'email' => 'ADMIN@example.com',
            'password' => 'correct-password',
        ])->assertOk()
            ->assertJsonPath('admin.email', 'admin@example.com')
            ->assertJsonPath('expiresIn', 28800);

        $token = $login->json('sessionToken');
        $this->assertIsString($token);
        $this->assertDatabaseHas('admin_sessions', [
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $token),
        ]);
        $this->assertDatabaseMissing('admin_sessions', ['token_hash' => $token]);

        $authenticatedHeaders = [...$headers, 'Authorization' => 'Bearer '.$token];
        $this->withHeaders($authenticatedHeaders)->getJson('/api/internal/v1/admin/session')
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('admin.email', 'admin@example.com');

        $this->withHeaders($authenticatedHeaders)->postJson('/api/internal/v1/admin/logout')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(0, AdminSession::query()->count());
    }

    /** @return array<string, string> */
    private function headers(string $clientSeed): array
    {
        return [
            'X-Internal-Key' => self::KEY,
            'X-Client-Key' => str_repeat($clientSeed, 64),
        ];
    }
}
