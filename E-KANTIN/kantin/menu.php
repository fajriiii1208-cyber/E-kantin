<?php
require __DIR__ . '/../config.php';

$user = require_role('kantin');
if (empty($user['kantin_id'])) {
    flash('error', 'Akunmu belum terhubung ke kantin. Hubungi admin untuk menautkannya.');
    redirect('akun.php');
}
$kantinId = (int) $user['kantin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $nama = post_str('nama');
        $kategoriId = (int) ($_POST['kategori_id'] ?? 0);
        $harga = (int) ($_POST['harga'] ?? 0);
        $stok = (int) ($_POST['stok'] ?? 0);
        $emoji = post_str('emoji', '🍽️');
        $deskripsi = mb_substr(post_str('deskripsi'), 0, 255);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $errors = [];
        if (mb_strlen($nama) < 2) {
            $errors[] = 'Nama menu minimal 2 karakter.';
        }
        if ($harga <= 0) {
            $errors[] = 'Harga harus lebih dari 0.';
        }
        if ($stok < 0) {
            $errors[] = 'Stok tidak boleh negatif.';
        }
        if ($emoji === '') {
            $emoji = '🍽️';
        }
        if ($kategoriId > 0) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM kategori WHERE id = ?');
            $check->execute([$kategoriId]);
            if ((int) $check->fetchColumn() === 0) {
                $kategoriId = 0;
            }
        }

        if ($id > 0) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM menu WHERE id = ? AND kantin_id = ?');
            $check->execute([$id, $kantinId]);
            if ((int) $check->fetchColumn() === 0) {
                flash('error', 'Menu tidak ditemukan di kantinmu.');
                redirect('kantin/menu.php');
            }
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('kantin/menu.php' . ($id > 0 ? '?edit=' . $id : ''));
        }

        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE menu SET kategori_id = ?, nama = ?, deskripsi = ?, harga = ?, stok = ?, emoji = ?, is_active = ?
                 WHERE id = ? AND kantin_id = ?'
            );
            $stmt->execute([
                $kategoriId > 0 ? $kategoriId : null,
                $nama,
                $deskripsi !== '' ? $deskripsi : null,
                $harga,
                $stok,
                $emoji,
                $isActive,
                $id,
                $kantinId,
            ]);
            flash('success', 'Menu "' . $nama . '" berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO menu (kantin_id, kategori_id, nama, deskripsi, harga, stok, emoji, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $kantinId,
                $kategoriId > 0 ? $kategoriId : null,
                $nama,
                $deskripsi !== '' ? $deskripsi : null,
                $harga,
                $stok,
                $emoji,
                $isActive,
            ]);
            flash('success', 'Menu "' . $nama . '" berhasil ditambahkan.');
        }
        redirect('kantin/menu.php');
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE menu SET is_active = 1 - is_active WHERE id = ? AND kantin_id = ?');
        $stmt->execute([$id, $kantinId]);
        if ($stmt->rowCount() > 0) {
            flash('success', 'Status menu diperbarui.');
        } else {
            flash('error', 'Menu tidak ditemukan.');
        }
        redirect('kantin/menu.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM menu WHERE id = ? AND kantin_id = ?');
        $stmt->execute([$id, $kantinId]);
        if ($stmt->rowCount() > 0) {
            flash('success', 'Menu dihapus. Riwayat pesanan lama tetap tersimpan.');
        } else {
            flash('error', 'Menu tidak ditemukan.');
        }
        redirect('kantin/menu.php');
    }

    flash('error', 'Aksi tidak dikenal.');
    redirect('kantin/menu.php');
}

$kategoris = $pdo->query('SELECT * FROM kategori ORDER BY nama')->fetchAll();

$editing = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM menu WHERE id = ? AND kantin_id = ?');
    $stmt->execute([$editId, $kantinId]);
    $editing = $stmt->fetch() ?: null;
    if (!$editing) {
        flash('error', 'Menu yang ingin diedit tidak ditemukan.');
        redirect('kantin/menu.php');
    }
}

$stmt = $pdo->prepare(
    'SELECT m.*, kat.nama AS nama_kategori, kat.ikon AS ikon_kategori,
            (SELECT COALESCE(SUM(d.qty), 0) FROM pesanan_detail d
             JOIN pesanan p ON p.id = d.pesanan_id
             WHERE d.menu_id = m.id AND p.status <> "dibatalkan") AS terjual
     FROM menu m
     LEFT JOIN kategori kat ON kat.id = m.kategori_id
     WHERE m.kantin_id = ?
     ORDER BY m.is_active DESC, m.nama ASC'
);
$stmt->execute([$kantinId]);
$menus = $stmt->fetchAll();

