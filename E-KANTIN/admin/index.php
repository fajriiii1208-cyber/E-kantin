<?php
require __DIR__ . '/../config.php';

$user = require_role('admin');

$totalSiswa = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'siswa'")->fetchColumn();
$totalKantin = (int) $pdo->query('SELECT COUNT(*) FROM kantin')->fetchColumn();
$totalMenu = (int) $pdo->query('SELECT COUNT(*) FROM menu WHERE is_active = 1')->fetchColumn();
$pending = (int) $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status = 'menunggu'")->fetchColumn();

$todayStmt = $pdo->query(
    "SELECT COUNT(*) AS jumlah, COALESCE(SUM(total), 0) AS omzet
     FROM pesanan WHERE DATE(created_at) = CURDATE() AND status <> 'dibatalkan'"
);
$today = $todayStmt->fetch();

$allStmt = $pdo->query(
    "SELECT COUNT(*) AS jumlah, COALESCE(SUM(total), 0) AS omzet FROM pesanan WHERE status <> 'dibatalkan'"
);
$allTime = $allStmt->fetch();

$recent = $pdo->query(
    'SELECT p.*, u.nama AS nama_pemesan, k.nama AS nama_kantin
     FROM pesanan p
     JOIN users u ON u.id = p.user_id
     JOIN kantin k ON k.id = p.kantin_id
     ORDER BY p.created_at DESC
     LIMIT 8'
)->fetchAll();

$topKantin = $pdo->query(
    "SELECT k.nama, COUNT(p.id) AS jumlah, COALESCE(SUM(p.total), 0) AS omzet
     FROM kantin k
     LEFT JOIN pesanan p ON p.kantin_id = k.id AND p.status <> 'dibatalkan'
     GROUP BY k.id, k.nama
     ORDER BY omzet DESC
     LIMIT 5"
)->fetchAll();

$lowStock = $pdo->query(
    'SELECT m.*, k.nama AS nama_kantin
     FROM menu m JOIN kantin k ON k.id = m.kantin_id
     WHERE m.is_active = 1 AND m.stok <= 5
     ORDER BY m.stok ASC, m.nama ASC
     LIMIT 6'
)->fetchAll();

$pageTitle = 'Dashboard admin';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <div class="page-head">
        <div>
            <span class="eyebrow">🛡️ Panel administrator</span>
            <h1>Ringkasan E-Kantin</h1>
            <p class="section-sub">Pantau pengguna, kantin, pesanan, dan performa penjualan.</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/laporan.php')) ?>">📊 Laporan</a>
            <a class="btn btn-primary btn-sm" href="<?= e(url('admin/kantin.php')) ?>">+ Kantin baru</a>
        </div>
    </div>

    <?php if ($pending > 0): ?>
        <div class="alert alert-warning alert-action">
            <span>⏳ <strong><?= $pending ?> pesanan</strong> masih menunggu konfirmasi dari kantin.</span>
            <a class="btn btn-sm btn-primary" href="<?= e(url('admin/laporan.php?status=menunggu')) ?>">Lihat laporan</a>
        </div>
    <?php endif; ?>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-icon brand">🧾</span>
            <div><strong><?= (int) $today['jumlah'] ?></strong><span>Pesanan hari ini</span></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon blue">💰</span>
            <div><strong><?= e(rupiah($today['omzet'])) ?></strong><span>Omzet hari ini</span></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon green">🎓</span>
            <div><strong><?= $totalSiswa ?></strong><span>Akun siswa</span></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon amber">🏪</span>
            <div><strong><?= $totalKantin ?></strong><span>Kantin terdaftar</span></div>
        </div>
    </div>

    <div class="two-col wide-left">
        <div class="panel">
            <div class="panel-head row-between">
                <div>
                    <h2>Pesanan terbaru</h2>
                    <p>8 transaksi terakhir dari seluruh kantin.</p>
                </div>
                <a class="link-more" href="<?= e(url('admin/laporan.php')) ?>">Lihat laporan →</a>
            </div>

            <?php if (!$recent): ?>
                <div class="empty-inline"><span>🧾</span><p>Belum ada pesanan masuk.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr><th>Kode</th><th>Pemesan</th><th>Kantin</th><th>Status</th><th>Total</th><th>Waktu</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $order): ?>
                                <?php $meta = status_meta($order['status']); ?>
                                <tr>
                                    <td><strong><?= e($order['kode']) ?></strong></td>
                                    <td><?= e($order['nama_pemesan']) ?></td>
                                    <td><?= e($order['nama_kantin']) ?></td>
                                    <td><span class="badge <?= e($meta['class']) ?>"><?= e($meta['label']) ?></span></td>
                                    <td class="nowrap"><?= e(rupiah($order['total'])) ?></td>
                                    <td class="nowrap muted"><?= e(tanggal_id($order['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="stack">
            <div class="panel">
                <div class="panel-head">
                    <h2>Omzet per kantin</h2>
                    <p>Total pesanan yang tidak dibatalkan.</p>
                </div>
                <ol class="rank-list">
                    <?php foreach ($topKantin as $index => $kantin): ?>
                        <li>
                            <span class="rank-number"><?= $index + 1 ?></span>
                            <div>
                                <strong><?= e($kantin['nama']) ?></strong>
                                <small><?= (int) $kantin['jumlah'] ?> pesanan</small>
                            </div>
                            <strong class="rank-value"><?= e(rupiah($kantin['omzet'])) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Stok menipis</h2>
                    <p>Menu aktif dengan stok ≤ 5.</p>
                </div>
                <?php if (!$lowStock): ?>
                    <p class="muted">Semua stok menu aman. 👌</p>
                <?php else: ?>
                    <div class="low-stock-list">
                        <?php foreach ($lowStock as $menu): ?>
                            <div class="low-stock-item">
                                <span class="table-emoji"><?= e(menu_emoji($menu['emoji'])) ?></span>
                                <div>
                                    <strong><?= e($menu['nama']) ?></strong>
                                    <small><?= e($menu['nama_kantin']) ?></small>
                                </div>
                                <span class="badge <?= (int) $menu['stok'] === 0 ? 'badge-red' : 'badge-amber' ?>"><?= (int) $menu['stok'] ?> sisa</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="quick-grid">
        <a class="quick-card" href="<?= e(url('admin/pengguna.php')) ?>">
            <span>👥</span><strong>Kelola pengguna</strong><small><?= $totalSiswa ?> siswa · kelola akun & peran</small>
        </a>
        <a class="quick-card" href="<?= e(url('admin/kantin.php')) ?>">
            <span>🏪</span><strong>Kelola kantin</strong><small><?= $totalKantin ?> kantin · profil & pengelola</small>
        </a>
        <a class="quick-card" href="<?= e(url('admin/kategori.php')) ?>">
            <span>🗂️</span><strong>Kategori menu</strong><small>Atur pengelompokan menu</small>
        </a>
        <a class="quick-card" href="<?= e(url('admin/laporan.php')) ?>">
            <span>📊</span><strong>Laporan penjualan</strong><small><?= (int) $allTime['jumlah'] ?> pesanan · <?= e(rupiah($allTime['omzet'])) ?></small>
        </a>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
