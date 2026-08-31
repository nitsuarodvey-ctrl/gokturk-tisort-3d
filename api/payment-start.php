<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/kuveyt-turk.php';
require_once dirname(__DIR__) . '/includes/payment-rules.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

function paymentError(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $safe = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="tr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ödeme başlatılamadı</title><body><main><h1>Ödeme başlatılamadı</h1><p>' . $safe . '</p><p><a href="../odeme-kart.html">Ödeme sayfasına dön</a></p></main></body></html>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    paymentError(405, 'Bu işlem yalnızca POST isteği kabul eder.');
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) paymentError(413, 'İstek boyutu izin verilen sınırı aşıyor.');
$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($contentType !== 'application/x-www-form-urlencoded') paymentError(415, 'Geçersiz istek biçimi.');

try {
    $config = loadStoreConfig();
} catch (Throwable $error) {
    error_log('Payment configuration error: ' . $error->getMessage());
    paymentError(503, 'Kartla ödeme henüz yapılandırılmadı.');
}
if (!$config['payment']['enabled']) paymentError(503, 'Kartla ödeme şu anda kullanılamıyor.');

$appParts = parse_url($config['app']['url']);
$expectedOrigin = strtolower($appParts['scheme'] . '://' . $appParts['host'] . (isset($appParts['port']) ? ':' . $appParts['port'] : ''));
$origin = strtolower(rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/'));
$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
if (($origin !== '' && !hash_equals($expectedOrigin, $origin)) || ($origin === '' && !str_starts_with(strtolower($referer), $expectedOrigin . '/'))) {
    paymentError(403, 'İstek kaynağı doğrulanamadı.');
}

$value = static function (string $key, int $max): string {
    $item = trim((string) ($_POST[$key] ?? ''));
    if ($item === '' || strlen($item) > $max || preg_match('/[\x00-\x1F\x7F]/', $item)) paymentError(422, 'Lütfen ödeme alanlarını eksiksiz ve doğru doldurun.');
    return $item;
};
$orderNumber = $value('order_number', 30);
$paymentToken = $value('payment_token', 64);
if (!preg_match('/^[a-f0-9]{64}$/', $paymentToken)) paymentError(403, 'Ödeme bağlantısı geçersiz veya süresi dolmuş.');
$cardHolderName = $value('card_holder_name', 45);
$cardNumber = preg_replace('/\D+/', '', $value('card_number', 25)) ?? '';
$expiryMonth = str_pad($value('expiry_month', 2), 2, '0', STR_PAD_LEFT);
$expiryYear = $value('expiry_year', 4);
$cvv = $value('cvv', 4);
$billingCity = $value('billing_city', 80);
$billingState = $value('billing_state', 3);
$billingPostalCode = $value('billing_postal_code', 12);
$billingAddress = $value('billing_address', 300);

$luhn = static function (string $number): bool {
    if (!preg_match('/^[0-9]{13,19}$/', $number)) return false;
    $sum = 0; $alternate = false;
    for ($index = strlen($number) - 1; $index >= 0; $index--) {
        $digit = (int) $number[$index];
        if ($alternate && ($digit *= 2) > 9) $digit -= 9;
        $sum += $digit; $alternate = !$alternate;
    }
    return $sum % 10 === 0;
};
if ((function_exists('mb_strlen') ? mb_strlen($cardHolderName, 'UTF-8') : strlen($cardHolderName)) < 2 || !$luhn($cardNumber) || !preg_match('/^[0-9]{3}$/', $cvv) || !preg_match('/^(0[1-9]|1[0-2])$/', $expiryMonth) || !preg_match('/^[0-9]{2,4}$/', $expiryYear) || !preg_match('/^[0-9]{1,3}$/', $billingState)) {
    paymentError(422, 'Kart veya fatura bilgileri geçersiz.');
}
$fullYear = strlen($expiryYear) === 2 ? 2000 + (int) $expiryYear : (int) $expiryYear;
if ($fullYear < (int) gmdate('Y') || ($fullYear === (int) gmdate('Y') && (int) $expiryMonth < (int) gmdate('n')) || $fullYear > (int) gmdate('Y') + 20) paymentError(422, 'Kartın son kullanma tarihi geçersiz.');
$expiryYear = substr((string) $fullYear, -2);

$db = $config['database'];
$bankResponseReceived = false;
try {
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']), $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $pdo->exec("SET time_zone = '+00:00'");
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $keyHash = hash_hmac('sha256', $remoteAddress, $config['app']['key']);
    $cutoff = gmdate('Y-m-d H:i:s', time() - 600);
    $limit = $pdo->prepare('INSERT INTO rate_limits (bucket, key_hash, window_started_at, request_count) VALUES (\'payment_start\', :key_hash, UTC_TIMESTAMP(), 1) ON DUPLICATE KEY UPDATE request_count = IF(window_started_at < :cutoff_count, 1, request_count + 1), window_started_at = IF(window_started_at < :cutoff_window, UTC_TIMESTAMP(), window_started_at)');
    $limit->execute(['key_hash' => $keyHash, 'cutoff_count' => $cutoff, 'cutoff_window' => $cutoff]);
    $check = $pdo->prepare("SELECT request_count FROM rate_limits WHERE bucket = 'payment_start' AND key_hash = :key_hash");
    $check->execute(['key_hash' => $keyHash]);
    if ((int) $check->fetchColumn() > 8) paymentError(429, 'Çok fazla ödeme denemesi yapıldı. Lütfen daha sonra tekrar deneyin.');

    $pdo->beginTransaction();
    $statement = $pdo->prepare('SELECT * FROM orders WHERE order_number = :order_number LIMIT 1 FOR UPDATE');
    $statement->execute(['order_number' => $orderNumber]);
    $order = $statement->fetch();
    if (!$order || !paymentTokenIsValid($order, $paymentToken)) {
        $pdo->rollBack();
        paymentError(403, 'Ödeme bağlantısı geçersiz veya süresi dolmuş.');
    }
    if ($order['payment_status'] === 'paid') {
        $pdo->rollBack();
        paymentError(409, 'Bu siparişin ödemesi zaten alınmış.');
    }
    $active = $pdo->prepare("SELECT status FROM payment_attempts WHERE order_id = :order_id AND status IN ('initiated','awaiting_3d','provisioning','unknown') LIMIT 1 FOR UPDATE");
    $active->execute(['order_id' => $order['id']]);
    if ($active->fetchColumn() !== false) {
        $pdo->rollBack();
        paymentError(409, 'Bu sipariş için devam eden veya kontrol bekleyen bir ödeme var.');
    }
    $merchantOrderId = $orderNumber . '-' . strtoupper(bin2hex(random_bytes(4)));
    $amount = (int) $order['total'] * 100;
    $insert = $pdo->prepare("INSERT INTO payment_attempts (order_id, merchant_order_id, amount, currency_code, status) VALUES (:order_id, :merchant_order_id, :amount, '0949', 'initiated')");
    $insert->execute(['order_id' => $order['id'], 'merchant_order_id' => $merchantOrderId, 'amount' => $amount]);
    $attemptId = (int) $pdo->lastInsertId();
    $pdo->commit();

    $attempt = ['id' => $attemptId, 'merchant_order_id' => $merchantOrderId, 'amount' => $amount, 'currency_code' => '0949'];
    $gateway = new KuveytTurkGateway($config['payment']);
    $bankHtml = $gateway->start($attempt, $order, [
        'client_ip' => $remoteAddress, 'card_holder_name' => $cardHolderName, 'card_number' => $cardNumber,
        'expiry_month' => $expiryMonth, 'expiry_year' => $expiryYear, 'cvv' => $cvv,
        'billing_city' => $billingCity, 'billing_state' => $billingState,
        'billing_postal_code' => $billingPostalCode, 'billing_address' => $billingAddress,
    ]);
    $bankResponseReceived = true;
    $update = $pdo->prepare("UPDATE payment_attempts SET status = 'awaiting_3d' WHERE id = :id AND status = 'initiated'");
    $update->execute(['id' => $attemptId]);
    $waiting = $pdo->prepare("UPDATE orders SET payment_status = 'waiting' WHERE id = :id AND payment_status <> 'paid'");
    $waiting->execute(['id' => $order['id']]);
    header('Content-Type: text/html; charset=utf-8');
    echo $bankHtml;
} catch (PaymentGatewayException $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (isset($pdo, $attemptId)) {
        $failed = $pdo->prepare("UPDATE payment_attempts SET status = 'failed', response_message = 'Banka ödeme başlangıç yanıtı alınamadı.' WHERE id = :id AND status = 'initiated'");
        $failed->execute(['id' => $attemptId]);
    }
    paymentError(502, $error->getMessage());
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (isset($pdo, $attemptId)) {
        $status = $bankResponseReceived ? 'unknown' : 'failed';
        $message = $bankResponseReceived
            ? 'Banka ödeme ekranı alındı ancak yerel durum kesinleştirilemedi; banka panelinden kontrol edilmeli.'
            : 'Ödeme başlatılamadı.';
        $failed = $pdo->prepare("UPDATE payment_attempts SET status = :status, response_message = :message WHERE id = :id AND status IN ('initiated','awaiting_3d')");
        $failed->execute(['status' => $status, 'message' => $message, 'id' => $attemptId]);
    }
    error_log('Payment start error: ' . $error->getMessage());
    paymentError(500, 'Ödeme şu anda başlatılamadı. Lütfen tekrar deneyin.');
}
