<?php
/**
 * hapus_perpustakaan.php - hapus transaksi (hanya lewat POST + token CSRF)
 */
require_once 'config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('perpustakaan.php');
}
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $del = db()->prepare('DELETE FROM perpustakaan WHERE id = ?');
    $del->execute([$id]);
    if ($del->rowCount() > 0) {
        set_flash('success', 'Data transaksi berhasil dihapus.');
    } else {
        set_flash('danger', 'Data transaksi tidak ditemukan.');
    }
}
redirect('perpustakaan.php#data-transaksi');
