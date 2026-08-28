<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentCallbackControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PROVISION_URL = 'https://bank.test/ThreeDModelProvisionGate';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.kuveyt_turk' => [
            'enabled' => true,
            'mode' => 'test',
            'customer_id' => '11111111',
            'merchant_id' => '99999',
            'username' => 'TESTUSER',
            'password' => 'test-password',
            'pay_url' => 'https://bank.test/ThreeDModelPayGate',
            'provision_url' => self::PROVISION_URL,
            'callback_url' => 'https://api.example.com/api/v1/payments/kuveyt-turk/callback',
            'return_url' => 'https://shop.example.com/payment/result',
        ]]);
    }

    public function test_valid_bank_callback_provisions_and_marks_order_paid(): void
    {
        [$order, $attempt] = $this->attempt();
        Http::preventStrayRequests();
        Http::fake([
            self::PROVISION_URL => Http::response($this->provisionXml($attempt, '00', true)),
        ]);

        $response = $this->post('/api/v1/payments/kuveyt-turk/callback', [
            'AuthenticationResponse' => $this->authenticationXml($attempt, '00', true),
        ]);

        $response->assertRedirect('https://shop.example.com/payment/result?status=paid&reference='.$attempt->merchant_order_id);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'order_status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => PaymentAttempt::STATUS_PAID,
            'response_code' => '00',
            'provision_number' => '123456',
        ]);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::PROVISION_URL
            && str_contains($request->body(), '<Key>MD</Key>')
            && str_contains($request->body(), '<Data>signed-md</Data>'));
    }

    public function test_invalid_authentication_hash_does_not_provision_or_change_order(): void
    {
        [$order, $attempt] = $this->attempt();
        Http::preventStrayRequests();
        Http::fake([self::PROVISION_URL => Http::response('unexpected')]);

        $this->post('/api/v1/payments/kuveyt-turk/callback', [
            'AuthenticationResponse' => $this->authenticationXml($attempt, '00', false),
        ])->assertRedirect('https://shop.example.com/payment/result?status=failed');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'waiting']);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => PaymentAttempt::STATUS_AWAITING_3D,
        ]);
        Http::assertNothingSent();
    }

    public function test_valid_decline_marks_attempt_failed_without_provisioning(): void
    {
        [$order, $attempt] = $this->attempt();
        Http::preventStrayRequests();
        Http::fake([self::PROVISION_URL => Http::response('unexpected')]);

        $this->post('/api/v1/payments/kuveyt-turk/callback', [
            'AuthenticationResponse' => $this->authenticationXml($attempt, '05', true, ''),
        ])->assertRedirect('https://shop.example.com/payment/result?status=failed&reference='.$attempt->merchant_order_id);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'waiting']);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => PaymentAttempt::STATUS_FAILED,
            'response_code' => '05',
        ]);
        Http::assertNothingSent();
    }

    public function test_unverified_provision_response_is_marked_unknown_without_changing_order(): void
    {
        [$order, $attempt] = $this->attempt();
        Http::preventStrayRequests();
        Http::fake([
            self::PROVISION_URL => Http::response($this->provisionXml($attempt, '00', false)),
        ]);

        $this->post('/api/v1/payments/kuveyt-turk/callback', [
            'AuthenticationResponse' => $this->authenticationXml($attempt, '00', true),
        ])->assertRedirect('https://shop.example.com/payment/result?status=unknown&reference='.$attempt->merchant_order_id);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'waiting']);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => PaymentAttempt::STATUS_UNKNOWN,
        ]);
    }

    /** @return array{0: Order, 1: PaymentAttempt} */
    private function attempt(): array
    {
        $order = Order::factory()->create(['total' => 499]);
        $attempt = PaymentAttempt::factory()->for($order)->create([
            'merchant_order_id' => 'GUB202608280001',
            'amount' => 49900,
            'status' => PaymentAttempt::STATUS_AWAITING_3D,
        ]);

        return [$order, $attempt];
    }

    private function authenticationXml(PaymentAttempt $attempt, string $responseCode, bool $validHash, string $md = 'signed-md'): string
    {
        $orderId = '987654';
        $hash = $this->hash([
            $attempt->merchant_order_id,
            $responseCode,
            $orderId,
            $this->hashedPassword(),
        ]);
        if (! $validHash) {
            $hash = 'invalid-hash';
        }

        return '<VPosTransactionResponseContract><VPosMessage>'
            .'<MerchantId>99999</MerchantId><Amount>'.$attempt->amount.'</Amount>'
            .'<MerchantOrderId>'.$attempt->merchant_order_id.'</MerchantOrderId></VPosMessage>'
            .'<ResponseCode>'.$responseCode.'</ResponseCode><ResponseMessage>Bank response</ResponseMessage>'
            .'<OrderId>'.$orderId.'</OrderId><HashData>'.$hash.'</HashData><MD>'.$md.'</MD>'
            .'<ReferenceId>reference-1</ReferenceId><BusinessKey>business-1</BusinessKey>'
            .'</VPosTransactionResponseContract>';
    }

    private function provisionXml(PaymentAttempt $attempt, string $responseCode, bool $validHash): string
    {
        $orderId = '987654';
        $rrn = '123456789012';
        $hash = $this->hash([
            $attempt->merchant_order_id,
            $rrn,
            $responseCode,
            $orderId,
            $this->hashedPassword(),
        ]);
        if (! $validHash) {
            $hash = 'invalid-hash';
        }

        return '<VPosTransactionResponseContract><VPosMessage>'
            .'<MerchantOrderId>'.$attempt->merchant_order_id.'</MerchantOrderId></VPosMessage>'
            .'<ProvisionNumber>123456</ProvisionNumber><RRN>'.$rrn.'</RRN><Stan>654321</Stan>'
            .'<ResponseCode>'.$responseCode.'</ResponseCode><ResponseMessage>OTORİZASYON VERİLDİ</ResponseMessage>'
            .'<OrderId>'.$orderId.'</OrderId><HashData>'.$hash.'</HashData><BusinessKey>business-2</BusinessKey>'
            .'</VPosTransactionResponseContract>';
    }

    /** @param array<int, string> $parts */
    private function hash(array $parts): string
    {
        return base64_encode(sha1(mb_convert_encoding(implode('', $parts), 'ISO-8859-9', 'UTF-8'), true));
    }

    private function hashedPassword(): string
    {
        return $this->hash(['test-password']);
    }
}
