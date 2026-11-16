<?php
require __DIR__ . '/config.php';

/* ------------------------------------------------------------------
   Aksi keranjang (POST)
   ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'add') {
        $user = require_role('siswa');
        $menuId = (int) ($_POST['menu_id'] ?? 0);
        $back = safe_next(post_str('back'), url('index.php'));

        $stmt = $pdo->prepare(
            'SELECT m.*, k.is_open FROM menu m JOIN kantin k ON k.id = m.kantin_id WHERE m.id = ? AND m.is_active = 1'
        );
        $stmt->execute([$menuId]);
        $menu = $stmt->fetch();

        if (!$menu) {
            flash('error', 'Menu tidak ditemukan atau sudah tidak dijual.');
        } elseif ((int) $menu['is_open'] !== 1) {
            flash('error', 'Kantin sedang tutup. Coba lagi nanti.');
        } elseif ((int) $menu['stok'] <= 0) {
            flash('error', 'Stok menu ini sudah habis.');
        } else {
            $current = (int) (cart()[$menuId] ?? 0);
            $newQty = min($current + 1, (int) $menu['stok']);
            cart_set($menuId, $newQty);
            flash('success', $menu['nama'] . ' ditambahkan ke keranjang.');
        }
        redirect_raw($back);
    }

    if ($action === 'update') {
        $user = require_role('siswa');
        $menuId = (int) ($_POST['menu_id'] ?? 0);
        $delta = (int) ($_POST['delta'] ?? 0);
        $qty = (int) (cart()[$menuId] ?? 0) + $delta;

        $stmt = $pdo->prepare('SELECT nama, stok FROM menu WHERE id = ?');
        $stmt->execute([$menuId]);
        $menu = $stmt->fetch();

        if (!$menu) {
            cart_set($menuId, 0);
            flash('error', 'Menu tidak tersedia lagi dan dihapus dari keranjang.');
        } elseif ($qty <= 0) {
            cart_set($menuId, 0);
            flash('info', $menu['nama'] . ' dihapus dari keranjang.');
        } elseif ($qty > (int) $menu['stok']) {
            cart_set($menuId, (int) $menu['stok']);
            flash('error', 'Stok ' . $menu['nama'] . ' tersisa ' . (int) $menu['stok'] . '.');
        } else {
            cart_set($menuId, $qty);
        }
        redirect('keranjang.php');
    }

    if ($action === 'remove') {
        $user = require_role('siswa');
        cart_set((int) ($_POST['menu_id'] ?? 0), 0);
        flash('info', 'Item dihapus dari keranjang.');
        redirect('keranjang.php');
    }

    if ($action === 'clear') {
        $user = require_role('siswa');
        cart_clear();
        flash('info', 'Keranjang dikosongkan.');
        redirect('keranjang.php');
    }

    if ($action === 'checkout') {
        $user = require_role('siswa');
        $items = cart_items($pdo);

        if (!$items) {
            flash('error', 'Keranjang masih kosong.');
            redirect('keranjang.php');
        }

        $metode = post_str('metode', 'tunai');
        if (!in_array($metode, ['tunai', 'qris'], true)) {
            $metode = 'tunai';
        }
        $catatan = mb_substr(post_str('catatan'), 0, 255);

        foreach ($items as $item) {
            if ((int) $item['is_active'] !== 1) {
                flash('error', $item['nama'] . ' sudah tidak dijual. Hapus dari keranjang terlebih dahulu.');
                redirect('keranjang.php');
            }
            if ((int) $item['is_open'] !== 1) {
                flash('error', 'Kantin ' . $item['nama_kantin'] . ' sedang tutup. Hapus menu kantin tersebut atau coba lagi nanti.');
                redirect('keranjang.php');
            }
            if ((int) $item['qty'] > (int) $item['stok']) {
                flash('error', 'Stok ' . $item['nama'] . ' tersisa ' . (int) $item['stok'] . '. Sesuaikan jumlahnya dulu.');
                redirect('keranjang.php');
            }
        }

        $groups = group_cart_by_kantin($items);
        $kodeList = [];

        try {
            $pdo->beginTransaction();

            foreach ($groups as $group) {
                $kode = kode_pesanan();
                while (true) {
                    $check = $pdo->prepare('SELECT COUNT(*) FROM pesanan WHERE kode = ?');
                    $check->execute([$kode]);
                    if ((int) $check->fetchColumn() === 0) {
                        break;
                    }
                    $kode = kode_pesanan();
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO pesanan (kode, user_id, kantin_id, total, metode, status, status_bayar, catatan)
                     VALUES (?, ?, ?, ?, ?, "menunggu", "belum", ?)'
                );
                $stmt->execute([
                    $kode,
                    $user['id'],
                    $group['kantin_id'],
                    $group['total'],
                    $metode,
                    $catatan !== '' ? $catatan : null,
                ]);
                $orderId = (int) $pdo->lastInsertId();

                $detailStmt = $pdo->prepare(
                    'INSERT INTO pesanan_detail (pesanan_id, menu_id, nama_menu, harga, qty, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stockStmt = $pdo->prepare('UPDATE menu SET stok = stok - ? WHERE id = ? AND stok >= ?');

                foreach ($group['items'] as $item) {
                    $stockStmt->execute([$item['qty'], $item['id'], $item['qty']]);
                    if ($stockStmt->rowCount() === 0) {
                        throw new RuntimeException('Stok ' . $item['nama'] . ' baru saja habis. Silakan periksa keranjang.');
                    }

                    $detailStmt->execute([
                        $orderId,
                        $item['id'],
                        $item['nama'],
                        $item['harga'],
                        $item['qty'],
                        $item['subtotal'],
                    ]);
                }

                $kodeList[] = $kode;
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Pesanan gagal dibuat. Silakan coba lagi.');
            redirect('keranjang.php');
        }

        cart_clear();
        flash('success', 'Pesanan berhasil dibuat (' . implode(', ', $kodeList) . '). Tunjukkan kode pesanan saat mengambil.');
        redirect('pesanan.php');
    }

    flash('error', 'Aksi tidak dikenal.');
    redirect('keranjang.php');
}

/* ------------------------------------------------------------------
   Tampilan keranjang
   ------------------------------------------------------------------ */
