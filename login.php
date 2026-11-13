<?php
/**
 * login.php - halaman login
 */
require_once 'config.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = db()->prepare('SELECT id, nama_lengkap, username, password FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']      = (int) $user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            redirect('dashboard.php');
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Data Penelitian Dosen FIP UNIMED</title>
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
            <h1>Selamat Datang</h1>
            <p class="muted">Silakan login untuk mengelola data penelitian dosen.</p>

            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success">Registrasi berhasil. Silakan login.</div>
            <?php endif; ?>
            <?php if (isset($_GET['logout'])): ?>
                <div class="alert alert-success">Anda telah berhasil logout.</div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>

            <p class="auth-switch">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
        </div>
    </div>
</body>
</html>
