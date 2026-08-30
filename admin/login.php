<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
if (!empty($_SESSION['admin_authenticated'])) { header('Location: index.php'); exit; }
$error = '';
if (isset($_GET['expired'])) $error = 'Oturum süreniz doldu. Lütfen tekrar giriş yapın.';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validCsrf((string) ($_POST['csrf'] ?? ''))) {
        $error = 'Oturum doğrulanamadı. Sayfayı yenileyin.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $admin = $storeConfig['admin'] ?? [];
        $configuredEmail = strtolower((string) ($admin['email'] ?? ''));
        $configuredHash = (string) ($admin['password_hash'] ?? '');
        try {
            $allowed = strlen($email) <= 190 && adminRateLimit($email);
        } catch (Throwable $exception) {
            error_log('Admin rate limit error: ' . $exception->getMessage());
            $allowed = false;
        }
        $emailMatches = hash_equals($configuredEmail, $email);
        $passwordMatches = password_verify($password, $configuredHash);
        if (!$allowed) {
            http_response_code(429);
            $error = 'Çok fazla deneme yapıldı. Lütfen daha sonra tekrar deneyin.';
        } elseif ($emailMatches && $passwordMatches) {
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_email'] = $email;
            $_SESSION['admin_created_at'] = time();
            $_SESSION['admin_last_activity'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            try { clearAdminRateLimit($email); } catch (Throwable $exception) { error_log('Admin rate limit cleanup error: ' . $exception->getMessage()); }
            header('Location: index.php'); exit;
        } else {
            usleep(350000);
            $error = 'E-posta veya şifre hatalı.';
        }
    }
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Yönetim Girişi</title><link rel="stylesheet" href="../assets/css/admin.css"></head><body class="login"><main class="wrap"><p>GÖKTÜRK ULUSAL BİRLİĞİ</p><h1>Yönetim girişi</h1><?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>"><div class="field"><label for="email">E-posta</label><input id="email" name="email" type="email" autocomplete="username" required></div><div class="field"><label for="password">Şifre</label><input id="password" name="password" type="password" autocomplete="current-password" required></div><button type="submit">Giriş yap</button></form><p><a href="../index.html">Mağazaya dön</a></p></main></body></html>
