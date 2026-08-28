<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentGatewayException;
use App\Http\Requests\KuveytTurkCallbackRequest;
use App\Models\PaymentAttempt;
use App\Payments\KuveytTurkGateway;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PaymentCallbackController extends Controller
{
    public function store(KuveytTurkCallbackRequest $request, KuveytTurkGateway $gateway): RedirectResponse
    {
        try {
            $authentication = $gateway->authenticate($request->validated('AuthenticationResponse'));
        } catch (PaymentGatewayException) {
            return $this->returnToStore('failed');
        }

        $attempt = PaymentAttempt::query()
            ->with('order')
            ->where('merchant_order_id', $authentication['merchantOrderId'])
            ->first();
        if (! $attempt || ! $authentication['hashValid']) {
            return $this->returnToStore('failed');
        }

        try {
            return Cache::lock('payment-callback-'.$attempt->id, 30)->block(5, function () use ($attempt, $authentication, $gateway): RedirectResponse {
                $attempt->refresh();

                if ($attempt->status === PaymentAttempt::STATUS_PAID) {
                    return $this->returnToStore('paid', $attempt);
                }
                if ($attempt->status === PaymentAttempt::STATUS_FAILED) {
                    return $this->returnToStore('failed', $attempt);
                }
                if ($authentication['merchantId'] !== (string) config('services.kuveyt_turk.merchant_id') || $authentication['amount'] !== $attempt->amount) {
                    $attempt->update(['status' => PaymentAttempt::STATUS_UNKNOWN]);

                    return $this->returnToStore('unknown', $attempt);
                }
                if ($attempt->status !== PaymentAttempt::STATUS_AWAITING_3D) {
                    return $this->returnToStore('unknown', $attempt);
                }

                if ($authentication['responseCode'] !== '00' || $authentication['md'] === '') {
                    $attempt->update([
                        'status' => PaymentAttempt::STATUS_FAILED,
                        'gateway_order_id' => $authentication['orderId'],
                        'response_code' => $authentication['responseCode'],
                        'response_message' => $authentication['responseMessage'],
                        'reference_id' => $authentication['referenceId'] ?: null,
                        'business_key' => $authentication['businessKey'] ?: null,
                    ]);

                    return $this->returnToStore('failed', $attempt);
                }

                $attempt->update([
                    'status' => PaymentAttempt::STATUS_PROVISIONING,
                    'gateway_order_id' => $authentication['orderId'],
                    'response_code' => $authentication['responseCode'],
                    'response_message' => $authentication['responseMessage'],
                    'reference_id' => $authentication['referenceId'] ?: null,
                    'business_key' => $authentication['businessKey'] ?: null,
                ]);

                try {
                    $provision = $gateway->provision($attempt, $authentication['md']);
                } catch (PaymentGatewayException) {
                    $attempt->update([
                        'status' => PaymentAttempt::STATUS_UNKNOWN,
                        'response_message' => 'Provizyon sonucu alınamadı; banka panelinden kontrol edilmeli.',
                    ]);

                    return $this->returnToStore('unknown', $attempt);
                }

                $validProvision = $provision['hashValid']
                    && $provision['merchantOrderId'] === $attempt->merchant_order_id
                    && $provision['orderId'] === $authentication['orderId'];
                if (! $validProvision) {
                    $attempt->update([
                        'status' => PaymentAttempt::STATUS_UNKNOWN,
                        'response_message' => 'Provizyon yanıtı doğrulanamadı; banka panelinden kontrol edilmeli.',
                    ]);

                    return $this->returnToStore('unknown', $attempt);
                }

                if ($provision['responseCode'] !== '00') {
                    $attempt->update([
                        'status' => PaymentAttempt::STATUS_FAILED,
                        'provision_number' => $provision['provisionNumber'] ?: null,
                        'rrn' => $provision['rrn'] ?: null,
                        'stan' => $provision['stan'] ?: null,
                        'response_code' => $provision['responseCode'],
                        'response_message' => $provision['responseMessage'],
                        'business_key' => $provision['businessKey'] ?: null,
                    ]);

                    return $this->returnToStore('failed', $attempt);
                }

                DB::transaction(function () use ($attempt, $provision): void {
                    $attempt->update([
                        'status' => PaymentAttempt::STATUS_PAID,
                        'provision_number' => $provision['provisionNumber'] ?: null,
                        'rrn' => $provision['rrn'] ?: null,
                        'stan' => $provision['stan'] ?: null,
                        'response_code' => $provision['responseCode'],
                        'response_message' => $provision['responseMessage'],
                        'business_key' => $provision['businessKey'] ?: null,
                        'completed_at' => now(),
                    ]);
                    $attempt->order->update([
                        'payment_status' => 'paid',
                        'order_status' => 'confirmed',
                    ]);
                });

                return $this->returnToStore('paid', $attempt);
            });
        } catch (LockTimeoutException) {
            return $this->returnToStore('unknown', $attempt);
        }
    }

    private function returnToStore(string $status, ?PaymentAttempt $attempt = null): RedirectResponse
    {
        $returnUrl = (string) config('services.kuveyt_turk.return_url');
        $separator = str_contains($returnUrl, '?') ? '&' : '?';
        $query = ['status' => $status];
        if ($attempt) {
            $query['reference'] = $attempt->merchant_order_id;
        }

        return redirect()->away($returnUrl.$separator.http_build_query($query), 303);
    }
}
