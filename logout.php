<?php
/**
 * logout.php - hapus session lalu kembali ke halaman login
 */
require_once 'config.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

redirect('login.php?logout=1');