$pageTitle = 'Kelola menu';
$active = 'menu';
require __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <div class="page-head">
        <div>
            <span class="eyebrow">Katalog kantin</span>
            <h1>Kelola menu</h1>
            <p class="section-sub">Tambah, ubah harga, atur stok, atau nonaktifkan menu.</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('kantin/stok.php')) ?>">📦 Stok & ketersediaan</a>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('kantin/index.php')) ?>">← Dashboard</a>
        </div>
    </div>

    <div class="menu-admin-layout">
        <div class="panel">
            <div class="panel-head">
                <h2><?= $editing ? 'Edit menu' : 'Tambah menu baru' ?></h2>
                <p><?= $editing ? 'Perbarui detail menu lalu simpan.' : 'Menu baru langsung tampil di halaman siswa jika aktif.' ?></p>
            </div>

            <form method="post" class="stack-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                <?php endif; ?>

                <div class="form-row emoji-row">
                    <label class="field emoji-field">
                        <span>Emoji</span>
                        <input type="text" name="emoji" value="<?= e($editing['emoji'] ?? '🍽️') ?>" maxlength="8" placeholder="🍽️">
                    </label>
                    <label class="field grow">
                        <span>Nama menu</span>
                        <input type="text" name="nama" value="<?= e($editing['nama'] ?? '') ?>" placeholder="Contoh: Nasi Ayam Geprek" required>
                    </label>
                </div>

                <label class="field">
                    <span>Deskripsi</span>
                    <textarea name="deskripsi" rows="2" maxlength="255" placeholder="Deskripsi singkat menu"><?= e($editing['deskripsi'] ?? '') ?></textarea>
                </label>

                <div class="form-row">
                    <label class="field">
                        <span>Kategori</span>
                        <select name="kategori_id">
                            <option value="0">Tanpa kategori</option>
                            <?php foreach ($kategoris as $kat): ?>
                                <option value="<?= (int) $kat['id'] ?>" <?= (int) ($editing['kategori_id'] ?? 0) === (int) $kat['id'] ? 'selected' : '' ?>>
                                    <?= e($kat['ikon'] . ' ' . $kat['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span>Harga (Rp)</span>
                        <input type="number" name="harga" min="500" step="500" value="<?= e((string) ($editing['harga'] ?? '')) ?>" placeholder="15000" required>
                    </label>
                    <label class="field">
                        <span>Stok</span>
                        <input type="number" name="stok" min="0" step="1" value="<?= e((string) ($editing['stok'] ?? '0')) ?>" required>
                    </label>
                </div>

                <label class="check-line">
                    <input type="checkbox" name="is_active" value="1" <?= !$editing || (int) $editing['is_active'] === 1 ? 'checked' : '' ?>>
                    <span>Tampilkan menu di halaman siswa</span>
                </label>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit"><?= $editing ? 'Simpan perubahan' : 'Tambah menu' ?></button>
                    <?php if ($editing): ?>
                        <a class="btn btn-ghost" href="<?= e(url('kantin/menu.php')) ?>">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head row-between">
                <div>
                    <h2>Daftar menu</h2>
                    <p><?= count($menus) ?> menu terdaftar.</p>
                </div>
            </div>

            <?php if (!$menus): ?>
                <div class="empty-inline">
                    <span>🍽️</span>
                    <p>Belum ada menu. Tambahkan menu pertamamu di form sebelah.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Menu</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Terjual</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($menus as $menu): ?>
                                <tr class="<?= (int) $menu['is_active'] !== 1 ? 'row-muted' : '' ?>">
                                    <td>
                                        <div class="table-menu">
                                            <span class="table-emoji"><?= e(menu_emoji($menu['emoji'])) ?></span>
                                            <div>
                                                <strong><?= e($menu['nama']) ?></strong>
                                                <small><?= e($menu['deskripsi'] ?: '—') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= e($menu['ikon_kategori'] ?: '🍽️') ?> <?= e($menu['nama_kategori'] ?: 'Lainnya') ?></td>
                                    <td class="nowrap"><?= e(rupiah($menu['harga'])) ?></td>
                                    <td><?= (int) $menu['stok'] ?></td>
                                    <td><?= (int) $menu['terjual'] ?></td>
                                    <td><span class="badge <?= (int) $menu['is_active'] === 1 ? 'badge-green' : 'badge-gray' ?>"><?= (int) $menu['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
                                    <td>
                                        <div class="table-actions">
                                            <a class="link-more" href="<?= e(url('kantin/menu.php?edit=' . (int) $menu['id'])) ?>">Edit</a>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= (int) $menu['id'] ?>">
                                                <button class="link-more" type="submit"><?= (int) $menu['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                            </form>
                                            <form method="post" data-confirm="Hapus menu <?= e($menu['nama']) ?>? Tindakan ini tidak bisa dibatalkan.">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $menu['id'] ?>">
                                                <button class="link-danger" type="submit">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
