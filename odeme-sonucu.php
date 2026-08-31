<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/env.php';
require_once __DIR__ . '/includes/payment-rules.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$status = 'unknown';
$reference = trim((string) ($_GET['reference'] ?? ''));
if (preg_match('/^SEL-[A-Z0-9-]{12,58}$/', $reference)) {
    try {
        $config = loadStoreConfig();
        $db = $config['database'];
        $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']), $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $status = authoritativePaymentResult($pdo, $reference);
    } catch (Throwable $error) {
        error_log('Payment result error: ' . $error->getMessage());
    }
}

$content = [
    'paid' => ['ÖDEME ONAYLANDI', 'Teşekkür<br>ederiz.', 'Ödemeniz banka tarafından doğrulandı ve siparişiniz alındı.'],
    'failed' => ['ÖDEME BAŞARISIZ', 'Ödeme<br>tamamlanamadı.', 'Kartınızdan başarılı tahsilat yapılmadı. Sipariş durumunu kontrol edip yeniden deneyebilirsiniz.'],
    'unknown' => ['KONTROL GEREKİYOR', 'Sonuç<br>bekleniyor.', 'Bankadan kesin sonuç alınamadı. Yeniden ödeme yapmadan önce sipariş durumunu kontrol edin.'],
][$status];
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>Ödeme Sonucu | GÖKTÜRK ULUSAL BİRLİĞİ</title><link rel="icon" href="assets/img/logo.png"><link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="assets/css/style.css"></head><body><a class="skip-link" href="#main">İçeriğe geç</a><div data-site-header></div><main id="main"><section class="page-hero"><div class="site-container"><p class="eyebrow"><?= htmlspecialchars($content[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><h1 class="display-title"><?= $content[1] ?></h1><p class="lead"><?= htmlspecialchars($content[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p><div class="hero-actions"><a class="button button-primary" href="siparis-takip.html">Siparişi takip et</a><a class="button" href="index.html">Mağazaya dön</a></div></div></section></main><div data-site-footer></div><script src="assets/vendor/jquery/jquery.min.js"></script><script src="assets/js/site.js"></script></body></html>
