<?php
require __DIR__ . '/config.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'profil') {
        $nama = post_str('nama');
        $email = strtolower(post_str('email'));
        $noHp = post_str('no_hp');

        $errors = [];
        if (mb_strlen($nama) < 3) {
            $errors[] = 'Nama minimal 3 karakter.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }
        if ($noHp !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $noHp)) {
            $errors[] = 'Nomor HP tidak valid.';
        }
        if (!$errors) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
            $check->execute([$email, $user['id']]);
            if ((int) $check->fetchColumn() > 0) {
                $errors[] = 'Email sudah dipakai akun lain.';
            }
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            $stmt = $pdo->prepare('UPDATE users SET nama = ?, email = ?, no_hp = ? WHERE id = ?');
            $stmt->execute([$nama, $email, $noHp !== '' ? $noHp : null, $user['id']]);
            flash('success', 'Profil berhasil diperbarui.');
        }
        redirect('akun.php');
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (!password_verify($current, $user['password'])) {
            flash('error', 'Password saat ini tidak sesuai.');
        } elseif (strlen($new) < 6) {
            flash('error', 'Password baru minimal 6 karakter.');
        } elseif ($new !== $confirm) {
            flash('error', 'Konfirmasi password baru tidak cocok.');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash('success', 'Password berhasil diganti.');
        }
        redirect('akun.php');
    }
}

$pageTitle = 'Akun saya';
$active = 'akun';
require __DIR__ . '/includes/header.php';
?>

<section class="section narrow">
    <div class="page-head">
        <div>
            <span class="eyebrow">Pengaturan akun</span>
            <h1>Akun saya</h1>
            <p class="section-sub">Kelola data profil dan keamanan akunmu.</p>
        </div>
        <span class="badge <?= e(role_badge_class($user['role'])) ?>"><?= e(role_label($user['role'])) ?></span>
    </div>

    <div class="two-col">
        <div class="panel">
            <div class="panel-head">
                <h2>Data profil</h2>
                <p>Informasi ini dipakai saat memesan.</p>
            </div>
            <form method="post" class="stack-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profil">
                <label class="field">
                    <span>Nama lengkap</span>
                    <input type="text" name="nama" value="<?= e($user['nama']) ?>" required>
                </label>
                <label class="field">
                    <span>Email</span>
                    <input type="email" name="email" value="<?= e($user['email']) ?>" required>
                </label>
                <label class="field">
                    <span>Nomor HP</span>
                    <input type="tel" name="no_hp" value="<?= e($user['no_hp'] ?? '') ?>" placeholder="08xxxxxxxxxx">
                </label>
                <?php if ($user['role'] === 'kantin' && $user['nama_kantin']): ?>
                    <div class="info-box">🏪 Terhubung dengan <strong><?= e($user['nama_kantin']) ?></strong></div>
                <?php endif; ?>
                <button class="btn btn-primary" type="submit">Simpan perubahan</button>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Ganti password</h2>
                <p>Gunakan password yang kuat dan mudah kamu ingat.</p>
            </div>
            <form method="post" class="stack-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <label class="field">
                    <span>Password saat ini</span>
                    <input type="password" name="current_password" autocomplete="current-password" required>
                </label>
                <label class="field">
                    <span>Password baru</span>
                    <input type="password" name="new_password" autocomplete="new-password" required>
                </label>
                <label class="field">
                    <span>Ulangi password baru</span>
                    <input type="password" name="confirm_password" autocomplete="new-password" required>
                </label>
                <button class="btn btn-ghost" type="submit">Perbarui password</button>
            </form>
        </div>
    </div>

    <div class="panel muted-panel">
        <div>
            <strong>Bergabung sejak</strong>
            <p><?= e(tanggal_id($user['created_at'])) ?></p>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('logout.php')) ?>">Keluar dari akun</a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
