<?php
declare(strict_types=1);

/* =========================================================
   Koneksi & instalasi database
   ========================================================= */

function db_connect(?string $dbname = DB_NAME): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
    if ($dbname !== null && $dbname !== '') {
        $dsn .= ';dbname=' . $dbname;
    }

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/**
 * Membuat database dan mengimpor database.sql saat pertama kali dijalankan.
 */
function install_database(): PDO
{
    $server = db_connect(null);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    $file = APP_ROOT . '/database.sql';
    if (!is_file($file)) {
        throw new RuntimeException('Berkas database.sql tidak ditemukan.');
    }

    $sql = (string) file_get_contents($file);
    $server->exec('USE `' . DB_NAME . '`');

    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        $server->exec($statement);
    }

    return db_connect();
}

function db_error_page(string $message): void
{
    http_response_code(500);
    $safe = e($message);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Koneksi database gagal</title>'
        . '<style>body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#f7f3ec;color:#1c1917;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px}'
        . '.box{max-width:620px;background:#fff;border:1px solid #e7e0d5;border-radius:18px;padding:32px;box-shadow:0 18px 40px rgba(60,40,20,.08)}'
        . 'h1{margin:0 0 8px;font-size:22px}p{color:#57534e;line-height:1.65}code{background:#f5f1ea;padding:3px 7px;border-radius:6px;font-size:13px}'
        . '.err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 14px;border-radius:10px;font-size:13px;overflow:auto}</style>'
        . '</head><body><div class="box"><h1>⚠️ Database belum siap</h1>'
        . '<p>Aplikasi tidak bisa terhubung ke MySQL. Pastikan <strong>MySQL di XAMPP sudah berjalan</strong>, lalu muat ulang halaman ini.</p>'
        . '<p>Konfigurasi saat ini: host <code>' . e(DB_HOST) . '</code>, database <code>' . e(DB_NAME) . '</code>, user <code>' . e(DB_USER) . '</code>.</p>'
        . '<div class="err">' . $safe . '</div></div></body></html>';
    exit;
}

/* =========================================================
   URL, redirect, escape
   ========================================================= */

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function redirect_raw(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function post_str(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function safe_next(?string $next, string $fallback): string
{
    if (!$next || preg_match('#^[a-z]+://#i', $next) || str_starts_with($next, '//')) {
        return $fallback;
    }
    if (!str_starts_with($next, '/') || str_contains($next, '..')) {
        return $fallback;
    }
    return $next;
}

/* =========================================================
   Flash message & CSRF
   ========================================================= */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        flash('error', 'Sesi keamanan kedaluwarsa. Silakan ulangi aksi kamu.');
        redirect('index.php');
    }
}

/* =========================================================
   Autentikasi & peran
   ========================================================= */

function current_user(): ?array
{
    global $pdo;
    static $cached = false;

    if ($cached !== false) {
        return $cached;
    }
    if (empty($_SESSION['user_id'])) {
        return $cached = null;
    }

    $stmt = $pdo->prepare(
        'SELECT u.*, k.nama AS nama_kantin, k.is_open AS kantin_open
         FROM users u
         LEFT JOIN kantin k ON k.id = u.kantin_id
         WHERE u.id = ?'
    );
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return $cached = null;
    }

    return $cached = $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Silakan masuk terlebih dahulu.');
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? url('index.php')));
    }
    return $user;
}

function dashboard_for(string $role): string
{
    return match ($role) {
        'kantin' => 'kantin/index.php',
        'admin'  => 'admin/index.php',
        default  => 'index.php',
    };
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        flash('error', 'Halaman tersebut khusus untuk ' . role_label($role) . '.');
        redirect(dashboard_for($user['role']));
    }
    return $user;
}

function role_label(string $role): string
{
    return match ($role) {
        'admin'  => 'Admin',
        'kantin' => 'Pengelola Kantin',
        default  => 'Siswa',
    };
}

function role_badge_class(string $role): string
{
    return match ($role) {
        'admin'  => 'badge-red',
        'kantin' => 'badge-blue',
        default  => 'badge-green',
    };
}

/* =========================================================
   Keranjang (disimpan di session)
   ========================================================= */

function cart(): array
{
    $cart = $_SESSION['cart'] ?? [];
    return is_array($cart) ? $cart : [];
}

function cart_count(): int
{
    return (int) array_sum(cart());
}

