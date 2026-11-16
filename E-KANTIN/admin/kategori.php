<?php
require __DIR__ . '/../config.php';

$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $nama = post_str('nama');
        $ikon = post_str('ikon', '🍽️');

        if (mb_strlen($nama) < 2) {
            flash('error', 'Nama kategori minimal 2 karakter.');
        } else {
            if ($ikon === '') {
                $ikon = '🍽️';
            }
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE kategori SET nama = ?, ikon = ? WHERE id = ?');
                $stmt->execute([$nama, $ikon, $id]);
                flash('success', 'Kategori "' . $nama . '" diperbarui.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO kategori (nama, ikon) VALUES (?, ?)');
                $stmt->execute([$nama, $ikon]);
                flash('success', 'Kategori "' . $nama . '" ditambahkan.');
            }
        }
        redirect('admin/kategori.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM kategori WHERE id = ?');
        $stmt->execute([$id]);
        $kategori = $stmt->fetch();

        if (!$kategori) {
            flash('error', 'Kategori tidak ditemukan.');
        } else {
            $pdo->prepare('DELETE FROM kategori WHERE id = ?')->execute([$id]);
            flash('success', 'Kategori "' . $kategori['nama'] . '" dihapus. Menu terkait menjadi tanpa kategori.');
        }
        redirect('admin/kategori.php');
    }

    flash('error', 'Aksi tidak dikenal.');
    redirect('admin/kategori.php');
}

$editing = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM kategori WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
    if (!$editing) {
        flash('error', 'Kategori yang ingin diedit tidak ditemukan.');
        redirect('admin/kategori.php');
    }
}

$kategoris = $pdo->query(
    'SELECT k.*, (SELECT COUNT(*) FROM menu m WHERE m.kategori_id = k.id) AS jumlah_menu
     FROM kategori k ORDER BY k.nama'
)->fetchAll();

$pageTitle = 'Kategori menu';
$active = 'kategori';
require __DIR__ . '/../includes/header.php';
?>

<section class="section narrow">
    <div class="page-head">
        <div>
            <span class="eyebrow">Struktur menu</span>
            <h1>Kategori menu</h1>
            <p class="section-sub">Kategori membantu siswa menemukan menu lebih cepat.</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/index.php')) ?>">← Dashboard</a>
    </div>

    <div class="two-col">
        <div class="panel">
            <div class="panel-head">
                <h2><?= $editing ? 'Edit kategori' : 'Tambah kategori' ?></h2>
                <p>Gunakan emoji agar tampilan lebih menarik.</p>
            </div>
            <form method="post" class="stack-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                <?php endif; ?>
                <div class="form-row emoji-row">
                    <label class="field emoji-field">
                        <span>Ikon</span>
                        <input type="text" name="ikon" value="<?= e($editing['ikon'] ?? '🍽️') ?>" maxlength="8">
                    </label>
                    <label class="field grow">
                        <span>Nama kategori</span>
                        <input type="text" name="nama" value="<?= e($editing['nama'] ?? '') ?>" placeholder="Contoh: Minuman" required>
                    </label>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit"><?= $editing ? 'Simpan perubahan' : 'Tambah kategori' ?></button>
                    <?php if ($editing): ?>
                        <a class="btn btn-ghost" href="<?= e(url('admin/kategori.php')) ?>">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Daftar kategori</h2>
                <p><?= count($kategoris) ?> kategori tersedia.</p>
            </div>
            <div class="category-list">
                <?php foreach ($kategoris as $kategori): ?>
                    <div class="category-item">
                        <span class="category-icon"><?= e($kategori['ikon']) ?></span>
                        <div class="category-info">
                            <strong><?= e($kategori['nama']) ?></strong>
                            <small><?= (int) $kategori['jumlah_menu'] ?> menu</small>
                        </div>
                        <div class="table-actions">
                            <a class="link-more" href="<?= e(url('admin/kategori.php?edit=' . (int) $kategori['id'])) ?>">Edit</a>
                            <form method="post" data-confirm="Hapus kategori <?= e($kategori['nama']) ?>? Menu terkait akan menjadi tanpa kategori.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $kategori['id'] ?>">
                                <button class="link-danger" type="submit">Hapus</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
