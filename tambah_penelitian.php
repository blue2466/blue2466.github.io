<?php
/**
 * tambah_penelitian.php - form tambah data penelitian
 */
require_once 'config.php';
require_login();

$data = ['nama_dosen' => '', 'nip_nidn' => '', 'judul_penelitian' => '', 'dana' => '', 'halaman_pengesahan' => ''];
$err  = [];

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
        $stmt = db()->prepare(
            'INSERT INTO penelitian (user_id, nama_dosen, nip_nidn, judul_penelitian, dana, halaman_pengesahan)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $_SESSION['user_id'],
            $data['nama_dosen'],
            $data['nip_nidn'],
            $data['judul_penelitian'],
            $data['dana'],
            $data['halaman_pengesahan'],
        ]);
        set_flash('success', 'Data penelitian berhasil ditambahkan.');
        redirect('dashboard.php#data-penelitian');
    }
}

render_header('Tambah Penelitian', 'tambah');
render_form_penelitian($data, $err, 'Simpan Data');
render_footer();
