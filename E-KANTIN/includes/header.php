<?php
declare(strict_types=1);

$user = current_user();
$active = $active ?? '';
$pageTitle = $pageTitle ?? 'Menu';
$bodyClass = $bodyClass ?? '';
$cartCount = cart_count();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="E-Kantin — pesan makanan kantin kampus tanpa antre.">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<header class="site-header" data-header>
    <div class="container nav-wrap">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <span class="brand-mark">🍱</span>
            <span class="brand-text">
                <strong>E-Kantin</strong>
                <small>Pesan, ambil, santap</small>
            </span>
        </a>

        <button class="nav-toggle" type="button" aria-label="Buka menu" aria-expanded="false" data-nav-toggle>
            <span></span><span></span><span></span>
        </button>

        <nav class="main-nav" data-nav>
            <?php if (!$user): ?>
                <a class="<?= $active === 'menu' ? 'active' : '' ?>" href="<?= e(url('index.php')) ?>">Menu</a>
            <?php elseif ($user['role'] === 'siswa'): ?>
                <a class="<?= $active === 'menu' ? 'active' : '' ?>" href="<?= e(url('index.php')) ?>">Menu</a>
                <a class="<?= $active === 'pesanan' ? 'active' : '' ?>" href="<?= e(url('pesanan.php')) ?>">Pesanan Saya</a>
            <?php elseif ($user['role'] === 'kantin'): ?>
                <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('kantin/index.php')) ?>">Dashboard</a>
                <a class="<?= $active === 'pesanan' ? 'active' : '' ?>" href="<?= e(url('kantin/pesanan.php')) ?>">Pesanan</a>
                <a class="<?= $active === 'stok' ? 'active' : '' ?>" href="<?= e(url('kantin/stok.php')) ?>">Stok</a>
                <a class="<?= $active === 'menu' ? 'active' : '' ?>" href="<?= e(url('kantin/menu.php')) ?>">Kelola Menu</a>
                <a class="<?= $active === 'profil' ? 'active' : '' ?>" href="<?= e(url('kantin/profil.php')) ?>">Profil Kantin</a>
            <?php else: ?>
                <a class="<?= $active === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('admin/index.php')) ?>">Dashboard</a>
                <a class="<?= $active === 'pengguna' ? 'active' : '' ?>" href="<?= e(url('admin/pengguna.php')) ?>">Pengguna</a>
                <a class="<?= $active === 'kantin' ? 'active' : '' ?>" href="<?= e(url('admin/kantin.php')) ?>">Kantin</a>
                <a class="<?= $active === 'kategori' ? 'active' : '' ?>" href="<?= e(url('admin/kategori.php')) ?>">Kategori</a>
                <a class="<?= $active === 'laporan' ? 'active' : '' ?>" href="<?= e(url('admin/laporan.php')) ?>">Laporan</a>
            <?php endif; ?>
        </nav>

        <div class="nav-actions">
            <?php if ($user && $user['role'] === 'siswa'): ?>
                <a class="cart-link" href="<?= e(url('keranjang.php')) ?>" aria-label="Keranjang">
                    <span class="cart-icon">🛒</span>
                    <span class="cart-label">Keranjang</span>
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-badge"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <?php if ($user): ?>
                <div class="user-menu">
                    <a class="user-chip" href="<?= e(url('akun.php')) ?>">
                        <span class="avatar"><?= e(strtoupper(mb_substr($user['nama'], 0, 1))) ?></span>
                        <span class="user-chip-text">
                            <strong><?= e($user['nama']) ?></strong>
                            <small><?= e(role_label($user['role'])) ?></small>
                        </span>
                    </a>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('logout.php')) ?>">Keluar</a>
                </div>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('login.php')) ?>">Masuk</a>
                <a class="btn btn-primary btn-sm" href="<?= e(url('register.php')) ?>">Daftar</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php $flashes = take_flashes(); ?>
<?php if ($flashes): ?>
    <div class="container flash-stack">
        <?php foreach ($flashes as $item): ?>
            <div class="flash flash-<?= e($item['type']) ?>" role="status" data-flash>
                <span class="flash-icon"><?= $item['type'] === 'success' ? '✅' : ($item['type'] === 'error' ? '⚠️' : 'ℹ️') ?></span>
                <span><?= e($item['message']) ?></span>
                <button type="button" class="flash-close" aria-label="Tutup" data-flash-close>×</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<main class="page">
