<?php
require __DIR__ . '/../config.php';

$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $nama = post_str('nama');
        $deskripsi = mb_substr(post_str('deskripsi'), 0, 255);
        $lokasi = mb_substr(post_str('lokasi'), 0, 150);
        $isOpen = isset($_POST['is_open']) ? 1 : 0;
        $adminNama = post_str('admin_nama');
        $adminEmail = strtolower(post_str('admin_email'));
        $adminPassword = (string) ($_POST['admin_password'] ?? '');

        $errors = [];
        if (mb_strlen($nama) < 3) {
            $errors[] = 'Nama kantin minimal 3 karakter.';
        }
        if ($id === 0 && $adminNama !== '') {
            if (mb_strlen($adminNama) < 3) {
                $errors[] = 'Nama pengelola minimal 3 karakter.';
            }
            if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email pengelola tidak valid.';
            }
            if (strlen($adminPassword) < 6) {
                $errors[] = 'Password pengelola minimal 6 karakter.';
            }
            if (!$errors) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
                $check->execute([$adminEmail]);
                if ((int) $check->fetchColumn() > 0) {
                    $errors[] = 'Email pengelola sudah terdaftar.';
                }
            }
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('admin/kantin.php' . ($id > 0 ? '?edit=' . $id : ''));
        }

        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE kantin SET nama = ?, deskripsi = ?, lokasi = ?, is_open = ? WHERE id = ?');
            $stmt->execute([$nama, $deskripsi !== '' ? $deskripsi : null, $lokasi !== '' ? $lokasi : null, $isOpen, $id]);
            flash('success', 'Kantin "' . $nama . '" berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO kantin (nama, deskripsi, lokasi, is_open) VALUES (?, ?, ?, ?)');
            $stmt->execute([$nama, $deskripsi !== '' ? $deskripsi : null, $lokasi !== '' ? $lokasi : null, $isOpen]);
            $newKantinId = (int) $pdo->lastInsertId();

            if ($adminNama !== '') {
                $stmt = $pdo->prepare('INSERT INTO users (nama, email, password, role, no_hp, kantin_id) VALUES (?, ?, ?, "kantin", NULL, ?)');
                $stmt->execute([$adminNama, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), $newKantinId]);
                flash('success', 'Kantin "' . $nama . '" dan akun pengelola berhasil dibuat.');
            } else {
                flash('success', 'Kantin "' . $nama . '" berhasil dibuat. Tambahkan akun pengelola di menu Pengguna.');
            }
        }
        redirect('admin/kantin.php');
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE kantin SET is_open = 1 - is_open WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', $stmt->rowCount() > 0 ? 'Status buka/tutup kantin diperbarui.' : 'Kantin tidak ditemukan.');
        redirect('admin/kantin.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM kantin WHERE id = ?');
        $stmt->execute([$id]);
        $kantin = $stmt->fetch();

        if (!$kantin) {
            flash('error', 'Kantin tidak ditemukan.');
        } else {
            $menuCount = $pdo->prepare('SELECT COUNT(*) FROM menu WHERE kantin_id = ?');
            $menuCount->execute([$id]);
            $orderCount = $pdo->prepare('SELECT COUNT(*) FROM pesanan WHERE kantin_id = ?');
            $orderCount->execute([$id]);

            if ((int) $menuCount->fetchColumn() > 0 || (int) $orderCount->fetchColumn() > 0) {
                flash('error', 'Kantin ' . $kantin['nama'] . ' masih punya menu atau riwayat pesanan sehingga tidak bisa dihapus. Nonaktifkan saja dengan menutup kantin.');
            } else {
                $pdo->prepare('DELETE FROM kantin WHERE id = ?')->execute([$id]);
                flash('success', 'Kantin ' . $kantin['nama'] . ' dihapus.');
            }
        }
        redirect('admin/kantin.php');
    }

    flash('error', 'Aksi tidak dikenal.');
    redirect('admin/kantin.php');
}

$editing = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM kantin WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
    if (!$editing) {
        flash('error', 'Kantin yang ingin diedit tidak ditemukan.');
        redirect('admin/kantin.php');
    }
}

$kantins = $pdo->query(
    'SELECT k.*,
            (SELECT COUNT(*) FROM menu m WHERE m.kantin_id = k.id) AS jumlah_menu,
            (SELECT COUNT(*) FROM pesanan p WHERE p.kantin_id = k.id) AS jumlah_pesanan,
            (SELECT COALESCE(SUM(p.total), 0) FROM pesanan p WHERE p.kantin_id = k.id AND p.status <> "dibatalkan") AS omzet,
            (SELECT GROUP_CONCAT(u.nama SEPARATOR ", ") FROM users u WHERE u.kantin_id = k.id AND u.role = "kantin") AS pengelola
     FROM kantin k
     ORDER BY k.nama'
)->fetchAll();

