<?php

namespace App\Http\Controllers\Internal;

use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartPaymentRequest;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Payments\KuveytTurkGateway;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function store(StartPaymentRequest $request, KuveytTurkGateway $gateway): Response
    {
        $this->limit($request);
        $data = $request->validated();
        $order = Order::query()->findOrFail($data['orderId']);

        abort_if($order->payment_status === 'paid', 409, 'Bu sipariş zaten ödendi.');
        abort_if($order->order_status === 'cancelled', 409, 'İptal edilmiş sipariş ödenemez.');

        try {
            return Cache::lock('payment-init-order-'.$order->id, 15)->block(3, function () use ($data, $gateway, $order): Response {
                $order->refresh();
                abort_if($order->payment_status === 'paid', 409, 'Bu sipariş zaten ödendi.');
                abort_if($order->order_status === 'cancelled', 409, 'İptal edilmiş sipariş ödenemez.');

                $hasActiveAttempt = $order->paymentAttempts()
                    ->where(function ($query): void {
                        $query->where('status', PaymentAttempt::STATUS_UNKNOWN)
                            ->orWhere(function ($query): void {
                                $query->whereIn('status', [
                                    PaymentAttempt::STATUS_INITIATED,
                                    PaymentAttempt::STATUS_AWAITING_3D,
                                    PaymentAttempt::STATUS_PROVISIONING,
                                ])->where('created_at', '>=', now()->subMinutes(15));
                            });
                    })
                    ->exists();
                abort_if($hasActiveAttempt, 409, 'Bu sipariş için devam eden bir ödeme işlemi var.');

                $attempt = PaymentAttempt::create([
                    'order_id' => $order->id,
                    'merchant_order_id' => 'GUB'.now()->format('YmdHis').Str::upper(Str::random(10)),
                    'amount' => $order->total * 100,
                    'currency_code' => '0949',
                    'status' => PaymentAttempt::STATUS_INITIATED,
                ]);

                try {
                    $html = $gateway->start($attempt, $order, $data);
                } catch (PaymentGatewayException $exception) {
                    $attempt->update([
                        'status' => PaymentAttempt::STATUS_FAILED,
                        'response_message' => $exception->getMessage(),
                    ]);
                    abort(502, $exception->getMessage());
                }

                $attempt->update(['status' => PaymentAttempt::STATUS_AWAITING_3D]);

                return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            });
        } catch (LockTimeoutException) {
            abort(409, 'Ödeme işlemi hazırlanıyor. Lütfen tekrar deneyin.');
        }
    }

    private function limit(Request $request): void
    {
        $client = (string) $request->header('X-Client-Key', 'unknown');
        $client = preg_match('/^[a-f0-9]{64}$/', $client) ? $client : 'unknown';
        $key = 'payment-start:'.$client;
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Çok fazla ödeme denemesi.');
        RateLimiter::hit($key, 10 * 60);
    }
}
