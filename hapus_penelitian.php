<?php
/**
 * hapus_penelitian.php - hapus data (hanya lewat POST + token CSRF)
 */
require_once 'config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = db()->prepare('SELECT halaman_pengesahan FROM penelitian WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if ($row) {
        $del = db()->prepare('DELETE FROM penelitian WHERE id = ?');
        $del->execute([$id]);
        delete_upload($row['halaman_pengesahan']);
        set_flash('success', 'Data penelitian berhasil dihapus.');
    } else {
        set_flash('danger', 'Data penelitian tidak ditemukan.');
    }
}
redirect('dashboard.php#data-penelitian');
