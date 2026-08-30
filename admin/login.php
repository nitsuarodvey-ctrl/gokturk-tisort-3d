<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
if (!empty($_SESSION['admin_authenticated'])) { header('Location: index.php'); exit; }
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $attempts = $_SESSION['login_attempts'] ?? ['start' => time(), 'count' => 0];
    if (time() - (int) $attempts['start'] > 900) $attempts = ['start' => time(), 'count' => 0];
    $attempts['count']++;
    $_SESSION['login_attempts'] = $attempts;
    if ($attempts['count'] > 8) {
        $error = 'Çok fazla deneme yapıldı. Lütfen daha sonra tekrar deneyin.';
    } elseif (!validCsrf((string) ($_POST['csrf'] ?? ''))) {
        $error = 'Oturum doğrulanamadı. Sayfayı yenileyin.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $admin = $storeConfig['admin'] ?? [];
        if (hash_equals(strtolower((string) ($admin['email'] ?? '')), $email) && password_verify($password, (string) ($admin['password_hash'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_email'] = $email;
            unset($_SESSION['login_attempts']);
            header('Location: index.php'); exit;
        }
        usleep(350000);
        $error = 'E-posta veya şifre hatalı.';
    }
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Yönetim Girişi</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body class="login"><main class="wrap"><p>GÖKTÜRK ULUSAL BİRLİĞİ</p><h1>Yönetim girişi</h1><?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><div class="field"><label for="email">E-posta</label><input id="email" name="email" type="email" autocomplete="username" required></div><div class="field"><label for="password">Şifre</label><input id="password" name="password" type="password" autocomplete="current-password" required></div><button type="submit">Giriş yap</button></form><p><a href="../index.html">Mağazaya dön</a></p></main></body></html>
