<?php
require __DIR__ . '/../config.php';

$user = require_role('kantin');
if (empty($user['kantin_id'])) {
    flash('error', 'Akunmu belum terhubung ke kantin. Hubungi admin untuk menautkannya.');
    redirect('akun.php');
}
$kantinId = (int) $user['kantin_id'];

/* ------------------------------------------------------------------
   Aksi cepat: perbarui stok & ketersediaan
   ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');
    $menuId = (int) ($_POST['menu_id'] ?? 0);
    $back = safe_next(post_str('back'), url('kantin/stok.php'));

    $stmt = $pdo->prepare('SELECT * FROM menu WHERE id = ? AND kantin_id = ? LIMIT 1');
    $stmt->execute([$menuId, $kantinId]);
    $menu = $stmt->fetch();

    if (!$menu) {
        flash('error', 'Menu tidak ditemukan di kantinmu.');
        redirect_raw($back);
    }

    if ($action === 'simpan') {
        $stok = max(0, min(9999, (int) ($_POST['stok'] ?? $menu['stok'])));
        $update = $pdo->prepare('UPDATE menu SET stok = ? WHERE id = ? AND kantin_id = ?');
        $update->execute([$stok, $menuId, $kantinId]);
        flash('success', 'Stok "' . $menu['nama'] . '" diperbarui menjadi ' . $stok . ' porsi.');
    } elseif ($action === 'habis') {
        $update = $pdo->prepare('UPDATE menu SET stok = 0 WHERE id = ? AND kantin_id = ?');
        $update->execute([$menuId, $kantinId]);
        flash('success', '"' . $menu['nama'] . '" ditandai habis.');
    } elseif ($action === 'toggle') {
        $update = $pdo->prepare('UPDATE menu SET is_active = 1 - is_active WHERE id = ? AND kantin_id = ?');
        $update->execute([$menuId, $kantinId]);
        $status = (int) $menu['is_active'] === 1 ? 'dinonaktifkan' : 'diaktifkan';
        flash('success', 'Menu "' . $menu['nama'] . '" ' . $status . '.');
    } else {
        flash('error', 'Aksi tidak dikenal.');
    }

    redirect_raw($back);
}

/* ------------------------------------------------------------------
   Data menu & status ketersediaan
   ------------------------------------------------------------------ */
$stmt = $pdo->prepare(
    'SELECT m.*, kat.nama AS nama_kategori, kat.ikon AS ikon_kategori
     FROM menu m
     LEFT JOIN kategori kat ON kat.id = m.kategori_id
     WHERE m.kantin_id = ?
     ORDER BY m.nama ASC'
);
$stmt->execute([$kantinId]);
$allMenus = $stmt->fetchAll();

$counts = ['semua' => 0, 'tersedia' => 0, 'menipis' => 0, 'habis' => 0, 'nonaktif' => 0];
$menus = [];

foreach ($allMenus as $menu) {
    $stok = (int) $menu['stok'];
    if ((int) $menu['is_active'] !== 1) {
        $status = 'nonaktif';
    } elseif ($stok <= 0) {
        $status = 'habis';
    } elseif ($stok <= 5) {
        $status = 'menipis';
    } else {
        $status = 'tersedia';
    }

    $menu['status_kode'] = $status;
    $counts['semua']++;
    $counts[$status]++;
    $menus[] = $menu;
}

$statusMeta = [
    'tersedia' => ['label' => 'Tersedia', 'class' => 'badge-green'],
    'menipis'  => ['label' => 'Stok menipis', 'class' => 'badge-amber'],
    'habis'    => ['label' => 'Habis', 'class' => 'badge-red'],
    'nonaktif' => ['label' => 'Nonaktif', 'class' => 'badge-gray'],
];

$filter = (string) ($_GET['status'] ?? 'semua');
if (!array_key_exists($filter, $counts)) {
    $filter = 'semua';
}
$q = trim((string) ($_GET['q'] ?? ''));