function cart_set(int $menuId, int $qty): void
{
    if ($qty <= 0) {
        unset($_SESSION['cart'][$menuId]);
        return;
    }
    $_SESSION['cart'][$menuId] = $qty;
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

/**
 * Ambil item keranjang beserta data menu & kantin terbaru.
 */
function cart_items(PDO $pdo): array
{
    $cart = cart();
    if (!$cart) {
        return [];
    }

    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT m.*, k.nama AS nama_kantin, k.is_open, kat.nama AS nama_kategori
         FROM menu m
         JOIN kantin k ON k.id = m.kantin_id
         LEFT JOIN kategori kat ON kat.id = m.kategori_id
         WHERE m.id IN ($placeholders)"
    );
    $stmt->execute($ids);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $qty = (int) ($cart[$row['id']] ?? 0);
        if ($qty <= 0) {
            continue;
        }
        $row['qty']      = $qty;
        $row['subtotal'] = $qty * (int) $row['harga'];
        $items[]         = $row;
    }
    return $items;
}

function cart_total(PDO $pdo): int
{
    return array_sum(array_column(cart_items($pdo), 'subtotal'));
}

function group_cart_by_kantin(array $items): array
{
    $groups = [];
    foreach ($items as $item) {
        $kantinId = (int) $item['kantin_id'];
        if (!isset($groups[$kantinId])) {
            $groups[$kantinId] = [
                'kantin_id'   => $kantinId,
                'nama_kantin' => $item['nama_kantin'],
                'is_open'     => (int) $item['is_open'],
                'items'       => [],
                'total'       => 0,
            ];
        }
        $groups[$kantinId]['items'][] = $item;
        $groups[$kantinId]['total']  += (int) $item['subtotal'];
    }
    return $groups;
}

/* =========================================================
   Format & label
   ========================================================= */

function rupiah(int|float|string $number): string
{
    return 'Rp ' . number_format((int) $number, 0, ',', '.');
}

function tanggal_id(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $time = strtotime($datetime);
    if ($time === false) {
        return '-';
    }
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('d', $time) . ' ' . $bulan[(int) date('n', $time)] . ' ' . date('Y', $time) . ', ' . date('H:i', $time);
}

function status_meta(string $status): array
{
    return match ($status) {
        'diproses'  => ['label' => 'Diproses', 'class' => 'badge-blue', 'icon' => '👨‍🍳'],
        'siap'      => ['label' => 'Siap diambil', 'class' => 'badge-green', 'icon' => '✅'],
        'selesai'   => ['label' => 'Selesai', 'class' => 'badge-gray', 'icon' => '🏁'],
        'dibatalkan'=> ['label' => 'Dibatalkan', 'class' => 'badge-red', 'icon' => '✖️'],
        default     => ['label' => 'Menunggu', 'class' => 'badge-amber', 'icon' => '⏳'],
    };
}

function status_step(string $status): int
{
    return match ($status) {
        'menunggu' => 1,
        'diproses' => 2,
        'siap'     => 3,
        'selesai'  => 4,
        default    => 0,
    };
}

function metode_label(string $metode): string
{
    return $metode === 'qris' ? 'QRIS' : 'Tunai';
}

function kode_pesanan(): string
{
    return 'EK' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
}

/* =========================================================
   Query bantu
   ========================================================= */

function menu_emoji(string $emoji, string $fallback = '🍽️'): string
{
    return $emoji !== '' ? $emoji : $fallback;
}

function fetch_order(PDO $pdo, int $orderId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.*, u.nama AS nama_pemesan, u.no_hp, k.nama AS nama_kantin
         FROM pesanan p
         JOIN users u ON u.id = p.user_id
         JOIN kantin k ON k.id = p.kantin_id
         WHERE p.id = ?'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    return $order ?: null;
}

function fetch_order_items(PDO $pdo, int $orderId): array
{
    $stmt = $pdo->prepare('SELECT * FROM pesanan_detail WHERE pesanan_id = ? ORDER BY id');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

/**
 * Transisi status yang diizinkan untuk pengelola kantin.
 */
function allowed_transitions(string $status): array
{
    return match ($status) {
        'menunggu' => ['diproses', 'dibatalkan'],
        'diproses' => ['siap', 'dibatalkan'],
        'siap'     => ['selesai', 'dibatalkan'],
        default    => [],
    };
}

/**
 * Batalkan pesanan dan kembalikan stok menu. Melempar exception bila gagal.
 */
function cancel_order(PDO $pdo, array $order): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE pesanan SET status = 'dibatalkan' WHERE id = ? AND status IN ('menunggu', 'diproses', 'siap')"
        );
        $stmt->execute([(int) $order['id']]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Pesanan tidak bisa dibatalkan karena statusnya sudah berubah.');
        }

        $restore = $pdo->prepare('UPDATE menu SET stok = stok + ? WHERE id = ?');
        foreach (fetch_order_items($pdo, (int) $order['id']) as $item) {
            if ($item['menu_id'] !== null) {
                $restore->execute([(int) $item['qty'], (int) $item['menu_id']]);
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
