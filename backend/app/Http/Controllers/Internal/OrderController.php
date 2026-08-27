<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class OrderController extends Controller
{
    private const UNIT_PRICE = 499;

    public function index(): JsonResponse
    {
        return response()->json([
            'orders' => OrderResource::collection(
                Order::query()->latest('created_at')->latest('id')->limit(1000)->get(),
            )->resolve(),
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json(['order' => OrderResource::make($order)->resolve()]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $this->limit($request, 'public-order', 5, 60);
        $data = $request->validated();

        $quantity = (int) $data['quantity'];
        $order = Order::create([
            'name' => trim($data['name']),
            'phone' => trim($data['phone']),
            'size' => $data['size'],
            'quantity' => $quantity,
            'delivery_type' => $data['deliveryType'],
            'city' => $data['city'] ?? null,
            'district' => $data['district'] ?? null,
            'address' => $data['address'] ?? null,
            'unit_price' => self::UNIT_PRICE,
            'total' => self::UNIT_PRICE * $quantity,
            'payment_status' => 'waiting',
            'order_status' => 'preorder',
            'production_status' => 'waiting',
            'delivery_status' => 'waiting',
        ]);

        return response()->json(['order' => OrderResource::make($order)->resolve()], 201);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $data = $request->validated();

        $map = [
            'paymentStatus' => 'payment_status',
            'orderStatus' => 'order_status',
            'productionStatus' => 'production_status',
            'deliveryStatus' => 'delivery_status',
            'notes' => 'notes',
        ];
        $changes = [];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $data)) {
                $changes[$column] = $data[$input];
            }
        }
        abort_if($changes === [], 422, 'Güncellenecek geçerli bir alan bulunamadı.');
        $order->update($changes);

        return response()->json(['order' => OrderResource::make($order->fresh())->resolve()]);
    }

    public function destroy(Order $order): JsonResponse
    {
        $order->delete();

        return response()->json(['ok' => true]);
    }

    private function limit(Request $request, string $scope, int $attempts, int $decay): void
    {
        $client = (string) $request->header('X-Client-Key', 'unknown');
        $client = preg_match('/^[a-f0-9]{64}$/', $client) ? $client : 'unknown';
        $key = $scope.':'.$client;
        abort_if(RateLimiter::tooManyAttempts($key, $attempts), 429, 'Çok fazla istek.');
        RateLimiter::hit($key, $decay);
    }
}
