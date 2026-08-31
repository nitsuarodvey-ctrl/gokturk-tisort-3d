<?php
declare(strict_types=1);

require_once __DIR__ . '/kuveyt-turk.php';

final class PaymentCallbackService
{
    public function __construct(private PDO $pdo, private KuveytTurkGateway $gateway, private array $config)
    {
    }

    public function process(string $authenticationResponse): array
    {
        try {
            $authentication = $this->gateway->authenticate($authenticationResponse);
        } catch (PaymentGatewayException $error) {
            return ['status' => 'rejected', 'reference' => null, 'reason' => 'malformed_response'];
        }
        if (!$authentication['hash_valid']) {
            return ['status' => 'rejected', 'reference' => null, 'reason' => 'invalid_signature'];
        }

        $this->pdo->beginTransaction();
        try {
            $attempt = $this->lockedAttempt($authentication['merchant_order_id']);
            if (!$attempt) {
                $this->pdo->rollBack();
                return ['status' => 'rejected', 'reference' => null, 'reason' => 'unknown_order'];
            }
            $reference = $attempt['merchant_order_id'];
            if ($attempt['status'] === 'paid' || $attempt['status'] === 'failed') {
                $this->touchCallback((int) $attempt['id']);
                $this->pdo->commit();
                return ['status' => $attempt['status'], 'reference' => $reference, 'reason' => 'duplicate'];
            }
            $authenticationIdentityValid = $authentication['merchant_id'] === $this->config['merchant_id']
                && in_array($authentication['customer_id'], ['0', $this->config['customer_id']], true)
                && $this->currencyMatches($authentication['currency_code'], $attempt['currency_code'])
                && $authentication['transaction_security'] === '3';
            if (!$authenticationIdentityValid || $authentication['amount'] !== (int) $attempt['amount']) {
                $this->markAttempt((int) $attempt['id'], 'unknown', $authentication, 'Callback işyeri, para birimi, güvenlik veya tutar doğrulaması başarısız.');
                $this->pdo->commit();
                return ['status' => 'rejected', 'reference' => $reference, 'reason' => 'order_mismatch'];
            }
            if ($attempt['status'] !== 'awaiting_3d') {
                $this->touchCallback((int) $attempt['id']);
                $this->pdo->commit();
                return ['status' => 'unknown', 'reference' => $reference, 'reason' => 'invalid_state'];
            }
            if ($authentication['response_code'] !== '00' || $authentication['md'] === '') {
                $this->markAttempt((int) $attempt['id'], 'failed', $authentication);
                $this->setOrderPayment((int) $attempt['order_id'], 'failed');
                $this->pdo->commit();
                return ['status' => 'failed', 'reference' => $reference, 'reason' => 'authentication_failed'];
            }

            $this->markAttempt((int) $attempt['id'], 'provisioning', $authentication);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        try {
            $provision = $this->gateway->provision($attempt, $authentication['md']);
        } catch (PaymentGatewayException $error) {
            $this->markUnknownAfterProvision((int) $attempt['id'], 'Provizyon sonucu alınamadı; banka panelinden kontrol edilmeli.');
            return ['status' => 'unknown', 'reference' => $reference, 'reason' => 'provision_unavailable'];
        }

        $validProvision = $provision['hash_valid']
            && $provision['merchant_order_id'] === $attempt['merchant_order_id']
            && $provision['order_id'] === $authentication['order_id']
            && $provision['merchant_id'] === $this->config['merchant_id']
            && $provision['customer_id'] === $this->config['customer_id']
            && $provision['amount'] === (int) $attempt['amount']
            && $this->currencyMatches($provision['currency_code'], $attempt['currency_code'])
            && $provision['transaction_security'] === '3';

        $this->pdo->beginTransaction();
        try {
            $current = $this->lockedAttempt($attempt['merchant_order_id']);
            if (!$current || $current['status'] !== 'provisioning') {
                $this->pdo->rollBack();
                return ['status' => $current['status'] ?? 'unknown', 'reference' => $reference, 'reason' => 'concurrent_callback'];
            }
            if (!$validProvision) {
                $this->markProvision((int) $attempt['id'], 'unknown', $provision, 'Provizyon yanıtı doğrulanamadı; banka panelinden kontrol edilmeli.');
                $this->pdo->commit();
                return ['status' => 'unknown', 'reference' => $reference, 'reason' => 'invalid_provision'];
            }
            if ($provision['response_code'] !== '00') {
                $this->markProvision((int) $attempt['id'], 'failed', $provision);
                $this->setOrderPayment((int) $attempt['order_id'], 'failed');
                $this->pdo->commit();
                return ['status' => 'failed', 'reference' => $reference, 'reason' => 'provision_declined'];
            }

            $this->markProvision((int) $attempt['id'], 'paid', $provision);
            $statement = $this->pdo->prepare("UPDATE orders SET payment_status = 'paid', payment_token_hash = NULL, payment_token_expires_at = NULL WHERE id = :id");
            $statement->execute(['id' => $attempt['order_id']]);
            $this->pdo->commit();
            return ['status' => 'paid', 'reference' => $reference, 'reason' => 'verified'];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    private function lockedAttempt(string $merchantOrderId): ?array
    {
        $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $statement = $this->pdo->prepare('SELECT payment_attempts.*, orders.payment_status AS order_payment_status FROM payment_attempts INNER JOIN orders ON orders.id = payment_attempts.order_id WHERE payment_attempts.merchant_order_id = :merchant_order_id LIMIT 1' . $lock);
        $statement->execute(['merchant_order_id' => $merchantOrderId]);
        $attempt = $statement->fetch();
        return is_array($attempt) ? $attempt : null;
    }

    private function touchCallback(int $attemptId): void
    {
        $statement = $this->pdo->prepare('UPDATE payment_attempts SET callback_count = callback_count + 1, last_callback_at = UTC_TIMESTAMP() WHERE id = :id');
        $statement->execute(['id' => $attemptId]);
    }

    private function markAttempt(int $attemptId, string $status, array $auth, ?string $message = null): void
    {
        $statement = $this->pdo->prepare('UPDATE payment_attempts SET status = :status, gateway_order_id = :gateway_order_id, response_code = :response_code, response_message = :response_message, reference_id = :reference_id, business_key = :business_key, callback_count = callback_count + 1, last_callback_at = UTC_TIMESTAMP() WHERE id = :id');
        $statement->execute([
            'status' => $status, 'gateway_order_id' => $auth['order_id'], 'response_code' => $auth['response_code'],
            'response_message' => $message ?? $auth['response_message'], 'reference_id' => $auth['reference_id'] ?: null,
            'business_key' => $auth['business_key'] ?: null, 'id' => $attemptId,
        ]);
    }

    private function markProvision(int $attemptId, string $status, array $provision, ?string $message = null): void
    {
        $completed = in_array($status, ['paid', 'failed'], true) ? gmdate('Y-m-d H:i:s') : null;
        $statement = $this->pdo->prepare('UPDATE payment_attempts SET status = :status, provision_number = :provision_number, rrn = :rrn, stan = :stan, response_code = :response_code, response_message = :response_message, business_key = :business_key, completed_at = :completed_at WHERE id = :id');
        $statement->execute([
            'status' => $status, 'provision_number' => $provision['provision_number'] ?: null, 'rrn' => $provision['rrn'] ?: null,
            'stan' => $provision['stan'] ?: null, 'response_code' => $provision['response_code'],
            'response_message' => $message ?? $provision['response_message'], 'business_key' => $provision['business_key'] ?: null,
            'completed_at' => $completed, 'id' => $attemptId,
        ]);
    }

    private function markUnknownAfterProvision(int $attemptId, string $message): void
    {
        $statement = $this->pdo->prepare("UPDATE payment_attempts SET status = 'unknown', response_message = :message WHERE id = :id AND status = 'provisioning'");
        $statement->execute(['message' => $message, 'id' => $attemptId]);
    }

    private function setOrderPayment(int $orderId, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE orders SET payment_status = :status WHERE id = :id AND payment_status <> \'paid\'');
        $statement->execute(['status' => $status, 'id' => $orderId]);
    }

    private function currencyMatches(string $bankCurrency, string $attemptCurrency): bool
    {
        return ltrim($bankCurrency, '0') === ltrim($attemptCurrency, '0') && ltrim($attemptCurrency, '0') === '949';
    }
}
