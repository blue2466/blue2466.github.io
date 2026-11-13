<?php
/**
 * edit_penelitian.php - form ubah data penelitian
 */
require_once 'config.php';
require_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    redirect('dashboard.php');
}

$stmt = db()->prepare('SELECT * FROM penelitian WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    set_flash('danger', 'Data penelitian tidak ditemukan.');
    redirect('dashboard.php');
}

$data = [
    'nama_dosen'         => $row['nama_dosen'],
    'nip_nidn'           => $row['nip_nidn'],
    'judul_penelitian'   => $row['judul_penelitian'],
    'dana'               => (string) (int) $row['dana'],
    'halaman_pengesahan' => (string) $row['halaman_pengesahan'],
];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $data = [
        'nama_dosen'         => trim((string) ($_POST['nama_dosen'] ?? '')),
        'nip_nidn'           => trim((string) ($_POST['nip_nidn'] ?? '')),
        'judul_penelitian'   => trim((string) ($_POST['judul_penelitian'] ?? '')),
        'dana'               => trim((string) ($_POST['dana'] ?? '')),
        'halaman_pengesahan' => (string) ($_POST['halaman_pengesahan'] ?? ''),
    ];
    $err = validate_penelitian($data);

    if (!$err) {
        $upd = db()->prepare(
            'UPDATE penelitian
                SET nama_dosen = ?, nip_nidn = ?, judul_penelitian = ?, dana = ?, halaman_pengesahan = ?
              WHERE id = ?'
        );
        $upd->execute([
            $data['nama_dosen'],
            $data['nip_nidn'],
            $data['judul_penelitian'],
            $data['dana'],
            $data['halaman_pengesahan'],
            $id,
        ]);
        set_flash('success', 'Data penelitian berhasil diperbarui.');
        redirect('dashboard.php#data-penelitian');
    }
}

render_header('Edit Penelitian', 'data');
render_form_penelitian($data, $err, 'Simpan Perubahan', null, $id);
render_footer();
