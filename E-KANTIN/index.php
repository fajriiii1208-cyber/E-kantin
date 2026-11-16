<?php
require __DIR__ . '/config.php';

$user = current_user();

$q          = trim((string) ($_GET['q'] ?? ''));
$kategoriId = (int) ($_GET['kategori'] ?? 0);
$kantinId   = (int) ($_GET['kantin'] ?? 0);
$sort       = (string) ($_GET['sort'] ?? 'populer');
$allowedSort = ['populer', 'termurah', 'termahal', 'nama'];
if (!in_array($sort, $allowedSort, true)) {
    $sort = 'populer';
}

$kantins    = $pdo->query('SELECT * FROM kantin ORDER BY nama')->fetchAll();
$kategoris  = $pdo->query(
    'SELECT k.*, (SELECT COUNT(*) FROM menu m WHERE m.kategori_id = k.id AND m.is_active = 1) AS jumlah
     FROM kategori k ORDER BY k.nama'
)->fetchAll();

$where  = ['m.is_active = 1'];
$params = [];

if ($q !== '') {
    $where[]  = '(m.nama LIKE ? OR m.deskripsi LIKE ? OR k.nama LIKE ?)';
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($kategoriId > 0) {
    $where[]  = 'm.kategori_id = ?';
    $params[] = $kategoriId;
}
if ($kantinId > 0) {
    $where[]  = 'm.kantin_id = ?';
    $params[] = $kantinId;
}

$orderBy = match ($sort) {
    'termurah' => 'm.harga ASC, m.nama ASC',
    'termahal' => 'm.harga DESC, m.nama ASC',
    'nama'     => 'm.nama ASC',
    default    => '(SELECT COALESCE(SUM(d.qty), 0) FROM pesanan_detail d JOIN pesanan p ON p.id = d.pesanan_id WHERE d.menu_id = m.id AND p.status <> "dibatalkan") DESC, m.nama ASC',
};

$sql = 'SELECT m.*, k.nama AS nama_kantin, k.lokasi, k.is_open, kat.nama AS nama_kategori, kat.ikon AS ikon_kategori
        FROM menu m
        JOIN kantin k ON k.id = m.kantin_id
        LEFT JOIN kategori kat ON kat.id = m.kategori_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $orderBy;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$menus = $stmt->fetchAll();

$totalMenu    = (int) $pdo->query('SELECT COUNT(*) FROM menu WHERE is_active = 1')->fetchColumn();
$totalKantin  = (int) $pdo->query('SELECT COUNT(*) FROM kantin')->fetchColumn();
$totalPesanan = (int) $pdo->query("SELECT COUNT(*) FROM pesanan WHERE status <> 'dibatalkan'")->fetchColumn();
$highlight    = array_slice($menus, 0, 3);

$cartItems = cart_items($pdo);
$cartTotal = array_sum(array_column($cartItems, 'subtotal'));

$pageTitle = 'Pesan makanan kantin';
$active = 'menu';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero-copy">
        <span class="eyebrow">🍽️ Kantin kampus online</span>
        <h1>Makan enak,<br><span class="text-brand">tanpa antre.</span></h1>
        <p class="hero-lead">Pesan makanan favoritmu dari kantin kampus. Pesanan disiapkan, kamu tinggal ambil saat statusnya <strong>siap</strong>.</p>

        <form class="search-form" method="get" action="<?= e(url('index.php')) ?>" role="search">
            <span class="search-icon">🔍</span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nasi geprek, mie ayam, es teh…" aria-label="Cari menu">
            <?php if ($kategoriId): ?><input type="hidden" name="kategori" value="<?= $kategoriId ?>"><?php endif; ?>
            <?php if ($kantinId): ?><input type="hidden" name="kantin" value="<?= $kantinId ?>"><?php endif; ?>
            <button class="btn btn-primary" type="submit">Cari</button>
        </form>

        <div class="hero-stats">
            <div><strong><?= number_format($totalMenu, 0, ',', '.') ?></strong><span>menu tersedia</span></div>
            <div><strong><?= number_format($totalKantin, 0, ',', '.') ?></strong><span>kantin aktif</span></div>
            <div><strong><?= number_format($totalPesanan, 0, ',', '.') ?></strong><span>pesanan diproses</span></div>
        </div>

        <?php if (!$user): ?>
            <div class="hero-note">
                <span>💡</span>
                <p>Masuk sebagai siswa untuk mulai memesan. Demo akun tersedia di halaman <a href="<?= e(url('login.php')) ?>">Masuk</a>.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="hero-art" aria-hidden="true">
        <div class="hero-blob"></div>
        <?php foreach ($highlight as $i => $item): ?>
            <div class="food-float food-float-<?= $i + 1 ?>">
                <span class="food-emoji"><?= e(menu_emoji($item['emoji'])) ?></span>
                <div>
                    <strong><?= e($item['nama']) ?></strong>
                    <small><?= e($item['nama_kantin']) ?></small>
                </div>
                <span class="food-price"><?= e(rupiah($item['harga'])) ?></span>
            </div>
        <?php endforeach; ?>
        <div class="hero-ticket">
            <span>✅</span>
            <div>
                <strong>Pesanan siap!</strong>
                <small>Ambil di counter kantin</small>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2>Jelajahi menu</h2>
            <p class="section-sub">Pilih kategori atau cari langsung makanan favoritmu.</p>
        </div>
        <?php if ($q !== '' || $kategoriId || $kantinId): ?>
            <a class="link-reset" href="<?= e(url('index.php')) ?>">Reset filter</a>
        <?php endif; ?>
    </div>

    <div class="chips">
        <a class="chip <?= $kategoriId === 0 ? 'active' : '' ?>" href="<?= e(url('index.php' . ($q !== '' ? '?q=' . urlencode($q) : ''))) ?>">🍽️ Semua <span><?= $totalMenu ?></span></a>
        <?php foreach ($kategoris as $kat): ?>
            <?php
            $chipParams = ['kategori' => $kat['id']];
            if ($q !== '') {
                $chipParams['q'] = $q;
            }
            if ($kantinId) {
                $chipParams['kantin'] = $kantinId;
            }
            ?>
            <a class="chip <?= $kategoriId === (int) $kat['id'] ? 'active' : '' ?>"
               href="<?= e(url('index.php?' . http_build_query($chipParams))) ?>">
                <?= e($kat['ikon']) ?> <?= e($kat['nama']) ?> <span><?= (int) $kat['jumlah'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <form class="filter-bar" method="get" action="<?= e(url('index.php')) ?>">
        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
        <?php if ($kategoriId): ?><input type="hidden" name="kategori" value="<?= $kategoriId ?>"><?php endif; ?>
        <label class="select-wrap">
            <span>Kantin</span>
            <select name="kantin" data-autosubmit>
                <option value="0">Semua kantin</option>
                <?php foreach ($kantins as $k): ?>
                    <option value="<?= (int) $k['id'] ?>" <?= $kantinId === (int) $k['id'] ? 'selected' : '' ?>>
                        <?= e($k['nama']) ?> <?= (int) $k['is_open'] === 1 ? '' : '(tutup)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="select-wrap">
            <span>Urutkan</span>
            <select name="sort" data-autosubmit>
                <option value="populer" <?= $sort === 'populer' ? 'selected' : '' ?>>Paling populer</option>
                <option value="termurah" <?= $sort === 'termurah' ? 'selected' : '' ?>>Harga termurah</option>
                <option value="termahal" <?= $sort === 'termahal' ? 'selected' : '' ?>>Harga termahal</option>
                <option value="nama" <?= $sort === 'nama' ? 'selected' : '' ?>>Nama A–Z</option>
            </select>
        </label>
        <noscript><button class="btn btn-ghost btn-sm" type="submit">Terapkan</button></noscript>
        <span class="filter-result"><?= count($menus) ?> menu ditemukan</span>
    </form>

    <?php if (!$menus): ?>
        <div class="empty-state">
            <span class="empty-emoji">🍽️</span>
            <h3>Menu tidak ditemukan</h3>
            <p>Coba kata kunci lain atau reset filter.</p>
            <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Lihat semua menu</a>
        </div>
    <?php else: ?>
        <div class="menu-grid">
            <?php foreach ($menus as $menu): ?>
                <?php
                $isClosed = (int) $menu['is_open'] === 1 ? false : true;
                $isSoldOut = (int) $menu['stok'] <= 0;
                $canOrder = $user && $user['role'] === 'siswa' && !$isClosed && !$isSoldOut;
                ?>
                <article class="menu-card <?= ($isClosed || $isSoldOut) ? 'is-disabled' : '' ?>">
                    <div class="menu-thumb">
                        <span class="menu-emoji"><?= e(menu_emoji($menu['emoji'])) ?></span>
                        <?php if ($isClosed): ?>
                            <span class="menu-flag">Tutup</span>
                        <?php elseif ($isSoldOut): ?>
                            <span class="menu-flag red">Habis</span>
                        <?php endif; ?>
                    </div>
                    <div class="menu-body">
                        <div class="menu-cat"><?= e($menu['ikon_kategori'] ?: '🍽️') ?> <?= e($menu['nama_kategori'] ?: 'Lainnya') ?></div>
                        <h3 class="menu-name"><?= e($menu['nama']) ?></h3>
                        <p class="menu-desc"><?= e($menu['deskripsi'] ?: 'Menu andalan kantin ini.') ?></p>
                        <div class="menu-foot">
                            <div>
                                <span class="price"><?= e(rupiah($menu['harga'])) ?></span>
                                <span class="stock">Stok <?= (int) $menu['stok'] ?></span>
                            </div>
                            <?php if ($canOrder): ?>
                                <form method="post" action="<?= e(url('keranjang.php')) ?>" class="add-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="menu_id" value="<?= (int) $menu['id'] ?>">
                                    <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? url('index.php')) ?>">
                                    <button class="btn btn-primary btn-sm" type="submit">+ Tambah</button>
                                </form>
                            <?php elseif (!$user): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? url('index.php')))) ?>">Masuk</a>
                            <?php else: ?>
                                <span class="btn btn-ghost btn-sm is-muted">Tidak tersedia</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($cartItems): ?>
    <div class="cart-float">
        <div class="cart-float-info">
            <span class="cart-float-icon">🛒</span>
            <div>
                <strong><?= cart_count() ?> item di keranjang</strong>
                <small><?= e(rupiah($cartTotal)) ?> · belum termasuk pesanan lain</small>
            </div>
        </div>
        <a class="btn btn-primary" href="<?= e(url('keranjang.php')) ?>">Lihat keranjang →</a>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
