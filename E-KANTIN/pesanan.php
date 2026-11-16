<?php
require __DIR__ . '/config.php';

$user = require_role('siswa');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (post_str('action') === 'cancel') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $order = fetch_order($pdo, $orderId);

        if (!$order || (int) $order['user_id'] !== (int) $user['id']) {
            flash('error', 'Pesanan tidak ditemukan.');
        } elseif ($order['status'] !== 'menunggu') {
            flash('error', 'Pesanan hanya bisa dibatalkan saat masih menunggu konfirmasi kantin.');
        } else {
            try {
                cancel_order($pdo, $order);
                flash('success', 'Pesanan ' . $order['kode'] . ' dibatalkan.');
            } catch (Throwable $exception) {
                flash('error', $exception->getMessage());
            }
        }
    }
    redirect('pesanan.php');
}

/* ------------------------------------------------------------------
   Detail pesanan
   ------------------------------------------------------------------ */
$orderId = (int) ($_GET['id'] ?? 0);
if ($orderId > 0) {
    $order = fetch_order($pdo, $orderId);
    if (!$order || (int) $order['user_id'] !== (int) $user['id']) {
        flash('error', 'Pesanan tidak ditemukan.');
        redirect('pesanan.php');
    }

    $items = fetch_order_items($pdo, $orderId);
    $step = status_step($order['status']);
    $meta = status_meta($order['status']);
    $steps = [
        1 => ['label' => 'Menunggu konfirmasi', 'desc' => 'Kantin menerima pesananmu'],
        2 => ['label' => 'Sedang diproses', 'desc' => 'Makanan sedang disiapkan'],
        3 => ['label' => 'Siap diambil', 'desc' => 'Ambil di counter kantin'],
        4 => ['label' => 'Selesai', 'desc' => 'Pesanan sudah diambil'],
    ];

    $pageTitle = 'Pesanan ' . $order['kode'];
    $active = 'pesanan';
    require __DIR__ . '/includes/header.php';
    ?>

    <section class="section narrow">
        <a class="back-link" href="<?= e(url('pesanan.php')) ?>">← Semua pesanan</a>

        <div class="order-detail-head">
            <div>
                <span class="eyebrow">Kode pesanan</span>
                <h1 class="order-code"><?= e($order['kode']) ?></h1>
                <p class="section-sub">Dibuat <?= e(tanggal_id($order['created_at'])) ?> · <?= e($order['nama_kantin']) ?></p>
            </div>
            <span class="badge <?= e($meta['class']) ?> badge-lg"><?= $meta['icon'] ?> <?= e($meta['label']) ?></span>
        </div>

        <?php if ($order['status'] === 'dibatalkan'): ?>
            <div class="alert alert-error">Pesanan ini dibatalkan. Stok menu sudah dikembalikan ke kantin.</div>
        <?php else: ?>
            <div class="timeline-card">
                <ol class="timeline">
                    <?php foreach ($steps as $number => $info): ?>
                        <li class="<?= $number < $step ? 'done' : ($number === $step ? 'current' : '') ?>">
                            <span class="timeline-dot"><?= $number < $step ? '✓' : $number ?></span>
                            <div>
                                <strong><?= e($info['label']) ?></strong>
                                <small><?= e($info['desc']) ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if ($order['status'] === 'siap'): ?>
                    <div class="pickup-banner">🎉 Pesananmu sudah siap! Tunjukkan kode <strong><?= e($order['kode']) ?></strong> di <?= e($order['nama_kantin']) ?>.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="two-col">
            <div class="panel">
                <div class="panel-head">
                    <h2>Rincian pesanan</h2>
                    <p><?= e($order['nama_kantin']) ?> · <?= e($order['nama_pemesan']) ?></p>
                </div>
                <div class="item-list">
                    <?php foreach ($items as $item): ?>
                        <div class="item-row">
                            <span class="item-qty"><?= (int) $item['qty'] ?>×</span>
                            <div class="item-name">
                                <strong><?= e($item['nama_menu']) ?></strong>
                                <small><?= e(rupiah($item['harga'])) ?> per porsi</small>
                            </div>
                            <strong><?= e(rupiah($item['subtotal'])) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="item-total">
                    <span>Total</span>
                    <strong><?= e(rupiah($order['total'])) ?></strong>
                </div>
                <?php if ($order['catatan']): ?>
                    <div class="info-box">📝 Catatan: <?= e($order['catatan']) ?></div>
                <?php endif; ?>
            </div>

            <aside class="panel">
                <h2>Info pembayaran</h2>
                <div class="summary-row">
                    <span>Metode</span>
                    <strong><?= e(metode_label($order['metode'])) ?></strong>
                </div>
                <div class="summary-row">
                    <span>Status bayar</span>
                    <span class="badge <?= $order['status_bayar'] === 'lunas' ? 'badge-green' : 'badge-amber' ?>">
                        <?= $order['status_bayar'] === 'lunas' ? 'Lunas' : 'Belum lunas' ?>
                    </span>
                </div>
                <div class="summary-row">
                    <span>Diperbarui</span>
                    <span><?= e(tanggal_id($order['updated_at'])) ?></span>
                </div>
                <div class="info-box small">
                    <?php if ($order['metode'] === 'qris'): ?>
                        📱 Tunjukkan bukti pembayaran QRIS ke kantin saat mengambil pesanan.
                    <?php else: ?>
                        💵 Siapkan uang tunai dan bayar langsung di counter kantin.
                    <?php endif; ?>
                </div>

                <?php if ($order['status'] === 'menunggu'): ?>
                    <form method="post" class="stack-form" data-confirm="Yakin ingin membatalkan pesanan ini?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                        <button class="btn btn-danger btn-block" type="submit">Batalkan pesanan</button>
                    </form>
                <?php endif; ?>
                <a class="btn btn-ghost btn-block" href="<?= e(url('index.php?kantin=' . (int) $order['kantin_id'])) ?>">Pesan menu lain</a>
            </aside>
        </div>
    </section>

    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ------------------------------------------------------------------
   Daftar pesanan
   ------------------------------------------------------------------ */
$statusFilter = (string) ($_GET['status'] ?? '');
$allowedStatus = ['menunggu', 'diproses', 'siap', 'selesai', 'dibatalkan'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = '';
}

$counts = ['semua' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) AS jumlah FROM pesanan WHERE user_id = ' . (int) $user['id'] . ' GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['jumlah'];
    $counts['semua'] += (int) $row['jumlah'];
}

