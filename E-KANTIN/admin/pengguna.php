<?php
require __DIR__ . '/../config.php';

$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post_str('action');

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $nama = post_str('nama');
        $email = strtolower(post_str('email'));
        $role = post_str('role', 'siswa');
        $noHp = post_str('no_hp');
        $kantinId = (int) ($_POST['kantin_id'] ?? 0);
        $password = (string) ($_POST['password'] ?? '');

        $errors = [];
        if (!in_array($role, ['admin', 'kantin', 'siswa'], true)) {
            $errors[] = 'Peran tidak valid.';
        }
        if (mb_strlen($nama) < 3) {
            $errors[] = 'Nama minimal 3 karakter.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }
        if ($role === 'kantin') {
            $check = $pdo->prepare('SELECT COUNT(*) FROM kantin WHERE id = ?');
            $check->execute([$kantinId]);
            if ($kantinId <= 0 || (int) $check->fetchColumn() === 0) {
                $errors[] = 'Pilih kantin untuk akun pengelola.';
            }
        } else {
            $kantinId = 0;
        }
        if ($id === (int) $user['id'] && $role !== 'admin') {
            $errors[] = 'Kamu tidak bisa mengubah peran akunmu sendiri dari admin.';
        }
        if ($id === 0 && strlen($password) < 6) {
            $errors[] = 'Password akun baru minimal 6 karakter.';
        }
        if ($id > 0 && $password !== '' && strlen($password) < 6) {
            $errors[] = 'Password baru minimal 6 karakter.';
        }

        if (!$errors) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
            $check->execute([$email, $id]);
            if ((int) $check->fetchColumn() > 0) {
                $errors[] = 'Email sudah dipakai akun lain.';
            }
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('admin/pengguna.php' . ($id > 0 ? '?edit=' . $id : ''));
        }

        if ($id > 0) {
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET nama = ?, email = ?, role = ?, no_hp = ?, kantin_id = ?, password = ? WHERE id = ?');
                $stmt->execute([$nama, $email, $role, $noHp !== '' ? $noHp : null, $kantinId ?: null, password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET nama = ?, email = ?, role = ?, no_hp = ?, kantin_id = ? WHERE id = ?');
                $stmt->execute([$nama, $email, $role, $noHp !== '' ? $noHp : null, $kantinId ?: null, $id]);
            }
            flash('success', 'Akun ' . $nama . ' berhasil diperbarui.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (nama, email, password, role, no_hp, kantin_id) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$nama, $email, password_hash($password, PASSWORD_DEFAULT), $role, $noHp !== '' ? $noHp : null, $kantinId ?: null]);
            flash('success', 'Akun baru untuk ' . $nama . ' berhasil dibuat.');
        }
        redirect('admin/pengguna.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === (int) $user['id']) {
            flash('error', 'Kamu tidak bisa menghapus akunmu sendiri.');
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $target = $stmt->fetch();

            if (!$target) {
                flash('error', 'Akun tidak ditemukan.');
            } elseif ($target['role'] === 'admin') {
                $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                if ($adminCount <= 1) {
                    flash('error', 'Admin terakhir tidak boleh dihapus.');
                } else {
                    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                    flash('success', 'Akun admin ' . $target['nama'] . ' dihapus.');
                }
            } else {
                $orderCount = $pdo->prepare('SELECT COUNT(*) FROM pesanan WHERE user_id = ?');
                $orderCount->execute([$id]);
                if ((int) $orderCount->fetchColumn() > 0) {
                    flash('error', 'Akun ' . $target['nama'] . ' punya riwayat pesanan sehingga tidak bisa dihapus. Nonaktifkan dengan mengubah perannya bila perlu.');
                } else {
                    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                    flash('success', 'Akun ' . $target['nama'] . ' berhasil dihapus.');
                }
            }
        }
        redirect('admin/pengguna.php');
    }

    if ($action === 'reset') {
        $id = (int) ($_POST['id'] ?? 0);
        $newPassword = 'EK' . random_int(100000, 999999);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
        flash('success', 'Password baru berhasil dibuat: ' . $newPassword . ' — catat dan berikan ke pengguna.');
        redirect('admin/pengguna.php');
    }

    flash('error', 'Aksi tidak dikenal.');
    redirect('admin/pengguna.php');
}

$kantins = $pdo->query('SELECT * FROM kantin ORDER BY nama')->fetchAll();

$editing = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
    if (!$editing) {
        flash('error', 'Akun yang ingin diedit tidak ditemukan.');
        redirect('admin/pengguna.php');
    }
}

$users = $pdo->query(
    'SELECT u.*, k.nama AS nama_kantin,
            (SELECT COUNT(*) FROM pesanan p WHERE p.user_id = u.id) AS jumlah_pesanan
     FROM users u LEFT JOIN kantin k ON k.id = u.kantin_id
     ORDER BY FIELD(u.role, "admin", "kantin", "siswa"), u.nama'
)->fetchAll();

