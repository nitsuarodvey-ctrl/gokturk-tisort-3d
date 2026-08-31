<?php
declare(strict_types=1);

function paymentTokenIsValid(array $order, string $token, ?int $now = null): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token) || !is_string($order['payment_token_hash'] ?? null)) return false;
    $expiresAt = strtotime((string) ($order['payment_token_expires_at'] ?? ''));
    return $expiresAt !== false
        && $expiresAt >= ($now ?? time())
        && hash_equals($order['payment_token_hash'], hash('sha256', $token));
}

function adminPaymentTransitionAllowed(string $current, string $requested): bool
{
    if ($requested === 'paid') return $current === 'paid';
    if ($current === 'paid') return $requested === 'refunded';
    return in_array($requested, ['waiting', 'failed', 'refunded'], true);
}

function authoritativePaymentResult(PDO $pdo, string $reference): string
{
    if (!preg_match('/^SEL-[A-Z0-9-]{12,58}$/', $reference)) return 'unknown';
    $statement = $pdo->prepare('SELECT status FROM payment_attempts WHERE merchant_order_id = :reference LIMIT 1');
    $statement->execute(['reference' => $reference]);
    $stored = $statement->fetchColumn();
    if (!in_array($stored, ['paid', 'failed', 'unknown', 'provisioning'], true)) return 'unknown';
    return $stored === 'provisioning' ? 'unknown' : $stored;
}
