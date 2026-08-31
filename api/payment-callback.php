<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/payment-service.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST'); http_response_code(405); exit;
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 100000) { http_response_code(413); exit; }
$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($contentType !== 'application/x-www-form-urlencoded') { http_response_code(415); exit; }
$authenticationResponse = (string) ($_POST['AuthenticationResponse'] ?? '');
if ($authenticationResponse === '' || strlen($authenticationResponse) > 90000) { http_response_code(400); exit; }
if (str_starts_with($authenticationResponse, '%3C') || str_starts_with($authenticationResponse, '%3c')) $authenticationResponse = rawurldecode($authenticationResponse);

try {
    $config = loadStoreConfig();
    if (!$config['payment']['enabled']) { http_response_code(503); exit; }
    $db = $config['database'];
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']), $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $pdo->exec("SET time_zone = '+00:00'");
    $service = new PaymentCallbackService($pdo, new KuveytTurkGateway($config['payment']), $config['payment']);
    $result = $service->process($authenticationResponse);
    if ($result['status'] === 'rejected') { http_response_code(400); exit; }
    $query = http_build_query(['reference' => $result['reference']]);
    header('Location: ' . $config['payment']['return_url'] . '?' . $query, true, 303);
} catch (Throwable $error) {
    error_log('Payment callback error: ' . $error->getMessage());
    http_response_code(500);
}
