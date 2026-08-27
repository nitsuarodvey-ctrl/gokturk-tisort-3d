<?php

namespace Tests\Feature\Http\Controllers\Internal;

use App\Models\AdminSession;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const KEY = 'test-internal-key-with-enough-entropy';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.internal_api_key' => self::KEY]);
    }

    public function test_internal_key_is_required(): void
    {
        $this->postJson('/api/internal/v1/orders', [])->assertUnauthorized();
    }

    public function test_public_order_is_created_with_server_controlled_financial_fields(): void
    {
        $response = $this->withHeaders($this->internalHeaders('a'))->postJson('/api/internal/v1/orders', [
            'name' => 'Test Kullanıcı',
            'phone' => '05551112233',
            'size' => 'M',
            'quantity' => 2,
            'deliveryType' => 'Genel Merkezden Teslim',
            'unitPrice' => 1,
            'total' => 1,
            'paymentStatus' => 'paid',
        ]);

        $response->assertCreated()
            ->assertJsonPath('order.total', 998)
            ->assertJsonPath('order.unitPrice', 499)
            ->assertJsonPath('order.paymentStatus', 'waiting')
            ->assertJsonPath('order.orderStatus', 'preorder');

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('order.id'),
            'total' => 998,
            'payment_status' => 'waiting',
        ]);
    }

    public function test_shipping_address_is_required_for_cargo_delivery(): void
    {
        $this->withHeaders($this->internalHeaders('b'))->postJson('/api/internal/v1/orders', [
            'name' => 'Kargo Test',
            'phone' => '05551112233',
            'size' => 'L',
            'quantity' => 1,
            'deliveryType' => 'Adrese Kargo',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['city', 'district', 'address'])
            ->assertJsonPath('errors.city.0', 'Kargo teslimatı için şehir zorunludur.');
    }

    public function test_admin_session_protects_and_allows_order_crud(): void
    {
        $order = Order::factory()->create();
        $headers = $this->internalHeaders('c');

        $this->withHeaders($headers)->getJson('/api/internal/v1/orders')->assertUnauthorized();

        $token = str_repeat('secure-admin-token-', 4);
        $admin = User::factory()->admin()->create();
        AdminSession::factory()->create([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $token),
        ]);
        $authenticatedHeaders = [...$headers, 'Authorization' => 'Bearer '.$token];

        $this->withHeaders($authenticatedHeaders)->getJson('/api/internal/v1/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.id', $order->id);

        $this->withHeaders($authenticatedHeaders)->patchJson('/api/internal/v1/orders/'.$order->id, [
            'paymentStatus' => 'paid',
            'productionStatus' => 'queued',
        ])->assertOk()
            ->assertJsonPath('order.paymentStatus', 'paid')
            ->assertJsonPath('order.productionStatus', 'queued');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'production_status' => 'queued',
        ]);

        $this->withHeaders($authenticatedHeaders)->deleteJson('/api/internal/v1/orders/'.$order->id)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    /** @return array<string, string> */
    private function internalHeaders(string $clientSeed): array
    {
        return [
            'X-Internal-Key' => self::KEY,
            'X-Client-Key' => str_repeat($clientSeed, 64),
        ];
    }
}
