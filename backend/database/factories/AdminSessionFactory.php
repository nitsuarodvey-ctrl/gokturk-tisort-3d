<?php

namespace Database\Factories;

use App\Models\AdminSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminSession>
 */
class AdminSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->admin(),
            'token_hash' => hash('sha256', fake()->unique()->uuid()),
            'expires_at' => now()->addHours(8),
        ];
    }
}
