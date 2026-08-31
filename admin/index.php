<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
requireAdmin();

$orderLabels = ['received' => 'Sipariş alındı', 'preparing' => 'Hazırlanıyor', 'ready' => 'Teslime hazır', 'shipped' => 'Kargoya verildi', 'delivered' => 'Teslim edildi', 'cancelled' => 'İptal edildi'];
$paymentLabels = ['waiting' => 'Ödeme bekliyor', 'paid' => 'Ödendi', 'failed' => 'Başarısız', 'refunded' => 'İade edildi'];
$deliveryLabels = ['pending' => 'Teslimat bekliyor', 'ready' => 'Teslime hazır', 'shipped' => 'Kargoya verildi', 'delivered' => 'Teslim edildi'];
$notice = (string) ($_SESSION['flash_notice'] ?? '');
unset($_SESSION['flash_notice']);
$error = '';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!validCsrf((string) ($_POST['csrf'] ?? ''))) {
            http_response_code(403);
            exit('Oturum doğrulanamadı.');
        }
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $orderStatus = (string) ($_POST['order_status'] ?? '');
        $paymentStatus = (string) ($_POST['payment_status'] ?? '');
        $deliveryStatus = (string) ($_POST['delivery_status'] ?? '');
        if (!$id || !isset($orderLabels[$orderStatus]) || !isset($paymentLabels[$paymentStatus]) || !isset($deliveryLabels[$deliveryStatus])) {
            http_response_code(422);
            exit('Geçersiz durum.');
        }
        $pdo = adminDb();
        $pdo->beginTransaction();
        $currentStatement = $pdo->prepare('SELECT payment_status FROM orders WHERE id = :id LIMIT 1 FOR UPDATE');
        $currentStatement->execute(['id' => $id]);
        $currentPaymentStatus = $currentStatement->fetchColumn();
        if ($currentPaymentStatus === false || !adminPaymentTransitionAllowed((string) $currentPaymentStatus, $paymentStatus)) {
            $pdo->rollBack();
            http_response_code(409);
            throw new DomainException('Ödendi durumu yalnızca doğrulanmış banka callback işlemiyle oluşabilir.');
        }
        $statement = $pdo->prepare('UPDATE orders SET order_status = :order_status, payment_status = :payment_status, delivery_status = :delivery_status WHERE id = :id');
        $statement->execute(['order_status' => $orderStatus, 'payment_status' => $paymentStatus, 'delivery_status' => $deliveryStatus, 'id' => $id]);
        $pdo->commit();
        if ($statement->rowCount() === 0) {
            $check = adminDb()->prepare('SELECT id FROM orders WHERE id = :id');
            $check->execute(['id' => $id]);
            if (!$check->fetchColumn()) {
                http_response_code(404);
                exit('Sipariş bulunamadı.');
            }
        }
        $_SESSION['flash_notice'] = 'Sipariş güncellendi.';
        header('Location: index.php');
        exit;
    }

    $orders = adminDb()->query("SELECT orders.id, order_number, name, surname, phone, email, delivery_type, total, payment_status, order_status, delivery_status, created_at, (SELECT GROUP_CONCAT(CONCAT(size, ' × ', quantity) ORDER BY id SEPARATOR ', ') FROM order_items WHERE order_id = orders.id) AS item_summary FROM orders ORDER BY created_at DESC LIMIT 250")->fetchAll();
    $messages = adminDb()->query('SELECT id, name, email, phone, subject, message, status, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 100')->fetchAll();
} catch (Throwable $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Admin dashboard error: ' . $exception->getMessage());
    $orders = [];
    $messages = [];
    $error = $exception instanceof DomainException ? $exception->getMessage() : 'Veriler şu anda yüklenemedi. Lütfen kısa süre sonra tekrar deneyin.';
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Sipariş Yönetimi</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body><main class="wrap"><header><div><p>GÖKTÜRK ULUSAL BİRLİĞİ</p><h1>Siparişler</h1></div><form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><button class="logout" type="submit">Çıkış yap</button></form></header><?php if ($notice): ?><p class="status" role="status"><?= e($notice) ?></p><?php endif; ?><?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?><div class="table"><table><thead><tr><th>Sipariş</th><th>Müşteri</th><th class="hide-mobile">Teslimat</th><th>Toplam</th><th>Durum</th></tr></thead><tbody><?php if (!$orders): ?><tr><td colspan="5">Henüz sipariş bulunmuyor.</td></tr><?php endif; ?><?php foreach ($orders as $order): ?><tr><td><strong><?= e($order['order_number']) ?></strong><br><small><?= e($order['created_at']) ?></small><br><small><?= e($order['item_summary']) ?></small></td><td><?= e($order['name'] . ' ' . $order['surname']) ?><br><small><?= e($order['phone']) ?><br><?= e($order['email']) ?></small></td><td class="hide-mobile"><?= $order['delivery_type'] === 'pickup' ? 'Genel merkezden elden teslim' : 'Kargo' ?></td><td><?= number_format((int) $order['total'], 0, ',', '.') ?> TL</td><td><form method="post"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><select name="order_status" aria-label="<?= e($order['order_number']) ?> sipariş durumu"><?php foreach ($orderLabels as $status => $label): ?><option value="<?= e($status) ?>" <?= $status === $order['order_status'] ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><select name="payment_status" aria-label="<?= e($order['order_number']) ?> ödeme durumu"><?php foreach ($paymentLabels as $status => $label): ?><option value="<?= e($status) ?>" <?= $status === $order['payment_status'] ? 'selected' : '' ?> <?= $status === 'paid' && $order['payment_status'] !== 'paid' ? 'disabled' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><select name="delivery_status" aria-label="<?= e($order['order_number']) ?> teslimat durumu"><?php foreach ($deliveryLabels as $status => $label): ?><option value="<?= e($status) ?>" <?= $status === $order['delivery_status'] ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button type="submit">Kaydet</button></form></td></tr><?php endforeach; ?></tbody></table></div><section class="messages"><h2>İletişim mesajları</h2><?php if (!$messages): ?><p>Henüz mesaj bulunmuyor.</p><?php endif; ?><?php foreach ($messages as $message): ?><article><header><strong><?= e($message['subject']) ?></strong><small><?= e($message['created_at']) ?></small></header><p><?= nl2br(e($message['message'])) ?></p><small><?= e($message['name']) ?> · <?= e($message['email']) ?><?= $message['phone'] ? ' · ' . e($message['phone']) : '' ?></small></article><?php endforeach; ?></section></main></body></html>
