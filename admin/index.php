<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
requireAdmin();
$notice = '';
$allowedOrders = ['received','preparing','ready','shipped','delivered','cancelled'];
$allowedPayments = ['waiting','paid','failed','refunded'];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validCsrf((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Oturum doğrulanamadı.'); }
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $orderStatus = (string) ($_POST['order_status'] ?? '');
    $paymentStatus = (string) ($_POST['payment_status'] ?? '');
    if (!$id || !in_array($orderStatus, $allowedOrders, true) || !in_array($paymentStatus, $allowedPayments, true)) { http_response_code(422); exit('Geçersiz durum.'); }
    $statement = adminDb()->prepare('UPDATE orders SET order_status = :order_status, payment_status = :payment_status WHERE id = :id');
    $statement->execute(['order_status' => $orderStatus, 'payment_status' => $paymentStatus, 'id' => $id]);
    $notice = 'Sipariş güncellendi.';
}
$orders = adminDb()->query('SELECT id, order_number, name, surname, phone, email, delivery_type, total, payment_status, order_status, created_at FROM orders ORDER BY created_at DESC LIMIT 250')->fetchAll();
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Sipariş Yönetimi</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body><main class="wrap"><header><div><p>GÖKTÜRK ULUSAL BİRLİĞİ</p><h1>Siparişler</h1></div><a href="logout.php">Çıkış yap</a></header><?php if ($notice): ?><p class="status" role="status"><?= e($notice) ?></p><?php endif; ?><div class="table"><table><thead><tr><th>Sipariş</th><th>Müşteri</th><th class="hide-mobile">Teslimat</th><th>Toplam</th><th>Durum</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td><strong><?= e($order['order_number']) ?></strong><br><small><?= e($order['created_at']) ?></small></td><td><?= e($order['name'] . ' ' . $order['surname']) ?><br><small><?= e($order['phone']) ?><br><?= e($order['email']) ?></small></td><td class="hide-mobile"><?= $order['delivery_type'] === 'pickup' ? 'Genel merkezden elden teslim' : 'Kargo' ?></td><td><?= number_format((int) $order['total'], 0, ',', '.') ?> TL</td><td><form method="post"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><select name="order_status" aria-label="Sipariş durumu"><?php foreach ($allowedOrders as $status): ?><option value="<?= e($status) ?>" <?= $status === $order['order_status'] ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select><select name="payment_status" aria-label="Ödeme durumu"><?php foreach ($allowedPayments as $status): ?><option value="<?= e($status) ?>" <?= $status === $order['payment_status'] ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select><button type="submit">Kaydet</button></form></td></tr><?php endforeach; ?></tbody></table></div></main></body></html>
