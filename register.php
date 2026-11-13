<?php
/**
 * register.php - halaman registrasi akun
 */
require_once 'config.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$old    = ['nama_lengkap' => '', 'username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nama     = trim((string) ($_POST['nama_lengkap'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['konfirmasi_password'] ?? '');
    $old      = ['nama_lengkap' => $nama, 'username' => $username];

    if (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
        $errors['nama_lengkap'] = 'Nama lengkap harus 3–100 karakter.';
    }
    if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $username)) {
        $errors['username'] = 'Username 4–30 karakter: huruf, angka, titik, atau underscore.';
    }
    if (strlen($password) < 6) {
        $errors['password'] = 'Password minimal 6 karakter.';
    }
    if ($password !== $confirm) {
        $errors['konfirmasi_password'] = 'Konfirmasi password tidak sama.';
    }

    if (!$errors) {
        $cek = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $cek->execute([$username]);
        if ($cek->fetch()) {
            $errors['username'] = 'Username sudah digunakan. Pilih username lain.';
        }
    }

    if (!$errors) {
        try {
            $ins = db()->prepare('INSERT INTO users (nama_lengkap, username, password) VALUES (?, ?, ?)');
            $ins->execute([$nama, $username, password_hash($password, PASSWORD_DEFAULT)]);
            redirect('login.php?registered=1');
        } catch (PDOException $ex) {
            $errors['umum'] = 'Terjadi kesalahan saat menyimpan akun. Coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrasi | Data Penelitian Dosen FIP UNIMED</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
    <div class="auth-wrap">
        <div class="auth-side">
            <div class="brand-logo big">FIP</div>
            <h2>SISTEM INFORMASI DATA PENELITIAN DOSEN</h2>
            <p>Fakultas Ilmu Pendidikan<br>Universitas Negeri Medan</p>
        </div>
        <div class="auth-card">
            <h1>Buat Akun Baru</h1>
            <p class="muted">Isi data berikut untuk mendaftar.</p>

            <?php if (isset($errors['umum'])): ?>
                <div class="alert alert-danger"><?= e($errors['umum']) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="nama_lengkap">Nama Lengkap</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?= e($old['nama_lengkap']) ?>" required autofocus>
                    <?php if (isset($errors['nama_lengkap'])): ?><small class="field-error"><?= e($errors['nama_lengkap']) ?></small><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= e($old['username']) ?>" autocomplete="username" required>
                    <?php if (isset($errors['username'])): ?><small class="field-error"><?= e($errors['username']) ?></small><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" required>
                    <?php if (isset($errors['password'])): ?><small class="field-error"><?= e($errors['password']) ?></small><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="konfirmasi_password">Konfirmasi Password</label>
                    <input type="password" id="konfirmasi_password" name="konfirmasi_password" autocomplete="new-password" required>
                    <?php if (isset($errors['konfirmasi_password'])): ?><small class="field-error"><?= e($errors['konfirmasi_password']) ?></small><?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Daftar</button>
            </form>

            <a href="login.php" class="btn btn-light btn-block" style="margin-top:12px">&larr; Kembali ke Login</a>
        </div>
    </div>
</body>
</html>
