<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('APP_ROOT', __DIR__);
define('APP_NAME', 'E-Kantin');

define('DB_HOST', getenv('EKANTIN_DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('EKANTIN_DB_NAME') ?: 'e_kantin');
define('DB_USER', getenv('EKANTIN_DB_USER') ?: 'root');
define('DB_PASS', getenv('EKANTIN_DB_PASS') ?: '');

/* Deteksi base URL agar aplikasi bisa jalan di root maupun subfolder (htdocs/e-kantin). */
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
$baseUrl = '';
if ($documentRoot !== false && $documentRoot !== '') {
    $docPath = str_replace('\\', '/', $documentRoot);
    $appPath = str_replace('\\', '/', APP_ROOT);
    if (str_starts_with($appPath, $docPath)) {
        $baseUrl = trim(substr($appPath, strlen($docPath)), '/');
        $baseUrl = $baseUrl === '' ? '' : '/' . $baseUrl;
    }
}
define('BASE_URL', $baseUrl);

require_once __DIR__ . '/includes/functions.php';

try {
    $pdo = db_connect();
} catch (PDOException $exception) {
    $driverCode = (int) ($exception->errorInfo[1] ?? 0);
    if ($driverCode === 1049 || str_contains($exception->getMessage(), 'Unknown database')) {
        try {
            $pdo = install_database();
        } catch (Throwable $installError) {
            db_error_page('Instalasi otomatis gagal: ' . $installError->getMessage());
        }
    } else {
        db_error_page($exception->getMessage());
    }
}
