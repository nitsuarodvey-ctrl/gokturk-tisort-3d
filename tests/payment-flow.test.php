<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/payment-service.php';
require_once dirname(__DIR__) . '/includes/payment-rules.php';

const PASSWORD = 'sandbox-secret';
const MERCHANT = '123456';

function assertion(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function bankHash(array $parts): string
{
    $value = iconv('UTF-8', 'ISO-8859-9', implode('', $parts));
    return base64_encode(sha1($value, true));
}

function hashedPassword(): string
{
    return bankHash([PASSWORD]);
}

function authXml(string $merchantOrderId, int $amount, string $responseCode = '00', string $md = 'secure-md', bool $validHash = true, array $fields = []): string
{
    $orderId = $fields['order_id'] ?? 'BANK-ORDER-' . substr(sha1($merchantOrderId), 0, 10);
    $merchantId = $fields['merchant_id'] ?? MERCHANT;
    $customerId = $fields['customer_id'] ?? '0';
    $currencyCode = $fields['currency_code'] ?? '949';
    $transactionSecurity = $fields['transaction_security'] ?? '3';
    $hash = bankHash([$merchantOrderId, $responseCode, $orderId, hashedPassword()]);
    if (!$validHash) $hash = 'invalid-signature';
    return '<?xml version="1.0" encoding="UTF-8"?><KuveytTurkVPosMessage>'
        . '<VPosMessage><MerchantId>' . $merchantId . '</MerchantId><CustomerId>' . $customerId . '</CustomerId><Amount>' . $amount . '</Amount><CurrencyCode>' . $currencyCode . '</CurrencyCode><TransactionSecurity>' . $transactionSecurity . '</TransactionSecurity></VPosMessage>'
        . '<MerchantOrderId>' . $merchantOrderId . '</MerchantOrderId><ResponseCode>' . $responseCode . '</ResponseCode>'
        . '<ResponseMessage>' . ($responseCode === '00' ? 'Kart doğrulandı' : 'Doğrulama başarısız') . '</ResponseMessage>'
        . '<OrderId>' . $orderId . '</OrderId><MD>' . $md . '</MD><ReferenceId>REF</ReferenceId><BusinessKey>KEY</BusinessKey><HashData>' . $hash . '</HashData></KuveytTurkVPosMessage>';
}

function provisionXml(string $requestXml, string $responseCode = '00', array $fields = []): string
{
    $document = new DOMDocument();
    $document->loadXML($requestXml, LIBXML_NONET);
    $xpath = new DOMXPath($document);
    $merchantOrderId = trim((string) $xpath->evaluate('string((//*[local-name()="MerchantOrderId"])[1])'));
    $amount = (string) ($fields['amount'] ?? trim((string) $xpath->evaluate('string((//*[local-name()="Amount"])[1])')));
    $orderId = $fields['order_id'] ?? 'BANK-ORDER-' . substr(sha1($merchantOrderId), 0, 10);
    $merchantId = $fields['merchant_id'] ?? MERCHANT;
    $customerId = $fields['customer_id'] ?? '100000';
    $currencyCode = $fields['currency_code'] ?? '949';
    $transactionSecurity = $fields['transaction_security'] ?? '3';
    $rrn = 'RRN123';
    $hash = bankHash([$merchantOrderId, $rrn, $responseCode, $orderId, hashedPassword()]);
    return '<?xml version="1.0" encoding="UTF-8"?><KuveytTurkVPosMessage>'
        . '<VPosMessage><MerchantId>' . $merchantId . '</MerchantId><CustomerId>' . $customerId . '</CustomerId><Amount>' . $amount . '</Amount><CurrencyCode>' . $currencyCode . '</CurrencyCode><TransactionSecurity>' . $transactionSecurity . '</TransactionSecurity></VPosMessage>'
        . '<MerchantOrderId>' . $merchantOrderId . '</MerchantOrderId><ResponseCode>' . $responseCode . '</ResponseCode>'
        . '<ResponseMessage>' . ($responseCode === '00' ? 'Otorizasyon verildi' : 'Reddedildi') . '</ResponseMessage>'
        . '<OrderId>' . $orderId . '</OrderId><ProvisionNumber>PROV1</ProvisionNumber><RRN>' . $rrn . '</RRN><Stan>STAN1</Stan><BusinessKey>KEY2</BusinessKey><HashData>' . $hash . '</HashData></KuveytTurkVPosMessage>';
}

function databaseForTest(string $attemptStatus = 'awaiting_3d'): PDO
{
    $mysqlDsn = getenv('PAYMENT_TEST_MYSQL_DSN');
    if (is_string($mysqlDsn) && $mysqlDsn !== '') {
        $pdo = new PDO($mysqlDsn, (string) getenv('PAYMENT_TEST_MYSQL_USER'), (string) getenv('PAYMENT_TEST_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $pdo->exec('DELETE FROM payment_attempts');
        $pdo->exec('DELETE FROM order_items');
        $pdo->exec('DELETE FROM orders');
        $pdo->exec("INSERT INTO orders (id, order_number, name, surname, phone, phone_normalized, email, delivery_type, unit_price, total, payment_status, order_status, delivery_status) VALUES (1, 'SEL-TEST-ORDER', 'Test', 'User', '05550000000', '5550000000', 'test@example.test', 'pickup', 499, 499, 'waiting', 'received', 'pending')");
        $statement = $pdo->prepare("INSERT INTO payment_attempts (id, order_id, merchant_order_id, amount, currency_code, status) VALUES (1, 1, 'SEL-TEST-1', 49900, '0949', :status)");
        $statement->execute(['status' => $attemptStatus]);
        return $pdo;
    }
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->sqliteCreateFunction('UTC_TIMESTAMP', static fn (): string => gmdate('Y-m-d H:i:s'));
    $pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, payment_status TEXT NOT NULL, payment_token_hash TEXT, payment_token_expires_at TEXT)');
    $pdo->exec('CREATE TABLE payment_attempts (id INTEGER PRIMARY KEY, order_id INTEGER NOT NULL, merchant_order_id TEXT UNIQUE NOT NULL, amount INTEGER NOT NULL, currency_code TEXT NOT NULL, status TEXT NOT NULL, gateway_order_id TEXT, provision_number TEXT, rrn TEXT, stan TEXT, response_code TEXT, response_message TEXT, reference_id TEXT, business_key TEXT, callback_count INTEGER NOT NULL DEFAULT 0, last_callback_at TEXT, completed_at TEXT)');
    $pdo->exec("INSERT INTO orders (id, payment_status) VALUES (1, 'waiting')");
    $statement = $pdo->prepare("INSERT INTO payment_attempts (id, order_id, merchant_order_id, amount, currency_code, status) VALUES (1, 1, 'SEL-TEST-1', 49900, '0949', :status)");
    $statement->execute(['status' => $attemptStatus]);
    return $pdo;
}

function config(): array
{
    return [
        'enabled' => true, 'mode' => 'test', 'customer_id' => '100000', 'merchant_id' => MERCHANT,
        'username' => 'api-user', 'password' => PASSWORD,
        'pay_url' => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate',
        'provision_url' => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate',
        'callback_url' => 'https://example.test/api/payment-callback.php', 'return_url' => 'https://example.test/odeme-sonucu.php',
    ];
}

$provisionCalls = 0;
$http = static function (string $url, string $xml) use (&$provisionCalls): string {
    assertion(str_contains($url, 'boatest.kuveytturk.com.tr'), 'Only sandbox endpoint may be used in tests.');
    if (str_contains($url, 'ProvisionGate')) { $provisionCalls++; return provisionXml($xml); }
    return '<html><body>3D test form</body></html>';
};

$gateway = new KuveytTurkGateway(config(), $http);
$startHtml = $gateway->start(['merchant_order_id' => 'SEL-TEST-START', 'amount' => 49900, 'currency_code' => '0949'], ['email' => 'test@example.test', 'phone' => '05550000000'], ['client_ip' => '127.0.0.1', 'card_holder_name' => 'TEST USER', 'card_number' => '4508034508034509', 'expiry_month' => '12', 'expiry_year' => '30', 'cvv' => '000', 'billing_city' => 'Istanbul', 'billing_state' => '34', 'billing_postal_code' => '34000', 'billing_address' => 'Test address']);
assertion(str_contains($startHtml, '3D test form'), 'Payment start did not return bank HTML.');

$pdo = databaseForTest();
$service = new PaymentCallbackService($pdo, $gateway, config());
$paid = $service->process(authXml('SEL-TEST-1', 49900));
assertion($paid['status'] === 'paid', 'Successful callback was not paid.');
assertion($pdo->query('SELECT payment_status FROM orders WHERE id = 1')->fetchColumn() === 'paid', 'Order was not marked paid.');
assertion($provisionCalls === 1, 'Provision must run exactly once.');

$duplicate = $service->process(authXml('SEL-TEST-1', 49900));
assertion($duplicate['status'] === 'paid' && $duplicate['reason'] === 'duplicate', 'Duplicate callback was not idempotent.');
assertion($provisionCalls === 1, 'Duplicate callback caused a second provision.');

$pdo = databaseForTest();
$failedService = new PaymentCallbackService($pdo, $gateway, config());
$failed = $failedService->process(authXml('SEL-TEST-1', 49900, '99', ''));
assertion($failed['status'] === 'failed', 'Failed authentication was not recorded.');
assertion($pdo->query('SELECT payment_status FROM orders WHERE id = 1')->fetchColumn() === 'failed', 'Failed order status was not recorded.');

$pdo = databaseForTest();
$invalidService = new PaymentCallbackService($pdo, $gateway, config());
$invalid = $invalidService->process(authXml('SEL-TEST-1', 49900, '00', 'secure-md', false));
assertion($invalid['status'] === 'rejected' && $invalid['reason'] === 'invalid_signature', 'Invalid signature was not rejected.');
assertion($pdo->query('SELECT status FROM payment_attempts WHERE id = 1')->fetchColumn() === 'awaiting_3d', 'Invalid signature mutated payment state.');

$pdo = databaseForTest();
$mismatchService = new PaymentCallbackService($pdo, $gateway, config());
$mismatch = $mismatchService->process(authXml('SEL-TEST-1', 50000));
assertion($mismatch['status'] === 'rejected' && $mismatch['reason'] === 'order_mismatch', 'Wrong amount was not rejected.');
assertion($pdo->query('SELECT status FROM payment_attempts WHERE id = 1')->fetchColumn() === 'unknown', 'Wrong amount was not quarantined.');
assertion($pdo->query('SELECT payment_status FROM orders WHERE id = 1')->fetchColumn() === 'waiting', 'Wrong amount marked order paid.');

$pdo = databaseForTest();
$unknownService = new PaymentCallbackService($pdo, $gateway, config());
$unknownOrder = $unknownService->process(authXml('SEL-UNKNOWN-ORDER', 49900));
assertion($unknownOrder['status'] === 'rejected' && $unknownOrder['reason'] === 'unknown_order', 'Wrong order number was not rejected.');
assertion($pdo->query('SELECT payment_status FROM orders WHERE id = 1')->fetchColumn() === 'waiting', 'Wrong order number mutated another order.');

$pdo = databaseForTest();
$invalidProvisionGateway = new KuveytTurkGateway(config(), static function (string $url, string $xml): string {
    $response = provisionXml($xml);
    return str_replace('<HashData>', '<HashData>tampered-', $response);
});
$invalidProvisionService = new PaymentCallbackService($pdo, $invalidProvisionGateway, config());
$invalidProvision = $invalidProvisionService->process(authXml('SEL-TEST-1', 49900));
assertion($invalidProvision['status'] === 'unknown' && $invalidProvision['reason'] === 'invalid_provision', 'Invalid provision signature was not quarantined.');
assertion($pdo->query('SELECT payment_status FROM orders WHERE id = 1')->fetchColumn() === 'waiting', 'Invalid provision marked order paid.');

foreach ([
    ['merchant_id' => '999999'],
    ['customer_id' => '999999'],
    ['currency_code' => '840'],
    ['transaction_security' => '1'],
] as $identityMismatch) {
    $pdo = databaseForTest();
    $identityService = new PaymentCallbackService($pdo, $gateway, config());
    $identityResult = $identityService->process(authXml('SEL-TEST-1', 49900, '00', 'secure-md', true, $identityMismatch));
    assertion($identityResult['status'] === 'rejected' && $identityResult['reason'] === 'order_mismatch', 'Merchant/customer/currency/security mismatch was not rejected.');
    assertion($pdo->query('SELECT payment_status FROM orders WHERE id = 1')->fetchColumn() === 'waiting', 'Identity mismatch marked order paid.');
}

$pdo = databaseForTest();
$wrongOrderGateway = new KuveytTurkGateway(config(), static fn (string $url, string $xml): string => provisionXml($xml, '00', ['order_id' => 'BANK-WRONG-ORDER']));
$wrongOrderService = new PaymentCallbackService($pdo, $wrongOrderGateway, config());
$wrongOrderResult = $wrongOrderService->process(authXml('SEL-TEST-1', 49900));
assertion($wrongOrderResult['status'] === 'unknown' && $wrongOrderResult['reason'] === 'invalid_provision', 'Wrong provision OrderId was not rejected.');
assertion($pdo->query('SELECT payment_status FROM orders WHERE id = 1')->fetchColumn() === 'waiting', 'Wrong provision OrderId marked order paid.');

$pdo = databaseForTest();
$declinedGateway = new KuveytTurkGateway(config(), static fn (string $url, string $xml): string => provisionXml($xml, '05'));
$declinedService = new PaymentCallbackService($pdo, $declinedGateway, config());
$declined = $declinedService->process(authXml('SEL-TEST-1', 49900));
assertion($declined['status'] === 'failed' && $declined['reason'] === 'provision_declined', 'Declined provision was not failed.');
assertion($pdo->query('SELECT payment_status FROM orders WHERE id = 1')->fetchColumn() === 'failed', 'Declined provision marked order paid.');

$pdo = databaseForTest();
$malformedService = new PaymentCallbackService($pdo, $gateway, config());
$malformed = $malformedService->process('<broken-callback');
assertion($malformed['status'] === 'rejected' && $malformed['reason'] === 'malformed_response', 'Malformed callback was not rejected.');
assertion($pdo->query('SELECT status FROM payment_attempts WHERE id = 1')->fetchColumn() === 'awaiting_3d', 'Malformed callback mutated state.');

$token = str_repeat('a', 64);
$tokenOrder = ['payment_token_hash' => hash('sha256', $token), 'payment_token_expires_at' => gmdate('Y-m-d H:i:s', time() - 1)];
assertion(!paymentTokenIsValid($tokenOrder, $token), 'Expired payment token was accepted.');
$tokenOrder['payment_token_expires_at'] = gmdate('Y-m-d H:i:s', time() + 60);
assertion(paymentTokenIsValid($tokenOrder, $token), 'Valid payment token was rejected.');

assertion(!adminPaymentTransitionAllowed('waiting', 'paid'), 'Admin could manually mark an unpaid order paid.');
assertion(adminPaymentTransitionAllowed('paid', 'paid'), 'Existing paid status could not remain paid.');
assertion(!adminPaymentTransitionAllowed('paid', 'waiting'), 'Paid order could be reverted to waiting.');

$pdo = databaseForTest();
$validReference = 'SEL-260831-ABCDEF123456-1A2B3C4D';
$statement = $pdo->prepare("UPDATE payment_attempts SET merchant_order_id = :reference, status = 'paid' WHERE id = 1");
$statement->execute(['reference' => $validReference]);
assertion(authoritativePaymentResult($pdo, $validReference) === 'paid', 'Result page did not read authoritative paid state.');
assertion(authoritativePaymentResult($pdo, 'SEL-260831-NOT-FOUND-1A2B3C4D') === 'unknown', 'Forged paid query could affect result state.');

$mysqlDsn = getenv('PAYMENT_TEST_MYSQL_DSN');
if (is_string($mysqlDsn) && $mysqlDsn !== '') {
    $pdo = databaseForTest();
    $concurrentResult = null;
    $outerProvisionCalls = 0;
    $concurrentGateway = new KuveytTurkGateway(config(), static function (string $url, string $xml) use (&$concurrentResult, &$outerProvisionCalls, $mysqlDsn): string {
        $outerProvisionCalls++;
        $otherPdo = new PDO($mysqlDsn, (string) getenv('PAYMENT_TEST_MYSQL_USER'), (string) getenv('PAYMENT_TEST_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $otherService = new PaymentCallbackService($otherPdo, new KuveytTurkGateway(config(), static fn (): string => throw new RuntimeException('Second callback must not provision.')), config());
        $concurrentResult = $otherService->process(authXml('SEL-TEST-1', 49900));
        return provisionXml($xml);
    });
    $outerService = new PaymentCallbackService($pdo, $concurrentGateway, config());
    $outerResult = $outerService->process(authXml('SEL-TEST-1', 49900));
    assertion($outerResult['status'] === 'paid', 'Primary concurrent callback did not complete.');
    assertion($concurrentResult['status'] === 'unknown' && $concurrentResult['reason'] === 'invalid_state', 'Concurrent callback was not stopped at provisioning state.');
    assertion($outerProvisionCalls === 1, 'Concurrent callback caused duplicate provision.');
}

echo "payment start (mock sandbox): ok\n";
echo "successful callback -> paid: ok\n";
echo "failed callback -> failed: ok\n";
echo "duplicate callback idempotency: ok\n";
echo "invalid signature rejection: ok\n";
echo "amount/order mismatch rejection: ok\n";
echo "invalid provision signature quarantine: ok\n";
echo "merchant/customer/currency/security validation: ok\n";
echo "wrong provision OrderId rejection: ok\n";
echo "declined provision -> failed: ok\n";
echo "malformed callback rejection: ok\n";
echo "expired payment token rejection: ok\n";
echo "admin manual paid rejection: ok\n";
echo "authoritative result lookup: ok\n";
if (is_string($mysqlDsn) && $mysqlDsn !== '') echo "concurrent callback idempotency (MySQL): ok\n";