require_role('siswa');

$items = cart_items($pdo);
$groups = group_cart_by_kantin($items);
$grandTotal = array_sum(array_column($items, 'subtotal'));
$hasProblem = false;
foreach ($items as $item) {
    if ((int) $item['is_active'] !== 1 || (int) $item['is_open'] !== 1 || (int) $item['qty'] > (int) $item['stok']) {
        $hasProblem = true;
    }
}

$pageTitle = 'Keranjang';
$active = 'keranjang';
require __DIR__ . '/includes/header.php';
?>

<section class="section">
    <div class="page-head">
        <div>
            <span class="eyebrow">Langkah terakhir</span>
            <h1>Keranjang pesanan</h1>
            <p class="section-sub">Periksa pesananmu, pilih metode pembayaran, lalu checkout.</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('index.php')) ?>">← Tambah menu lagi</a>
    </div>

    <?php if (!$items): ?>
        <div class="empty-state">
            <span class="empty-emoji">🛒</span>
            <h3>Keranjang masih kosong</h3>
            <p>Yuk pilih makanan atau minuman favoritmu dari kantin kampus.</p>
            <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Jelajahi menu</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <div class="cart-main">
                <?php foreach ($groups as $group): ?>
                    <div class="cart-group">
                        <div class="cart-group-head">
                            <div>
                                <h2>🏪 <?= e($group['nama_kantin']) ?></h2>
                                <p>Pesanan ini diproses terpisah oleh kantin.</p>
                            </div>
                            <?php if ($group['is_open'] !== 1): ?>
                                <span class="badge badge-red">Kantin tutup</span>
                            <?php else: ?>
                                <span class="badge badge-green">Buka</span>
                            <?php endif; ?>
                        </div>

                        <?php foreach ($group['items'] as $item): ?>
                            <div class="cart-row <?= ((int) $item['is_active'] !== 1 || (int) $item['stok'] <= 0) ? 'row-problem' : '' ?>">
                                <span class="cart-emoji"><?= e(menu_emoji($item['emoji'])) ?></span>
                                <div class="cart-item-info">
                                    <strong><?= e($item['nama']) ?></strong>
                                    <small><?= e(rupiah($item['harga'])) ?> / porsi · stok <?= (int) $item['stok'] ?></small>
                                    <?php if ((int) $item['is_active'] !== 1): ?>
                                        <span class="text-danger">Menu sudah tidak dijual</span>
                                    <?php elseif ((int) $item['stok'] <= 0): ?>
                                        <span class="text-danger">Stok habis</span>
                                    <?php elseif ((int) $item['qty'] > (int) $item['stok']): ?>
                                        <span class="text-danger">Jumlah melebihi stok</span>
                                    <?php endif; ?>
                                </div>

                                <div class="qty-control">
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="menu_id" value="<?= (int) $item['id'] ?>">
                                        <input type="hidden" name="delta" value="-1">
                                        <button class="qty-btn" type="submit" aria-label="Kurangi">−</button>
                                    </form>
                                    <span class="qty-value"><?= (int) $item['qty'] ?></span>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="menu_id" value="<?= (int) $item['id'] ?>">
                                        <input type="hidden" name="delta" value="1">
                                        <button class="qty-btn" type="submit" aria-label="Tambah">+</button>
                                    </form>
                                </div>

                                <div class="cart-item-side">
                                    <strong><?= e(rupiah($item['subtotal'])) ?></strong>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="menu_id" value="<?= (int) $item['id'] ?>">
                                        <button class="link-danger" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="cart-group-total">
                            <span>Subtotal <?= e($group['nama_kantin']) ?></span>
                            <strong><?= e(rupiah($group['total'])) ?></strong>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="cart-group">
                    <div class="cart-group-head">
                        <div>
                            <h2>📝 Catatan pesanan</h2>
                            <p>Opsional — sampaikan permintaan khusus ke kantin.</p>
                        </div>
                    </div>
                    <form method="post" id="checkoutForm" class="checkout-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="checkout">
                        <label class="field">
                            <span>Catatan untuk kantin</span>
                            <textarea name="catatan" rows="3" maxlength="255" placeholder="Contoh: sambal dipisah, tanpa bawang, tambah nasi…"></textarea>
                        </label>

                        <div class="field">
                            <span>Metode pembayaran</span>
                            <div class="pay-options">
                                <label class="pay-option">
                                    <input type="radio" name="metode" value="tunai" checked>
                                    <span class="pay-body">
                                        <span class="pay-icon">💵</span>
                                        <span><strong>Tunai</strong><small>Bayar di kantin saat mengambil</small></span>
                                    </span>
                                </label>
                                <label class="pay-option">
                                    <input type="radio" name="metode" value="qris">
                                    <span class="pay-body">
                                        <span class="pay-icon">📱</span>
                                        <span><strong>QRIS</strong><small>Tunjukkan bukti bayar ke kantin</small></span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="cart-actions">
                            <button class="btn btn-primary btn-lg" type="submit" <?= $hasProblem ? 'disabled' : '' ?>>
                                Checkout sekarang · <?= e(rupiah($grandTotal)) ?>
                            </button>
                            <button class="btn btn-ghost" type="submit" form="clearCart">Kosongkan keranjang</button>
                        </div>
                        <?php if ($hasProblem): ?>
                            <p class="text-danger">Ada item yang tidak tersedia. Hapus atau sesuaikan dulu sebelum checkout.</p>
                        <?php endif; ?>
                    </form>
                    <form method="post" id="clearCart">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="clear">
                    </form>
                </div>
            </div>

            <aside class="cart-summary">
                <div class="panel sticky">
                    <h2>Ringkasan pesanan</h2>
                    <div class="summary-row">
                        <span>Jumlah item</span>
                        <strong><?= cart_count() ?> item</strong>
                    </div>
                    <?php foreach ($groups as $group): ?>
                        <div class="summary-row muted">
                            <span><?= e($group['nama_kantin']) ?></span>
                            <span><?= e(rupiah($group['total'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="summary-total">
                        <span>Total bayar</span>
                        <strong><?= e(rupiah($grandTotal)) ?></strong>
                    </div>
                    <p class="summary-note">Pesanan dari kantin berbeda akan dibuat sebagai pesanan terpisah.</p>
                    <div class="info-box small">⏱️ Rata-rata pesanan siap dalam 10–20 menit setelah dikonfirmasi kantin.</div>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
