<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

rateLimit('contact', 6, 600);
$data = input();
if (!empty($data['website'])) respond(400, ['ok' => false, 'message' => 'İstek doğrulanamadı.']);
$name = textValue($data, 'name', 100);
$email = strtolower((string) textValue($data, 'email', 190));
$phone = textValue($data, 'phone', 20, false);
$subject = textValue($data, 'subject', 160);
$message = textValue($data, 'message', 2000);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(422, ['ok' => false, 'message' => 'Geçerli bir e-posta adresi girin.']);

try {
    $statement = database()->prepare('INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (:name, :email, :phone, :subject, :message)');
    $statement->execute(['name' => $name, 'email' => $email, 'phone' => $phone, 'subject' => $subject, 'message' => $message]);
} catch (Throwable $error) {
    error_log('Contact form error: ' . $error->getMessage());
    respond(500, ['ok' => false, 'message' => 'Mesajınız kaydedilemedi. Lütfen tekrar deneyin.']);
}
respond(201, ['ok' => true]);