$pageTitle = 'Kelola kantin';
$active = 'kantin';
require __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <div class="page-head">
        <div>
            <span class="eyebrow">Manajemen kantin</span>
            <h1>Kelola kantin</h1>
            <p class="section-sub">Daftarkan kantin baru beserta akun pengelolanya.</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/index.php')) ?>">← Dashboard</a>
    </div>

    <div class="menu-admin-layout">
        <div class="panel">
            <div class="panel-head">
                <h2><?= $editing ? 'Edit kantin' : 'Tambah kantin baru' ?></h2>
                <p><?= $editing ? 'Perbarui informasi kantin.' : 'Opsional: sekaligus buatkan akun pengelola kantin.' ?></p>
            </div>

            <form method="post" class="stack-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                <?php endif; ?>

                <label class="field">
                    <span>Nama kantin</span>
                    <input type="text" name="nama" value="<?= e($editing['nama'] ?? '') ?>" placeholder="Contoh: Kantin Bu Sari" required>
                </label>
                <label class="field">
                    <span>Deskripsi</span>
                    <textarea name="deskripsi" rows="2" maxlength="255" placeholder="Menu andalan kantin"><?= e($editing['deskripsi'] ?? '') ?></textarea>
                </label>
                <label class="field">
                    <span>Lokasi</span>
                    <input type="text" name="lokasi" value="<?= e($editing['lokasi'] ?? '') ?>" placeholder="Gedung A · Lantai 1">
                </label>
                <label class="check-line">
                    <input type="checkbox" name="is_open" value="1" <?= !$editing || (int) $editing['is_open'] === 1 ? 'checked' : '' ?>>
                    <span>Kantin langsung dibuka dan menerima pesanan</span>
                </label>

                <?php if (!$editing): ?>
                    <div class="form-section">
                        <h3>Akun pengelola <span class="badge badge-gray">opsional</span></h3>
                        <p>Buat sekaligus akun untuk pengelola kantin ini.</p>
                    </div>
                    <label class="field">
                        <span>Nama pengelola</span>
                        <input type="text" name="admin_nama" placeholder="Nama penanggung jawab">
                    </label>
                    <div class="form-row">
                        <label class="field">
                            <span>Email pengelola</span>
                            <input type="email" name="admin_email" placeholder="pengelola@email.com">
                        </label>
                        <label class="field">
                            <span>Password</span>
                            <input type="text" name="admin_password" placeholder="Minimal 6 karakter">
                        </label>
                    </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit"><?= $editing ? 'Simpan perubahan' : 'Tambah kantin' ?></button>
                    <?php if ($editing): ?>
                        <a class="btn btn-ghost" href="<?= e(url('admin/kantin.php')) ?>">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Daftar kantin</h2>
                <p><?= count($kantins) ?> kantin terdaftar.</p>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Kantin</th><th>Pengelola</th><th>Menu</th><th>Pesanan</th><th>Omzet</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kantins as $kantin): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?= e($kantin['nama']) ?></strong>
                                        <small class="block muted"><?= e($kantin['lokasi'] ?: 'Lokasi belum diatur') ?></small>
                                    </div>
                                </td>
                                <td><?= e($kantin['pengelola'] ?: '—') ?></td>
                                <td><?= (int) $kantin['jumlah_menu'] ?></td>
                                <td><?= (int) $kantin['jumlah_pesanan'] ?></td>
                                <td class="nowrap"><?= e(rupiah($kantin['omzet'])) ?></td>
                                <td><span class="badge <?= (int) $kantin['is_open'] === 1 ? 'badge-green' : 'badge-red' ?>"><?= (int) $kantin['is_open'] === 1 ? 'Buka' : 'Tutup' ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <a class="link-more" href="<?= e(url('admin/kantin.php?edit=' . (int) $kantin['id'])) ?>">Edit</a>
                                        <form method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int) $kantin['id'] ?>">
                                            <button class="link-more" type="submit"><?= (int) $kantin['is_open'] === 1 ? 'Tutup' : 'Buka' ?></button>
                                        </form>
                                        <form method="post" data-confirm="Hapus kantin <?= e($kantin['nama']) ?>?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $kantin['id'] ?>">
                                            <button class="link-danger" type="submit">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
