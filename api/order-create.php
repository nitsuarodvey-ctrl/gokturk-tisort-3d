<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

rateLimit('order_create', 8, 600);
$data = input();
if (!empty($data['website'])) respond(400, ['ok' => false, 'message' => 'İstek doğrulanamadı.']);
if (($data['legal_accepted'] ?? '') !== '1') respond(422, ['ok' => false, 'message' => 'Sözleşme onayı gereklidir.']);

$name = textValue($data, 'name', 60);
$surname = textValue($data, 'surname', 60);
$phone = textValue($data, 'phone', 20);
$email = strtolower((string) textValue($data, 'email', 190));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(422, ['ok' => false, 'message' => 'Geçerli bir e-posta adresi girin.']);
$normalizedPhone = normalizePhone((string) $phone);
if (strlen($normalizedPhone) < 10 || strlen($normalizedPhone) > 15) respond(422, ['ok' => false, 'message' => 'Geçerli bir telefon numarası girin.']);

$deliveryType = (string) ($data['delivery_type'] ?? '');
if (!in_array($deliveryType, ['cargo', 'pickup'], true)) respond(422, ['ok' => false, 'message' => 'Geçerli bir teslimat yöntemi seçin.']);
$city = $deliveryType === 'cargo' ? textValue($data, 'city', 80) : null;
$district = $deliveryType === 'cargo' ? textValue($data, 'district', 80) : null;
$address = $deliveryType === 'cargo' ? textValue($data, 'address', 600) : null;
$postalCode = $deliveryType === 'cargo' ? textValue($data, 'postal_code', 12, false) : null;
$notes = textValue($data, 'notes', 500, false);

$itemsInput = $data['items'] ?? null;
if (!is_array($itemsInput) || count($itemsInput) < 1 || count($itemsInput) > 4) respond(422, ['ok' => false, 'message' => 'Sepet bilgisi geçersiz.']);

$unitPrice = 499;
$items = [];
$totalQuantity = 0;
foreach ($itemsInput as $item) {
    if (!is_array($item) || ($item['id'] ?? '') !== 'selcuk-tshirt') respond(422, ['ok' => false, 'message' => 'Sepette geçersiz bir ürün var.']);
    $size = strtoupper((string) ($item['size'] ?? ''));
    $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
    if (!in_array($size, ['S', 'M', 'L', 'XL'], true) || $quantity === false || $quantity < 1 || $quantity > 10) respond(422, ['ok' => false, 'message' => 'Beden veya adet bilgisi geçersiz.']);
    $totalQuantity += $quantity;
    $items[] = ['product_id' => 'selcuk-tshirt', 'product_name' => 'SELÇUK T-SHIRT', 'size' => $size, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'line_total' => $unitPrice * $quantity];
}
if ($totalQuantity > 10) respond(422, ['ok' => false, 'message' => 'Bir siparişte en fazla 10 ürün olabilir.']);
$total = $unitPrice * $totalQuantity;
$orderNumber = 'SEL-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
$paymentToken = bin2hex(random_bytes(32));
$paymentTokenHash = hash('sha256', $paymentToken);
$paymentTokenExpiresAt = gmdate('Y-m-d H:i:s', time() + 7200);

$pdo = database();
try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare('INSERT INTO orders (order_number, name, surname, phone, phone_normalized, email, delivery_type, city, district, address, postal_code, notes, unit_price, total, payment_status, payment_token_hash, payment_token_expires_at, order_status, delivery_status) VALUES (:order_number, :name, :surname, :phone, :phone_normalized, :email, :delivery_type, :city, :district, :address, :postal_code, :notes, :unit_price, :total, :payment_status, :payment_token_hash, :payment_token_expires_at, :order_status, :delivery_status)');
    $statement->execute(['order_number' => $orderNumber, 'name' => $name, 'surname' => $surname, 'phone' => $phone, 'phone_normalized' => $normalizedPhone, 'email' => $email, 'delivery_type' => $deliveryType, 'city' => $city, 'district' => $district, 'address' => $address, 'postal_code' => $postalCode, 'notes' => $notes, 'unit_price' => $unitPrice, 'total' => $total, 'payment_status' => 'waiting', 'payment_token_hash' => $paymentTokenHash, 'payment_token_expires_at' => $paymentTokenExpiresAt, 'order_status' => 'received', 'delivery_status' => 'pending']);
    $orderId = (int) $pdo->lastInsertId();
    $itemStatement = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, size, quantity, unit_price, line_total) VALUES (:order_id, :product_id, :product_name, :size, :quantity, :unit_price, :line_total)');
    foreach ($items as $item) $itemStatement->execute(['order_id' => $orderId] + $item);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Order create error: ' . $error->getMessage());
    respond(500, ['ok' => false, 'message' => 'Sipariş kaydedilemedi. Lütfen tekrar deneyin.']);
}

respond(201, ['ok' => true, 'order_number' => $orderNumber, 'payment_token' => $paymentToken, 'order' => ['items' => $items, 'delivery_type' => $deliveryType, 'address_summary' => $deliveryType === 'cargo' ? trim($address . ', ' . $district . '/' . $city) : null, 'total' => $total, 'billing' => ['city' => $city, 'address' => $address, 'postal_code' => $postalCode]]]);
