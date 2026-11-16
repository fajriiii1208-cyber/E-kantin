<?php
require __DIR__ . '/../config.php';

$user = require_role('kantin');
if (empty($user['kantin_id'])) {
    flash('error', 'Akunmu belum terhubung ke kantin. Hubungi admin untuk menautkannya.');
    redirect('akun.php');
}
$kantinId = (int) $user['kantin_id'];

$stmt = $pdo->prepare('SELECT * FROM kantin WHERE id = ?');
$stmt->execute([$kantinId]);
$kantin = $stmt->fetch();
if (!$kantin) {
    flash('error', 'Data kantin tidak ditemukan.');
    redirect('akun.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (post_str('action') === 'save') {
        $nama = post_str('nama');
        $deskripsi = mb_substr(post_str('deskripsi'), 0, 255);
        $lokasi = mb_substr(post_str('lokasi'), 0, 150);
        $isOpen = isset($_POST['is_open']) ? 1 : 0;

        if (mb_strlen($nama) < 3) {
            flash('error', 'Nama kantin minimal 3 karakter.');
        } else {
            $update = $pdo->prepare('UPDATE kantin SET nama = ?, deskripsi = ?, lokasi = ?, is_open = ? WHERE id = ?');
            $update->execute([
                $nama,
                $deskripsi !== '' ? $deskripsi : null,
                $lokasi !== '' ? $lokasi : null,
                $isOpen,
                $kantinId,
            ]);
            flash('success', 'Profil kantin berhasil diperbarui.');
        }
    }
    redirect('kantin/profil.php');
}

$menuCount = $pdo->prepare('SELECT COUNT(*) FROM menu WHERE kantin_id = ?');
$menuCount->execute([$kantinId]);
$totalMenu = (int) $menuCount->fetchColumn();

$orderCount = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE kantin_id = ? AND status <> 'dibatalkan'");
$orderCount->execute([$kantinId]);
$totalOrder = (int) $orderCount->fetchColumn();

$pageTitle = 'Profil kantin';
$active = 'profil';
require __DIR__ . '/../includes/header.php';
?>

<section class="section narrow">
    <div class="page-head">
        <div>
            <span class="eyebrow">Pengaturan kantin</span>
            <h1>Profil kantin</h1>
            <p class="section-sub">Informasi ini terlihat oleh siswa saat memilih menu.</p>
        </div>
        <span class="badge <?= (int) $kantin['is_open'] === 1 ? 'badge-green' : 'badge-red' ?> badge-lg">
            <?= (int) $kantin['is_open'] === 1 ? '🟢 Sedang buka' : '🔴 Sedang tutup' ?>
        </span>
    </div>

    <div class="two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Informasi kantin</h2>
                <p>Pastikan nama dan lokasi mudah dikenali siswa.</p>
            </div>
            <form method="post" class="stack-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <label class="field">
                    <span>Nama kantin</span>
                    <input type="text" name="nama" value="<?= e($kantin['nama']) ?>" required>
                </label>
                <label class="field">
                    <span>Deskripsi</span>
                    <textarea name="deskripsi" rows="3" maxlength="255" placeholder="Ceritakan menu andalan kantinmu"><?= e($kantin['deskripsi'] ?? '') ?></textarea>
                </label>
                <label class="field">
                    <span>Lokasi</span>
                    <input type="text" name="lokasi" value="<?= e($kantin['lokasi'] ?? '') ?>" placeholder="Gedung A · Lantai 1">
                </label>
                <label class="check-line">
                    <input type="checkbox" name="is_open" value="1" <?= (int) $kantin['is_open'] === 1 ? 'checked' : '' ?>>
                    <span>Kantin sedang buka dan menerima pesanan</span>
                </label>
                <button class="btn btn-primary" type="submit">Simpan profil</button>
            </form>
        </div>

        <div class="stack">
            <div class="panel">
                <div class="panel-head">
                    <h2>Ringkasan</h2>
                </div>
                <div class="summary-row"><span>Menu terdaftar</span><strong><?= $totalMenu ?></strong></div>
                <div class="summary-row"><span>Total pesanan</span><strong><?= $totalOrder ?></strong></div>
                <div class="summary-row"><span>Sejak</span><span><?= e(tanggal_id($kantin['created_at'])) ?></span></div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h2>Akun pengelola</h2>
                    <p>Akun yang dipakai untuk masuk ke dashboard.</p>
                </div>
                <div class="info-box">
                    👤 <strong><?= e($user['nama']) ?></strong><br>
                    <small><?= e($user['email']) ?></small>
                </div>
                <a class="btn btn-ghost btn-block" href="<?= e(url('akun.php')) ?>">Ubah data akun →</a>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
