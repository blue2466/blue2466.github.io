<?php
/**
 * detail_penelitian.php - tampilkan detail satu data penelitian
 */
require_once 'config.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('dashboard.php');
}

$stmt = db()->prepare(
    'SELECT p.*, u.nama_lengkap AS penginput
       FROM penelitian p JOIN users u ON u.id = p.user_id
      WHERE p.id = ?'
);
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) {
    set_flash('danger', 'Data penelitian tidak ditemukan.');
    redirect('dashboard.php');
}

$jenis = in_array($r['halaman_pengesahan'], jenis_penelitian_options(), true) ? $r['halaman_pengesahan'] : 'Belum dipilih';

render_header('Detail Penelitian', 'data');
?>
<section class="card">
    <div class="card-head">
        <h2>Informasi Penelitian</h2>
        <div class="actions">
            <a href="edit_penelitian.php?id=<?= (int) $r['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
            <a href="dashboard.php" class="btn btn-light btn-sm">&larr; Kembali</a>
        </div>
    </div>

    <dl class="detail-list">
        <dt>Nama Dosen</dt><dd><?= e($r['nama_dosen']) ?></dd>
        <dt>NIP/NIDN</dt><dd><?= e($r['nip_nidn']) ?></dd>
        <dt>Judul Penelitian</dt><dd><?= e($r['judul_penelitian']) ?></dd>
        <dt>Dana</dt><dd><?= e(rupiah($r['dana'])) ?></dd>
        <dt>Halaman Pengesahan</dt><dd><?= e($jenis) ?></dd>
        <dt>Diinput oleh</dt><dd><?= e($r['penginput']) ?></dd>
        <dt>Dibuat</dt><dd><?= e(date('d-m-Y H:i', strtotime($r['created_at']))) ?></dd>
        <dt>Terakhir diubah</dt><dd><?= e(date('d-m-Y H:i', strtotime($r['updated_at']))) ?></dd>
    </dl>
</section>
<?php render_footer(); ?>