$sql = 'SELECT p.*, k.nama AS nama_kantin, k.lokasi
        FROM pesanan p
        JOIN kantin k ON k.id = p.kantin_id
        WHERE p.user_id = ?';
$params = [$user['id']];
if ($statusFilter !== '') {
    $sql .= ' AND p.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY p.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $orderIds = array_map(static fn ($o) => (int) $o['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStmt = $pdo->prepare("SELECT * FROM pesanan_detail WHERE pesanan_id IN ($placeholders) ORDER BY id");
    $itemStmt->execute($orderIds);
    foreach ($itemStmt->fetchAll() as $item) {
        $itemsByOrder[(int) $item['pesanan_id']][] = $item;
    }
}

$pageTitle = 'Pesanan saya';
$active = 'pesanan';
require __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="page-head">
        <div>
            <span class="eyebrow">Riwayat & status</span>
            <h1>Pesanan saya</h1>
            <p class="section-sub">Pantau status pesanan dan ambil saat sudah siap.</p>
        </div>
        <a class="btn btn-primary btn-sm" href="<?= e(url('index.php')) ?>">+ Pesan lagi</a>
    </div>

    <div class="status-tabs">
        <?php
        $tabs = [
            ''           => ['label' => 'Semua', 'count' => $counts['semua'] ?? 0],
            'menunggu'   => ['label' => 'Menunggu', 'count' => $counts['menunggu'] ?? 0],
            'diproses'   => ['label' => 'Diproses', 'count' => $counts['diproses'] ?? 0],
            'siap'       => ['label' => 'Siap diambil', 'count' => $counts['siap'] ?? 0],
            'selesai'    => ['label' => 'Selesai', 'count' => $counts['selesai'] ?? 0],
            'dibatalkan' => ['label' => 'Dibatalkan', 'count' => $counts['dibatalkan'] ?? 0],
        ];
        ?>
        <?php foreach ($tabs as $key => $tab): ?>
            <a class="status-tab <?= $statusFilter === $key ? 'active' : '' ?>" href="<?= e(url('pesanan.php' . ($key !== '' ? '?status=' . $key : ''))) ?>">
                <?= e($tab['label']) ?> <span><?= (int) $tab['count'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$orders): ?>
        <div class="empty-state">
            <span class="empty-emoji">🧾</span>
            <h3>Belum ada pesanan</h3>
            <p>Pesanan yang kamu buat akan muncul di sini beserta statusnya.</p>
            <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Mulai pesan</a>
        </div>
    <?php else: ?>
        <div class="order-list">
            <?php foreach ($orders as $order): ?>
                <?php
                $meta = status_meta($order['status']);
                $orderItems = $itemsByOrder[(int) $order['id']] ?? [];
                $names = array_map(static fn ($i) => $i['nama_menu'] . ' ×' . $i['qty'], $orderItems);
                ?>
                <a class="order-card" href="<?= e(url('pesanan.php?id=' . (int) $order['id'])) ?>">
                    <div class="order-card-main">
                        <div class="order-card-top">
                            <span class="order-kode"><?= e($order['kode']) ?></span>
                            <span class="badge <?= e($meta['class']) ?>"><?= $meta['icon'] ?> <?= e($meta['label']) ?></span>
                        </div>
                        <h3>🏪 <?= e($order['nama_kantin']) ?></h3>
                        <p class="order-items"><?= e(implode(', ', $names)) ?></p>
                        <div class="order-card-meta">
                            <span><?= e(tanggal_id($order['created_at'])) ?></span>
                            <span class="dot">·</span>
                            <span><?= e(metode_label($order['metode'])) ?></span>
                            <span class="dot">·</span>
                            <span class="<?= $order['status_bayar'] === 'lunas' ? 'text-success' : 'text-danger' ?>">
                                <?= $order['status_bayar'] === 'lunas' ? 'Lunas' : 'Belum lunas' ?>
                            </span>
                        </div>
                    </div>
                    <div class="order-card-side">
                        <strong><?= e(rupiah($order['total'])) ?></strong>
                        <span class="link-detail">Lihat detail →</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
