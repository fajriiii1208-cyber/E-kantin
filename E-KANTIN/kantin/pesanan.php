<?php
require __DIR__ . '/../config.php';

$user = require_role('kantin');
if (empty($user['kantin_id'])) {
    flash('error', 'Akunmu belum terhubung ke kantin. Hubungi admin untuk menautkannya.');
    redirect('akun.php');
}
$kantinId = (int) $user['kantin_id'];

$statusFilter = (string) ($_GET['status'] ?? '');
$allowedStatus = ['menunggu', 'diproses', 'siap', 'selesai', 'dibatalkan'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = '';
}
$periode = (string) ($_GET['periode'] ?? 'hari_ini');
if (!in_array($periode, ['hari_ini', 'minggu', 'semua'], true)) {
    $periode = 'hari_ini';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $order = fetch_order($pdo, $orderId);

    if (!$order || (int) $order['kantin_id'] !== $kantinId) {
        flash('error', 'Pesanan tidak ditemukan di kantinmu.');
        redirect('kantin/pesanan.php');
    }

    if ($action === 'status') {
        $target = post_str('status');
        if (!in_array($target, allowed_transitions($order['status']), true)) {
            flash('error', 'Perubahan status tidak valid untuk pesanan ini.');
        } elseif ($target === 'dibatalkan') {
            try {
                cancel_order($pdo, $order);
                flash('success', 'Pesanan ' . $order['kode'] . ' dibatalkan dan stok dikembalikan.');
            } catch (Throwable $exception) {
                flash('error', $exception->getMessage());
            }
        } else {
            $stmt = $pdo->prepare('UPDATE pesanan SET status = ? WHERE id = ?');
            $stmt->execute([$target, $order['id']]);

            $message = match ($target) {
                'diproses' => 'Pesanan diterima dan mulai diproses.',
                'siap'     => 'Pesanan ditandai siap diambil.',
                'selesai'  => 'Pesanan selesai. Terima kasih!',
                default    => 'Status pesanan diperbarui.',
            };
            flash('success', $message);
        }
    } elseif ($action === 'bayar') {
        $statusBayar = post_str('status_bayar') === 'lunas' ? 'lunas' : 'belum';
        $stmt = $pdo->prepare('UPDATE pesanan SET status_bayar = ? WHERE id = ?');
        $stmt->execute([$statusBayar, $order['id']]);
        flash('success', 'Status pembayaran diperbarui menjadi ' . $statusBayar . '.');
    } else {
        flash('error', 'Aksi tidak dikenal.');
    }

    $query = http_build_query(array_filter([
        'status'  => $statusFilter,
        'periode' => $periode,
    ]));
    redirect('kantin/pesanan.php' . ($query !== '' ? '?' . $query : ''));
}

$where = ['p.kantin_id = ?'];
$params = [$kantinId];

if ($statusFilter !== '') {
    $where[] = 'p.status = ?';
    $params[] = $statusFilter;
}
if ($periode === 'hari_ini') {
    $where[] = 'DATE(p.created_at) = CURDATE()';
} elseif ($periode === 'minggu') {
    $where[] = 'p.created_at >= NOW() - INTERVAL 7 DAY';
}

$stmt = $pdo->prepare(
    'SELECT p.*, u.nama AS nama_pemesan, u.no_hp
     FROM pesanan p JOIN users u ON u.id = p.user_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY p.created_at DESC'
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = array_map(static fn ($o) => (int) $o['id'], $orders);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $itemStmt = $pdo->prepare("SELECT * FROM pesanan_detail WHERE pesanan_id IN ($ph) ORDER BY id");
    $itemStmt->execute($ids);
    foreach ($itemStmt->fetchAll() as $item) {
        $itemsByOrder[(int) $item['pesanan_id']][] = $item;
    }
}

$counts = ['semua' => 0];
$countStmt = $pdo->prepare('SELECT status, COUNT(*) AS jumlah FROM pesanan WHERE kantin_id = ? GROUP BY status');
$countStmt->execute([$kantinId]);
foreach ($countStmt->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['jumlah'];
    $counts['semua'] += (int) $row['jumlah'];
}

$nextLabel = [
    'diproses' => '✅ Terima & proses',
    'siap'     => '🛎️ Tandai siap',
    'selesai'  => '🏁 Selesaikan',
];