$roleCounts = ['admin' => 0, 'kantin' => 0, 'siswa' => 0];
foreach ($users as $row) {
    $roleCounts[$row['role']] = ($roleCounts[$row['role']] ?? 0) + 1;
}

$pageTitle = 'Kelola pengguna';
$active = 'pengguna';
require __DIR__ . '/../includes/header.php';
?>

<section class="section">
    <div class="page-head">
        <div>
            <span class="eyebrow">Manajemen akses</span>
            <h1>Kelola pengguna</h1>
            <p class="section-sub">Tambah akun, atur peran, dan tautkan pengelola ke kantin.</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/index.php')) ?>">← Dashboard</a>
    </div>

    <div class="chips static">
        <span class="chip nonactive">🛡️ Admin <span><?= $roleCounts['admin'] ?></span></span>
        <span class="chip nonactive">👩‍🍳 Pengelola <span><?= $roleCounts['kantin'] ?></span></span>
        <span class="chip nonactive">🎓 Siswa <span><?= $roleCounts['siswa'] ?></span></span>
    </div>

    <div class="menu-admin-layout">
        <div class="panel">
            <div class="panel-head">
                <h2><?= $editing ? 'Edit akun' : 'Tambah akun baru' ?></h2>
                <p><?= $editing ? 'Biarkan password kosong jika tidak ingin menggantinya.' : 'Akun pengelola kantin wajib ditautkan ke kantin.' ?></p>
            </div>

            <form method="post" class="stack-form" data-role-form>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <?php if ($editing): ?>
                    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                <?php endif; ?>

                <label class="field">
                    <span>Nama lengkap</span>
                    <input type="text" name="nama" value="<?= e($editing['nama'] ?? '') ?>" required>
                </label>
                <label class="field">
                    <span>Email</span>
                    <input type="email" name="email" value="<?= e($editing['email'] ?? '') ?>" required>
                </label>
                <div class="form-row">
                    <label class="field">
                        <span>Peran</span>
                        <select name="role" data-role-select>
                            <option value="siswa" <?= ($editing['role'] ?? 'siswa') === 'siswa' ? 'selected' : '' ?>>Siswa</option>
                            <option value="kantin" <?= ($editing['role'] ?? '') === 'kantin' ? 'selected' : '' ?>>Pengelola Kantin</option>
                            <option value="admin" <?= ($editing['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Nomor HP</span>
                        <input type="tel" name="no_hp" value="<?= e($editing['no_hp'] ?? '') ?>" placeholder="08xxxxxxxxxx">
                    </label>
                </div>
                <label class="field" data-kantin-field>
                    <span>Kantin</span>
                    <select name="kantin_id">
                        <option value="0">— Pilih kantin —</option>
                        <?php foreach ($kantins as $kantin): ?>
                            <option value="<?= (int) $kantin['id'] ?>" <?= (int) ($editing['kantin_id'] ?? 0) === (int) $kantin['id'] ? 'selected' : '' ?>>
                                <?= e($kantin['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Wajib untuk peran Pengelola Kantin.</small>
                </label>
                <label class="field">
                    <span>Password <?= $editing ? '(opsional)' : '' ?></span>
                    <input type="text" name="password" <?= $editing ? '' : 'required' ?> placeholder="<?= $editing ? 'Kosongkan jika tidak diubah' : 'Minimal 6 karakter' ?>" autocomplete="new-password">
                </label>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit"><?= $editing ? 'Simpan perubahan' : 'Buat akun' ?></button>
                    <?php if ($editing): ?>
                        <a class="btn btn-ghost" href="<?= e(url('admin/pengguna.php')) ?>">Batal</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Daftar pengguna</h2>
                <p><?= count($users) ?> akun terdaftar.</p>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Pengguna</th><th>Peran</th><th>Kantin</th><th>Pesanan</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $row): ?>
                            <tr>
                                <td>
                                    <div class="table-menu">
                                        <span class="avatar small"><?= e(strtoupper(mb_substr($row['nama'], 0, 1))) ?></span>
                                        <div>
                                            <strong><?= e($row['nama']) ?><?= (int) $row['id'] === (int) $user['id'] ? ' (kamu)' : '' ?></strong>
                                            <small><?= e($row['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge <?= e(role_badge_class($row['role'])) ?>"><?= e(role_label($row['role'])) ?></span></td>
                                <td><?= e($row['nama_kantin'] ?? '—') ?></td>
                                <td><?= (int) $row['jumlah_pesanan'] ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a class="link-more" href="<?= e(url('admin/pengguna.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                                        <form method="post" data-confirm="Buat password baru untuk <?= e($row['nama']) ?>?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reset">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button class="link-more" type="submit">Reset</button>
                                        </form>
                                        <?php if ((int) $row['id'] !== (int) $user['id']): ?>
                                            <form method="post" data-confirm="Hapus akun <?= e($row['nama']) ?>?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                <button class="link-danger" type="submit">Hapus</button>
                                            </form>
                                        <?php endif; ?>
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
