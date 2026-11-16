<?php
require __DIR__ . '/config.php';

if (current_user()) {
    redirect(dashboard_for(current_user()['role']));
}

$next = (string) ($_GET['next'] ?? '');
$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(post_str('email'));
    $password = (string) ($_POST['password'] ?? '');
    $next = post_str('next');

    if ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        if ($account && password_verify($password, $account['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['id'];
            flash('success', 'Selamat datang, ' . $account['nama'] . '!');
            if ($next !== '') {
                redirect_raw(safe_next($next, url(dashboard_for($account['role']))));
            }
            redirect(dashboard_for($account['role']));
        }
        $error = 'Email atau password salah. Silakan coba lagi.';
    }
}

$pageTitle = 'Masuk';
$active = 'login';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-layout">
    <div class="auth-card">
        <a class="auth-brand" href="<?= e(url('index.php')) ?>">
            <span class="brand-mark">🍱</span>
            <span class="auth-brand-text">
                <strong>E-Kantin</strong>
                <small>Kantin kampus online</small>
            </span>
        </a>
        <span class="eyebrow">Selamat datang kembali</span>
        <h1>Masuk ke E-Kantin</h1>
        <p class="auth-sub">Pesan makanan, kelola kantin, atau pantau seluruh transaksi dari satu tempat.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="stack-form">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" value="<?= e($email) ?>" placeholder="nama@email.com" autocomplete="email" required>
            </label>
            <label class="field">
                <span>Password</span>
                <div class="password-wrap">
                    <input type="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required data-password>
                    <button type="button" class="password-toggle" data-password-toggle aria-label="Tampilkan password">👁️</button>
                </div>
            </label>
            <button class="btn btn-primary btn-block" type="submit">Masuk</button>
        </form>

        <p class="auth-switch">Belum punya akun? <a href="<?= e(url('register.php')) ?>">Daftar sebagai siswa</a></p>
    </div>

    <aside class="demo-card">
        <h2>Akun demo</h2>
        <p>Gunakan salah satu akun berikut untuk mencoba setiap peran.</p>

        <?php
        $demos = [
            ['role' => 'Siswa', 'email' => 'siswa@ekantin.test', 'password' => 'siswa123', 'icon' => '🎓', 'desc' => 'Pesan menu & lacak pesanan'],
            ['role' => 'Pengelola Kantin', 'email' => 'sari@ekantin.test', 'password' => 'kantin123', 'icon' => '👩‍🍳', 'desc' => 'Kelola menu & pesanan kantin'],
            ['role' => 'Admin', 'email' => 'admin@ekantin.test', 'password' => 'admin123', 'icon' => '🛡️', 'desc' => 'Pantau pengguna & laporan'],
        ];
        ?>
        <div class="demo-list">
            <?php foreach ($demos as $demo): ?>
                <button type="button" class="demo-item" data-demo-email="<?= e($demo['email']) ?>" data-demo-password="<?= e($demo['password']) ?>">
                    <span class="demo-icon"><?= $demo['icon'] ?></span>
                    <span class="demo-text">
                        <strong><?= e($demo['role']) ?></strong>
                        <small><?= e($demo['desc']) ?></small>
                        <code><?= e($demo['email']) ?> · <?= e($demo['password']) ?></code>
                    </span>
                    <span class="demo-fill">Isi</span>
                </button>
            <?php endforeach; ?>
        </div>
        <p class="demo-hint">Klik salah satu akun untuk mengisi formulir otomatis.</p>
    </aside>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
