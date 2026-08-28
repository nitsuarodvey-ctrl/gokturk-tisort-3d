<?php

namespace Tests\Feature\Http\Controllers\Internal;

use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const KEY = 'test-internal-key-with-enough-entropy';

    private const PAY_URL = 'https://bank.test/ThreeDModelPayGate';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.internal_api_key' => self::KEY,
            'services.kuveyt_turk' => [
                'enabled' => true,
                'mode' => 'test',
                'customer_id' => '11111111',
                'merchant_id' => '99999',
                'username' => 'TESTUSER',
                'password' => 'test-password',
                'pay_url' => self::PAY_URL,
                'provision_url' => 'https://bank.test/ThreeDModelProvisionGate',
                'callback_url' => 'https://api.example.com/api/v1/payments/kuveyt-turk/callback',
                'return_url' => 'https://shop.example.com/payment/result',
            ],
        ]);
    }

    public function test_returns_401_without_internal_key(): void
    {
        $this->postJson('/api/internal/v1/payments', [])->assertUnauthorized();
    }

    public function test_valid_request_creates_attempt_from_server_total_and_returns_bank_html(): void
    {
        $order = Order::factory()->create(['quantity' => 2, 'total' => 998]);
        Http::preventStrayRequests();
        Http::fake([self::PAY_URL => Http::response('<html><body>3D Secure</body></html>')]);

        $response = $this->withHeaders($this->headers())->postJson('/api/internal/v1/payments', $this->payload($order));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('3D Secure');
        $this->assertDatabaseHas('payment_attempts', [
            'order_id' => $order->id,
            'amount' => 99800,
            'status' => PaymentAttempt::STATUS_AWAITING_3D,
        ]);
        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return $request->url() === self::PAY_URL
                && $request->hasHeader('Content-Type', 'application/xml; charset=UTF-8')
                && str_contains($body, '<Amount>99800</Amount>')
                && str_contains($body, '<CardNumber>4111111111111111</CardNumber>')
                && str_contains($body, '<TransactionSecurity>3</TransactionSecurity>');
        });
    }

    public function test_invalid_card_returns_422_without_contacting_bank(): void
    {
        $order = Order::factory()->create();
        Http::preventStrayRequests();
        Http::fake([self::PAY_URL => Http::response('unexpected')]);
        $payload = $this->payload($order);
        $payload['cardNumber'] = '4111111111111112';

        $this->withHeaders($this->headers())->postJson('/api/internal/v1/payments', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cardNumber')
            ->assertJsonPath('errors.cardNumber.0', 'Kart numarası geçersiz.');

        $this->assertDatabaseCount('payment_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_active_attempt_returns_409_without_second_bank_request(): void
    {
        $order = Order::factory()->create();
        PaymentAttempt::factory()->for($order)->create([
            'status' => PaymentAttempt::STATUS_AWAITING_3D,
            'created_at' => now(),
        ]);
        Http::preventStrayRequests();
        Http::fake([self::PAY_URL => Http::response('unexpected')]);

        $this->withHeaders($this->headers())->postJson('/api/internal/v1/payments', $this->payload($order))
            ->assertConflict()
            ->assertJsonPath('message', 'Bu sipariş için devam eden bir ödeme işlemi var.');

        $this->assertDatabaseCount('payment_attempts', 1);
        Http::assertNothingSent();
    }

    public function test_unknown_attempt_stays_blocked_until_admin_review(): void
    {
        $order = Order::factory()->create();
        PaymentAttempt::factory()->for($order)->create([
            'status' => PaymentAttempt::STATUS_UNKNOWN,
            'created_at' => now()->subDay(),
        ]);
        Http::preventStrayRequests();

        $this->withHeaders($this->headers())->postJson('/api/internal/v1/payments', $this->payload($order))
            ->assertConflict()
            ->assertJsonPath('message', 'Bu sipariş için devam eden bir ödeme işlemi var.');

        $this->assertDatabaseCount('payment_attempts', 1);
        Http::assertNothingSent();
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'X-Internal-Key' => self::KEY,
            'X-Client-Key' => str_repeat('a', 64),
            'X-Client-IP' => '203.0.113.10',
        ];
    }

    /** @return array<string, string> */
    private function payload(Order $order): array
    {
        return [
            'orderId' => $order->id,
            'cardHolderName' => 'Test Kullanici',
            'cardNumber' => '4111111111111111',
            'expiryMonth' => '12',
            'expiryYear' => '30',
            'cvv' => '123',
            'email' => 'test@example.com',
            'billingCity' => 'Izmir',
            'billingState' => '35',
            'billingPostalCode' => '35000',
            'billingAddress' => 'Test Mahallesi No 1',
        ];
    }
}
