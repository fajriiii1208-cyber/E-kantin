<?php
require __DIR__ . '/../config.php';

$user = require_role('kantin');
if (empty($user['kantin_id'])) {
    flash('error', 'Akunmu belum terhubung ke kantin. Hubungi admin untuk menautkannya.');
    redirect('akun.php');
}
$kantinId = (int) $user['kantin_id'];

$stmt = $pdo->prepare('SELECT * FROM kantin WHERE id = ?');
$stmt->execute([$kantinId]);
$kantin = $stmt->fetch();
if (!$kantin) {
    flash('error', 'Data kantin tidak ditemukan.');
    redirect('akun.php');
}

$statsStmt = $pdo->prepare(
    "SELECT
       COUNT(*) AS jumlah,
       COALESCE(SUM(total), 0) AS omzet
     FROM pesanan
     WHERE kantin_id = ? AND DATE(created_at) = CURDATE() AND status <> 'dibatalkan'"
);
$statsStmt->execute([$kantinId]);
$today = $statsStmt->fetch();

$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE kantin_id = ? AND status = 'menunggu'");
$pendingStmt->execute([$kantinId]);
$pending = (int) $pendingStmt->fetchColumn();

$readyStmt = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE kantin_id = ? AND status = 'siap'");
$readyStmt->execute([$kantinId]);
$ready = (int) $readyStmt->fetchColumn();

$menuStmt = $pdo->prepare('SELECT COUNT(*) FROM menu WHERE kantin_id = ? AND is_active = 1');
$menuStmt->execute([$kantinId]);
$menuCount = (int) $menuStmt->fetchColumn();

$lowStmt = $pdo->prepare('SELECT COUNT(*) FROM menu WHERE kantin_id = ? AND is_active = 1 AND stok <= 5');
$lowStmt->execute([$kantinId]);
$lowStock = (int) $lowStmt->fetchColumn();

$allStmt = $pdo->prepare("SELECT COUNT(*) AS jumlah, COALESCE(SUM(total), 0) AS omzet FROM pesanan WHERE kantin_id = ? AND status <> 'dibatalkan'");
$allStmt->execute([$kantinId]);
$allTime = $allStmt->fetch();

$recentStmt = $pdo->prepare(
    'SELECT p.*, u.nama AS nama_pemesan
     FROM pesanan p JOIN users u ON u.id = p.user_id
     WHERE p.kantin_id = ?
     ORDER BY p.created_at DESC
     LIMIT 6'
);
$recentStmt->execute([$kantinId]);
$recent = $recentStmt->fetchAll();

$itemsByOrder = [];
if ($recent) {
    $ids = array_map(static fn ($o) => (int) $o['id'], $recent);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $itemStmt = $pdo->prepare("SELECT * FROM pesanan_detail WHERE pesanan_id IN ($ph) ORDER BY id");
    $itemStmt->execute($ids);
    foreach ($itemStmt->fetchAll() as $item) {
        $itemsByOrder[(int) $item['pesanan_id']][] = $item;
    }
}

$bestStmt = $pdo->prepare(
    "SELECT d.nama_menu, SUM(d.qty) AS total_qty, SUM(d.subtotal) AS total_omzet
     FROM pesanan_detail d
     JOIN pesanan p ON p.id = d.pesanan_id
     WHERE p.kantin_id = ? AND p.status <> 'dibatalkan'
     GROUP BY d.nama_menu
     ORDER BY total_qty DESC
     LIMIT 5"
);
$bestStmt->execute([$kantinId]);
$bestSellers = $bestStmt->fetchAll();

