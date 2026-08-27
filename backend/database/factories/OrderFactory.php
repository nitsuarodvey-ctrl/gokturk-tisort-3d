<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);

        return [
            'name' => fake()->name(),
            'phone' => '05'.fake()->numerify('#########'),
            'size' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'quantity' => $quantity,
            'delivery_type' => 'Genel Merkezden Teslim',
            'city' => null,
            'district' => null,
            'address' => null,
            'unit_price' => 499,
            'total' => 499 * $quantity,
            'payment_status' => 'waiting',
            'order_status' => 'preorder',
            'production_status' => 'waiting',
            'delivery_status' => 'waiting',
            'notes' => null,
        ];
    }
}