if ($filter !== 'semua' || $q !== '') {
    $menus = array_values(array_filter($menus, static function (array $menu) use ($filter, $q): bool {
        if ($filter !== 'semua' && $menu['status_kode'] !== $filter) {
            return false;
        }
        if ($q !== '') {
            $haystack = mb_strtolower($menu['nama'] . ' ' . ($menu['deskripsi'] ?? '') . ' ' . ($menu['nama_kategori'] ?? ''));
            if (!str_contains($haystack, mb_strtolower($q))) {
                return false;
            }
        }
        return true;
    }));
}

$priority = ['habis' => 0, 'menipis' => 1, 'tersedia' => 2, 'nonaktif' => 3];
usort($menus, static function (array $a, array $b) use ($priority): int {
    $order = ($priority[$a['status_kode']] ?? 9) <=> ($priority[$b['status_kode']] ?? 9);
    return $order !== 0 ? $order : strcasecmp($a['nama'], $b['nama']);
});

$filterQuery = http_build_query(array_filter([
    'status' => $filter !== 'semua' ? $filter : '',
    'q'      => $q,
], static fn (string $value): bool => $value !== ''));
$backUrl = url('kantin/stok.php' . ($filterQuery !== '' ? '?' . $filterQuery : ''));
$perhatian = $counts['habis'] + $counts['menipis'];

