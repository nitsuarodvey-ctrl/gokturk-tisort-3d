<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

rateLimit('order_track', 12, 600);
$data = input();
$orderNumber = strtoupper((string) textValue($data, 'order_number', 30));
$contact = strtolower((string) textValue($data, 'contact', 190));
$phone = normalizePhone($contact);

$statement = database()->prepare('SELECT order_number, order_status, delivery_status, delivery_type, created_at, updated_at FROM orders WHERE order_number = :order_number AND (phone_normalized = :phone OR email = :email) LIMIT 1');
$statement->execute(['order_number' => $orderNumber, 'phone' => $phone, 'email' => $contact]);
$order = $statement->fetch();
if (!$order) respond(404, ['ok' => false, 'message' => 'Bu bilgilerle eşleşen bir sipariş bulunamadı.']);

$status = $order['order_status'];
if ($status !== 'cancelled') {
    if ($order['delivery_status'] === 'delivered') $status = 'delivered';
    elseif ($order['delivery_status'] === 'shipped') $status = 'shipped';
    elseif ($order['delivery_status'] === 'ready' && $order['delivery_type'] === 'pickup') $status = 'ready';
}

respond(200, ['ok' => true, 'order' => ['order_number' => $order['order_number'], 'status' => $status, 'delivery_type' => $order['delivery_type'], 'updated_at' => $order['updated_at']]]);
