<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validCsrf((string) ($_POST['csrf'] ?? ''))) {
    http_response_code(405);
    header('Allow: POST');
    exit('Geçersiz istek.');
}
endAdminSession();
header('Location: login.php');
exit;
