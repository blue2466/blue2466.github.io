<?php
/**
 * edit_perpustakaan.php - form ubah data transaksi perpustakaan
 */
require_once 'config.php';
require_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    redirect('perpustakaan.php');
}

$stmt = db()->prepare('SELECT * FROM perpustakaan WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    set_flash('danger', 'Data transaksi tidak ditemukan.');
    redirect('perpustakaan.php');
}

$data = [
    'nama'              => $row['nama'],
    'nim'               => $row['nim'],
    'jenis_transaksi'   => $row['jenis_transaksi'],
    'judul_buku'        => $row['judul_buku'],
    'tanggal_transaksi' => $row['tanggal_transaksi'],
];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $data = [
        'nama'              => trim((string) ($_POST['nama'] ?? '')),
        'nim'               => trim((string) ($_POST['nim'] ?? '')),
        'jenis_transaksi'   => (string) ($_POST['jenis_transaksi'] ?? ''),
        'judul_buku'        => trim((string) ($_POST['judul_buku'] ?? '')),
        'tanggal_transaksi' => trim((string) ($_POST['tanggal_transaksi'] ?? '')),
    ];
    $err = validate_perpustakaan($data);

    if (!$err) {
        $upd = db()->prepare(
            'UPDATE perpustakaan
                SET nama = ?, nim = ?, jenis_transaksi = ?, judul_buku = ?, tanggal_transaksi = ?
              WHERE id = ?'
        );
        $upd->execute([
            $data['nama'],
            $data['nim'],
            $data['jenis_transaksi'],
            $data['judul_buku'],
            $data['tanggal_transaksi'],
            $id,
        ]);
        set_flash('success', 'Data transaksi berhasil diperbarui.');
        redirect('perpustakaan.php#data-transaksi');
    }
}

render_header('Edit Transaksi Perpustakaan', 'perpustakaan');
?>
<section class="card form-card">
    <div class="card-head"><h2>Ubah Data Transaksi</h2></div>
    <?php render_form_perpustakaan($data, $err, 'Simpan Perubahan', $id, 'perpustakaan.php#data-transaksi'); ?>
</section>
<?php render_footer(); ?>