$pageTitle = 'Pesanan masuk';
$active = 'pesanan';
require __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <div class="page-head">
        <div>
            <span class="eyebrow">Kelola pesanan</span>
            <h1>Pesanan masuk</h1>
            <p class="section-sub">Konfirmasi, proses, dan selesaikan pesanan siswa.</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('kantin/index.php')) ?>">← Dashboard</a>
    </div>

    <div class="toolbar">
        <div class="status-tabs flush">
            <?php
            $tabs = [
                ''           => ['label' => 'Semua', 'count' => $counts['semua'] ?? 0],
                'menunggu'   => ['label' => 'Menunggu', 'count' => $counts['menunggu'] ?? 0],
                'diproses'   => ['label' => 'Diproses', 'count' => $counts['diproses'] ?? 0],
                'siap'       => ['label' => 'Siap', 'count' => $counts['siap'] ?? 0],
                'selesai'    => ['label' => 'Selesai', 'count' => $counts['selesai'] ?? 0],
                'dibatalkan' => ['label' => 'Batal', 'count' => $counts['dibatalkan'] ?? 0],
            ];
            ?>
            <?php foreach ($tabs as $key => $tab): ?>
                <a class="status-tab <?= $statusFilter === $key ? 'active' : '' ?>"
                   href="<?= e(url('kantin/pesanan.php?' . http_build_query(array_filter(['status' => $key, 'periode' => $periode])))) ?>">
                    <?= e($tab['label']) ?> <span><?= (int) $tab['count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="get" class="toolbar-filter">
            <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
            <label class="select-wrap">
                <span>Periode</span>
                <select name="periode" data-autosubmit>
                    <option value="hari_ini" <?= $periode === 'hari_ini' ? 'selected' : '' ?>>Hari ini</option>
                    <option value="minggu" <?= $periode === 'minggu' ? 'selected' : '' ?>>7 hari terakhir</option>
                    <option value="semua" <?= $periode === 'semua' ? 'selected' : '' ?>>Semua</option>
                </select>
            </label>
        </form>
    </div>

    <?php if (!$orders): ?>
        <div class="empty-state">
            <span class="empty-emoji">📭</span>
            <h3>Tidak ada pesanan</h3>
            <p>Belum ada pesanan pada filter ini. Coba ubah periode atau status.</p>
            <a class="btn btn-ghost" href="<?= e(url('kantin/pesanan.php?periode=semua')) ?>">Lihat semua pesanan</a>
        </div>
    <?php else: ?>
        <div class="order-list">
            <?php foreach ($orders as $order): ?>
                <?php
                $meta = status_meta($order['status']);
                $orderItems = $itemsByOrder[(int) $order['id']] ?? [];
                ?>
                <article class="order-card static">
                    <div class="order-card-main">
                        <div class="order-card-top">
                            <span class="order-kode"><?= e($order['kode']) ?></span>
                            <span class="badge <?= e($meta['class']) ?>"><?= $meta['icon'] ?> <?= e($meta['label']) ?></span>
                            <span class="badge <?= $order['status_bayar'] === 'lunas' ? 'badge-green' : 'badge-amber' ?>">
                                <?= $order['status_bayar'] === 'lunas' ? 'Lunas' : 'Belum lunas' ?>
                            </span>
                        </div>
                        <h3>👤 <?= e($order['nama_pemesan']) ?><?= $order['no_hp'] ? ' · ' . e($order['no_hp']) : '' ?></h3>
                        <ul class="mini-items">
                            <?php foreach ($orderItems as $item): ?>
                                <li><span><?= (int) $item['qty'] ?>× <?= e($item['nama_menu']) ?></span><span><?= e(rupiah($item['subtotal'])) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($order['catatan']): ?>
                            <p class="order-note">📝 <?= e($order['catatan']) ?></p>
                        <?php endif; ?>
                        <div class="order-card-meta">
                            <span><?= e(tanggal_id($order['created_at'])) ?></span>
                            <span class="dot">·</span>
                            <span><?= e(metode_label($order['metode'])) ?></span>
                        </div>
                    </div>

                    <div class="order-card-side">
                        <strong class="order-total"><?= e(rupiah($order['total'])) ?></strong>
                        <div class="action-stack">
                            <?php foreach (array_diff(allowed_transitions($order['status']), ['dibatalkan']) as $next): ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="status" value="<?= e($next) ?>">
                                    <button class="btn btn-primary btn-sm btn-block" type="submit"><?= e($nextLabel[$next] ?? $next) ?></button>
                                </form>
                            <?php endforeach; ?>

                            <?php if (in_array('dibatalkan', allowed_transitions($order['status']), true)): ?>
                                <form method="post" data-confirm="Batalkan pesanan <?= e($order['kode']) ?>? Stok menu akan dikembalikan.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="status" value="dibatalkan">
                                    <button class="btn btn-ghost btn-sm btn-block btn-danger-text" type="submit">Batalkan</button>
                                </form>
                            <?php endif; ?>

                            <?php if (in_array($order['status'], ['diproses', 'siap', 'selesai'], true)): ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="bayar">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="status_bayar" value="<?= $order['status_bayar'] === 'lunas' ? 'belum' : 'lunas' ?>">
                                    <button class="btn btn-ghost btn-sm btn-block" type="submit">
                                        <?= $order['status_bayar'] === 'lunas' ? 'Tandai belum lunas' : '💰 Tandai lunas' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