$pageTitle = 'Dashboard kantin';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <div class="page-head">
        <div>
            <span class="eyebrow">👩‍🍳 <?= e(role_label($user['role'])) ?></span>
            <h1><?= e($kantin['nama']) ?></h1>
            <p class="section-sub"><?= e($kantin['lokasi'] ?: 'Lokasi belum diatur') ?> · <?= (int) $kantin['is_open'] === 1 ? 'Sedang buka' : 'Sedang tutup' ?></p>
        </div>
        <div class="head-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('kantin/stok.php')) ?>">📦 Kelola stok</a>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('kantin/profil.php')) ?>">⚙️ Atur kantin</a>
            <a class="btn btn-primary btn-sm" href="<?= e(url('kantin/menu.php')) ?>">+ Tambah menu</a>
        </div>
    </div>

    <?php if ($pending > 0): ?>
        <div class="alert alert-warning alert-action">
            <span>🔔 Ada <strong><?= $pending ?> pesanan baru</strong> yang menunggu konfirmasi.</span>
            <a class="btn btn-sm btn-primary" href="<?= e(url('kantin/pesanan.php?status=menunggu')) ?>">Lihat pesanan</a>
        </div>
    <?php endif; ?>

    <?php if ($lowStock > 0): ?>
        <div class="alert alert-warning alert-action">
            <span>📦 <strong><?= $lowStock ?> menu</strong> stoknya menipis atau habis. Segera perbarui ketersediaannya.</span>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('kantin/stok.php')) ?>">Perbarui stok</a>
        </div>
    <?php endif; ?>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-icon amber">⏳</span>
            <div><strong><?= $pending ?></strong><span>Perlu konfirmasi</span></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon green">✅</span>
            <div><strong><?= $ready ?></strong><span>Siap diambil</span></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon blue">🧾</span>
            <div><strong><?= (int) $today['jumlah'] ?></strong><span>Pesanan hari ini</span></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon brand">💰</span>
            <div><strong><?= e(rupiah($today['omzet'])) ?></strong><span>Omzet hari ini</span></div>
        </div>
    </div>

    <div class="two-col wide-left">
        <div class="panel">
            <div class="panel-head row-between">
                <div>
                    <h2>Pesanan terbaru</h2>
                    <p>Pesanan terakhir yang masuk ke kantinmu.</p>
                </div>
                <a class="link-more" href="<?= e(url('kantin/pesanan.php')) ?>">Semua pesanan →</a>
            </div>

            <?php if (!$recent): ?>
                <div class="empty-inline">
                    <span>🍽️</span>
                    <p>Belum ada pesanan. Menu yang aktif akan muncul di halaman siswa.</p>
                </div>
            <?php else: ?>
                <div class="compact-orders">
                    <?php foreach ($recent as $order): ?>
                        <?php
                        $meta = status_meta($order['status']);
                        $items = $itemsByOrder[(int) $order['id']] ?? [];
                        $names = array_map(static fn ($i) => $i['nama_menu'] . ' ×' . $i['qty'], $items);
                        ?>
                        <div class="compact-order">
                            <div class="compact-order-info">
                                <div class="compact-order-top">
                                    <strong><?= e($order['kode']) ?></strong>
                                    <span class="badge <?= e($meta['class']) ?>"><?= e($meta['label']) ?></span>
                                </div>
                                <p><?= e($order['nama_pemesan']) ?> · <?= e(implode(', ', $names)) ?></p>
                                <small><?= e(tanggal_id($order['created_at'])) ?> · <?= e(metode_label($order['metode'])) ?></small>
                            </div>
                            <div class="compact-order-side">
                                <strong><?= e(rupiah($order['total'])) ?></strong>
                                <a class="link-more" href="<?= e(url('kantin/pesanan.php?status=' . $order['status'])) ?>">Kelola →</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="stack">
            <div class="panel">
                <div class="panel-head">
                    <h2>Ringkasan</h2>
                    <p>Performa kantinmu sampai hari ini.</p>
                </div>
                <div class="summary-row">
                    <span>Total pesanan</span>
                    <strong><?= (int) $allTime['jumlah'] ?></strong>
                </div>
                <div class="summary-row">
                    <span>Total omzet</span>
                    <strong><?= e(rupiah($allTime['omzet'])) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Menu aktif</span>
                    <strong><?= $menuCount ?></strong>
                </div>
                <a class="btn btn-ghost btn-block" href="<?= e(url('kantin/menu.php')) ?>">Kelola menu</a>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Menu terlaris</h2>
                    <p>Berdasarkan jumlah porsi terjual.</p>
                </div>
                <?php if (!$bestSellers): ?>
                    <p class="muted">Belum ada data penjualan.</p>
                <?php else: ?>
                    <ol class="rank-list">
                        <?php foreach ($bestSellers as $index => $best): ?>
                            <li>
                                <span class="rank-number"><?= $index + 1 ?></span>
                                <div>
                                    <strong><?= e($best['nama_menu']) ?></strong>
                                    <small><?= (int) $best['total_qty'] ?> porsi · <?= e(rupiah($best['total_omzet'])) ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
