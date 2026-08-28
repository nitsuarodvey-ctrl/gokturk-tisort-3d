<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentAttempt>
 */
class PaymentAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'merchant_order_id' => 'GUB'.now()->format('YmdHis').Str::upper(fake()->unique()->bothify('??####??')),
            'amount' => 49900,
            'currency_code' => '0949',
            'status' => PaymentAttempt::STATUS_AWAITING_3D,
            'gateway_order_id' => null,
            'provision_number' => null,
            'rrn' => null,
            'stan' => null,
            'response_code' => null,
            'response_message' => null,
            'reference_id' => null,
            'business_key' => null,
            'completed_at' => null,
        ];
    }
}
