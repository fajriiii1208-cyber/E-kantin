<?php
require __DIR__ . '/../config.php';

$user = require_role('admin');

$dari = (string) ($_GET['dari'] ?? date('Y-m-01'));
$sampai = (string) ($_GET['sampai'] ?? date('Y-m-d'));
$validDate = static fn (string $value): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);

if (!$validDate($dari)) {
    $dari = date('Y-m-01');
}
if (!$validDate($sampai)) {
    $sampai = date('Y-m-d');
}
if ($dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}

$statusFilter = (string) ($_GET['status'] ?? '');
$allowedStatus = ['menunggu', 'diproses', 'siap', 'selesai', 'dibatalkan'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = '';
}
$kantinFilter = (int) ($_GET['kantin'] ?? 0);
$kantins = $pdo->query('SELECT id, nama FROM kantin ORDER BY nama')->fetchAll();

$where = ['DATE(p.created_at) BETWEEN ? AND ?'];
$params = [$dari, $sampai];
if ($statusFilter !== '') {
    $where[] = 'p.status = ?';
    $params[] = $statusFilter;
}
if ($kantinFilter > 0) {
    $where[] = 'p.kantin_id = ?';
    $params[] = $kantinFilter;
}
$whereSql = implode(' AND ', $where);

$sql = "SELECT p.*, u.nama AS nama_pemesan, k.nama AS nama_kantin
        FROM pesanan p
        JOIN users u ON u.id = p.user_id
        JOIN kantin k ON k.id = p.kantin_id
        WHERE $whereSql
        ORDER BY p.created_at DESC";

/* ------------------------------------------------------------------
   Ekspor CSV
   ------------------------------------------------------------------ */
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $pdo->prepare($sql . ' LIMIT 5000');
    $stmt->execute($params);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="laporan-e-kantin-' . $dari . '-hingga-' . $sampai . '.csv"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Kode', 'Tanggal', 'Pemesan', 'Kantin', 'Metode', 'Status', 'Status Bayar', 'Total', 'Catatan']);

    $safe = static function (?string $value): string {
        $value = (string) $value;
        return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
    };

    foreach ($stmt->fetchAll() as $row) {
        fputcsv($output, [
            $safe($row['kode']),
            date('Y-m-d H:i', strtotime($row['created_at'])),
            $safe($row['nama_pemesan']),
            $safe($row['nama_kantin']),
            metode_label($row['metode']),
            status_meta($row['status'])['label'],
            $row['status_bayar'] === 'lunas' ? 'Lunas' : 'Belum lunas',
            (int) $row['total'],
            $safe($row['catatan'] ?? ''),
        ]);
    }
    fclose($output);
    exit;
}

$stmt = $pdo->prepare($sql . ' LIMIT 500');
$stmt->execute($params);
$orders = $stmt->fetchAll();

$summaryStmt = $pdo->prepare(
    "SELECT COUNT(*) AS jumlah,
            COALESCE(SUM(CASE WHEN p.status <> 'dibatalkan' THEN p.total ELSE 0 END), 0) AS omzet
     FROM pesanan p WHERE $whereSql"
);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$itemSql = "SELECT COALESCE(SUM(d.qty), 0)
            FROM pesanan_detail d
            JOIN pesanan p ON p.id = d.pesanan_id
            WHERE $whereSql AND p.status <> 'dibatalkan'";
$itemStmt = $pdo->prepare($itemSql);
$itemStmt->execute($params);
$totalItems = (int) $itemStmt->fetchColumn();

$average = (int) $summary['jumlah'] > 0 ? (int) $summary['omzet'] / (int) $summary['jumlah'] : 0;

$queryString = http_build_query(array_filter([
    'dari'   => $dari,
    'sampai' => $sampai,
    'status' => $statusFilter,
    'kantin' => $kantinFilter,
]));

$pageTitle = 'Laporan penjualan';
$active = 'laporan';
require __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <div class="page-head">
        <div>
            <span class="eyebrow">Analitik penjualan</span>
            <h1>Laporan penjualan</h1>
            <p class="section-sub">Rekap transaksi seluruh kantin berdasarkan periode.</p>
        </div>
        <a class="btn btn-primary btn-sm" href="<?= e(url('admin/laporan.php?' . $queryString . '&export=csv')) ?>">⬇️ Ekspor CSV</a>
    </div>

    <form class="report-filter" method="get">
        <label class="field">
            <span>Dari tanggal</span>
            <input type="date" name="dari" value="<?= e($dari) ?>">
        </label>
        <label class="field">
            <span>Sampai tanggal</span>
            <input type="date" name="sampai" value="<?= e($sampai) ?>">
        </label>
        <label class="field">
            <span>Kantin</span>
            <select name="kantin">
                <option value="0">Semua kantin</option>
                <?php foreach ($kantins as $kantin): ?>
                    <option value="<?= (int) $kantin['id'] ?>" <?= $kantinFilter === (int) $kantin['id'] ? 'selected' : '' ?>><?= e($kantin['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Status</span>
            <select name="status">
                <option value="">Semua status</option>
                <?php foreach ($allowedStatus as $status): ?>
                    <?php $meta = status_meta($status); ?>
                    <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Terapkan</button>
    </form>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-icon blue">🧾</span>
            <div><strong><?= (int) $summary['jumlah'] ?></strong><span>Pesanan</span></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon green">💰</span>
            <div><strong><?= e(rupiah($summary['omzet'])) ?></strong><span>Omzet (tanpa batal)</span></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon amber">🍽️</span>
            <div><strong><?= number_format($totalItems, 0, ',', '.') ?></strong><span>Item terjual</span></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon brand">📈</span>
            <div><strong><?= e(rupiah($average)) ?></strong><span>Rata-rata / pesanan</span></div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head row-between">
            <div>
                <h2>Transaksi <?= e(date('d/m/Y', strtotime($dari))) ?> – <?= e(date('d/m/Y', strtotime($sampai))) ?></h2>
                <p>Menampilkan maksimal 500 baris. Ekspor CSV memuat hingga 5.000 baris.</p>
            </div>
        </div>

        <?php if (!$orders): ?>
            <div class="empty-inline"><span>📊</span><p>Tidak ada transaksi pada filter ini.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Kode</th><th>Tanggal</th><th>Pemesan</th><th>Kantin</th>
                            <th>Metode</th><th>Status</th><th>Bayar</th><th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php $meta = status_meta($order['status']); ?>
                            <tr>
                                <td><strong><?= e($order['kode']) ?></strong></td>
                                <td class="nowrap muted"><?= e(tanggal_id($order['created_at'])) ?></td>
                                <td><?= e($order['nama_pemesan']) ?></td>
                                <td><?= e($order['nama_kantin']) ?></td>
                                <td><?= e(metode_label($order['metode'])) ?></td>
                                <td><span class="badge <?= e($meta['class']) ?>"><?= e($meta['label']) ?></span></td>
                                <td><span class="badge <?= $order['status_bayar'] === 'lunas' ? 'badge-green' : 'badge-amber' ?>"><?= $order['status_bayar'] === 'lunas' ? 'Lunas' : 'Belum' ?></span></td>
                                <td class="nowrap"><strong><?= e(rupiah($order['total'])) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