$pageTitle = 'Stok & ketersediaan';
$active = 'stok';
require __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <div class="page-head">
        <div>
            <span class="eyebrow">📦 Ketersediaan menu</span>
            <h1>Stok & ketersediaan</h1>
            <p class="section-sub">Perbarui jumlah porsi dan status jual menu secara cepat. Menu dengan stok 0 otomatis tampil habis di halaman siswa.</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('kantin/menu.php')) ?>">🍽️ Kelola menu</a>
            <a class="btn btn-primary btn-sm" href="<?= e(url('kantin/menu.php')) ?>">+ Tambah menu</a>
        </div>
    </div>

    <?php if ($perhatian > 0): ?>
        <div class="alert alert-warning alert-action">
            <span>🔔 <strong><?= $counts['habis'] ?> menu habis</strong> dan <strong><?= $counts['menipis'] ?> menu stok menipis</strong>. Perbarui stok agar siswa tetap bisa memesan.</span>
            <a class="btn btn-sm btn-primary" href="<?= e(url('kantin/stok.php?status=habis')) ?>">Lihat yang habis</a>
        </div>
    <?php endif; ?>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="stat-icon brand">📦</span>
            <div><strong><?= $counts['semua'] ?></strong><span>Total menu</span></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon green">✅</span>
            <div><strong><?= $counts['tersedia'] ?></strong><span>Tersedia</span></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon amber">⚠️</span>
            <div><strong><?= $counts['menipis'] ?></strong><span>Stok menipis</span></div>
        </div>
        <div class="stat-card">
            <span class="stat-icon red">🚫</span>
            <div><strong><?= $counts['habis'] + $counts['nonaktif'] ?></strong><span>Habis / nonaktif</span></div>
        </div>
    </div>

    <div class="chips">
        <?php
        $filterTabs = [
            'semua'    => ['label' => '🍽️ Semua', 'count' => $counts['semua']],
            'tersedia' => ['label' => '✅ Tersedia', 'count' => $counts['tersedia']],
            'menipis'  => ['label' => '⚠️ Menipis', 'count' => $counts['menipis']],
            'habis'    => ['label' => '🚫 Habis', 'count' => $counts['habis']],
            'nonaktif' => ['label' => '💤 Nonaktif', 'count' => $counts['nonaktif']],
        ];
        ?>
        <?php foreach ($filterTabs as $key => $tab): ?>
            <?php
            $chipQuery = http_build_query(array_filter([
                'status' => $key !== 'semua' ? $key : '',
                'q'      => $q,
            ], static fn (string $value): bool => $value !== ''));
            ?>
            <a class="chip <?= $filter === $key ? 'active' : '' ?>" href="<?= e(url('kantin/stok.php' . ($chipQuery !== '' ? '?' . $chipQuery : ''))) ?>">
                <?= $tab['label'] ?> <span><?= $tab['count'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="filter-bar" method="get" action="<?= e(url('kantin/stok.php')) ?>">
        <?php if ($filter !== 'semua'): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
        <label class="field grow stock-search-field">
            <span>Cari menu</span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama menu, kategori, atau deskripsi…">
        </label>
        <button class="btn btn-primary btn-sm" type="submit">Cari</button>
        <?php if ($q !== '' || $filter !== 'semua'): ?>
            <a class="link-reset" href="<?= e(url('kantin/stok.php')) ?>">Reset filter</a>
        <?php endif; ?>
        <span class="filter-result"><?= count($menus) ?> menu ditampilkan</span>
    </form>

    <?php if (!$menus): ?>
        <div class="empty-state">
            <span class="empty-emoji">📦</span>
            <h3>Tidak ada menu pada filter ini</h3>
            <p>Coba ubah kata kunci atau pilih filter lain.</p>
            <a class="btn btn-primary" href="<?= e(url('kantin/stok.php')) ?>">Lihat semua menu</a>
        </div>
    <?php else: ?>
        <div class="stock-grid">
            <?php foreach ($menus as $menu): ?>
                <?php
                $stok = (int) $menu['stok'];
                $status = $menu['status_kode'];
                $meta = $statusMeta[$status];
                $isActive = (int) $menu['is_active'] === 1;
                $meter = $stok <= 0 ? 0 : min(100, (int) round($stok / 30 * 100));
                ?>
                <article class="stock-card is-<?= e($status) ?>">
                    <div class="stock-card-head">
                        <span class="stock-emoji"><?= e(menu_emoji($menu['emoji'])) ?></span>
                        <div class="stock-info">
                            <strong><?= e($menu['nama']) ?></strong>
                            <small><?= e($menu['ikon_kategori'] ?: '🍽️') ?> <?= e($menu['nama_kategori'] ?: 'Lainnya') ?> · <?= e(rupiah($menu['harga'])) ?></small>
                        </div>
                        <span class="badge <?= e($meta['class']) ?>"><?= e($meta['label']) ?></span>
                    </div>

                    <div class="stock-meter <?= $status === 'menipis' ? 'warn' : ($status === 'habis' ? 'danger' : ($status === 'nonaktif' ? 'muted' : '')) ?>">
                        <span style="width: <?= $meter ?>%"></span>
                    </div>

                    <div class="stock-numbers">
                        <span>Stok saat ini</span>
                        <strong><?= $stok ?> <small>porsi</small></strong>
                    </div>

                    <form method="post" class="stock-form" data-stock-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="simpan">
                        <input type="hidden" name="menu_id" value="<?= (int) $menu['id'] ?>">
                        <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                        <div class="stock-stepper">
                            <button type="button" class="stock-step" data-stock-step="-1" aria-label="Kurangi stok <?= e($menu['nama']) ?>">−</button>
                            <input type="number" name="stok" value="<?= $stok ?>" min="0" max="9999" inputmode="numeric" aria-label="Stok <?= e($menu['nama']) ?>">
                            <button type="button" class="stock-step" data-stock-step="1" aria-label="Tambah stok <?= e($menu['nama']) ?>">+</button>
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit">Simpan</button>
                    </form>

                    <div class="stock-actions">
                        <?php if ($stok > 0): ?>
                            <form method="post" data-confirm="Tandai &quot;<?= e($menu['nama']) ?>&quot; habis?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="habis">
                                <input type="hidden" name="menu_id" value="<?= (int) $menu['id'] ?>">
                                <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                                <button class="btn btn-ghost btn-sm btn-danger-text" type="submit">Tandai habis</button>
                            </form>
                        <?php endif; ?>

                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="menu_id" value="<?= (int) $menu['id'] ?>">
                            <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                            <button class="btn btn-ghost btn-sm" type="submit"><?= $isActive ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                        </form>

                        <a class="link-more" href="<?= e(url('kantin/menu.php?edit=' . (int) $menu['id'])) ?>">Edit menu →</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
