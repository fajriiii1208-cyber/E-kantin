<?php
require __DIR__ . '/config.php';

if (current_user()) {
    redirect(dashboard_for(current_user()['role']));
}

$errors = [];
$nama = $email = $noHp = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $nama = post_str('nama');
    $email = strtolower(post_str('email'));
    $noHp = post_str('no_hp');
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');

    if (mb_strlen($nama) < 3) {
        $errors[] = 'Nama minimal 3 karakter.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }
    if ($password !== $password2) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }
    if ($noHp !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $noHp)) {
        $errors[] = 'Nomor HP tidak valid.';
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $check->execute([$email]);
        if ((int) $check->fetchColumn() > 0) {
            $errors[] = 'Email tersebut sudah terdaftar. Silakan masuk.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO users (nama, email, password, role, no_hp) VALUES (?, ?, ?, "siswa", ?)');
        $stmt->execute([$nama, $email, password_hash($password, PASSWORD_DEFAULT), $noHp !== '' ? $noHp : null]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $pdo->lastInsertId();
        flash('success', 'Akun berhasil dibuat. Selamat datang, ' . $nama . '!');
        redirect('index.php');
    }
}

$pageTitle = 'Daftar';
$active = 'register';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-layout single">
    <div class="auth-card wide">
        <a class="auth-brand" href="<?= e(url('index.php')) ?>">
            <span class="brand-mark">🍱</span>
            <span class="auth-brand-text">
                <strong>E-Kantin</strong>
                <small>Kantin kampus online</small>
            </span>
        </a>
        <span class="eyebrow">Gratis & cepat</span>
        <h1>Daftar sebagai siswa</h1>
        <p class="auth-sub">Buat akun untuk memesan makanan di seluruh kantin kampus.</p>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $item): ?>
                    <div>• <?= e($item) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="stack-form">
            <?= csrf_field() ?>
            <div class="form-row">
                <label class="field">
                    <span>Nama lengkap</span>
                    <input type="text" name="nama" value="<?= e($nama) ?>" placeholder="Nama kamu" autocomplete="name" required>
                </label>
                <label class="field">
                    <span>Nomor HP <small>(opsional)</small></span>
                    <input type="tel" name="no_hp" value="<?= e($noHp) ?>" placeholder="08xxxxxxxxxx" autocomplete="tel">
                </label>
            </div>
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" value="<?= e($email) ?>" placeholder="nama@email.com" autocomplete="email" required>
            </label>
            <div class="form-row">
                <label class="field">
                    <span>Password</span>
                    <div class="password-wrap">
                        <input type="password" name="password" placeholder="Minimal 6 karakter" autocomplete="new-password" required data-password>
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Tampilkan password">👁️</button>
                    </div>
                </label>
                <label class="field">
                    <span>Ulangi password</span>
                    <div class="password-wrap">
                        <input type="password" name="password2" placeholder="Ulangi password" autocomplete="new-password" required data-password>
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Tampilkan password">👁️</button>
                    </div>
                </label>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Buat akun</button>
        </form>

        <p class="auth-switch">Sudah punya akun? <a href="<?= e(url('login.php')) ?>">Masuk di sini</a></p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
