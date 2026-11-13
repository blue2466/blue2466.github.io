<?php
/**
 * config.php
 * Konfigurasi database, session, dan fungsi bantu (helper) yang dipakai semua halaman.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ====== KONFIGURASI DATABASE (sesuaikan bila perlu) ====== */
define('DB_HOST', 'localhost');
define('DB_NAME', 'penelitian_dosen_fip');
define('DB_USER', 'root');   // default XAMPP
define('DB_PASS', '');       // default XAMPP kosong

/* ====== KONFIGURASI UPLOAD ====== */
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB
define('APP_TITLE', 'SISTEM INFORMASI DATA PENELITIAN DOSEN FIP UNIMED');

/* ====== KONEKSI PDO ====== */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die('Koneksi database gagal. Pastikan MySQL di XAMPP aktif dan database.sql sudah diimpor.');
        }
    }
    return $pdo;
}

/* ====== HELPER UMUM ====== */
function e(?string $str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function rupiah($angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

/* ====== CSRF ====== */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        die('Sesi formulir tidak valid. Silakan kembali dan muat ulang halaman.');
    }
}

/* ====== FLASH MESSAGE ====== */
function set_flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/* ====== PILIHAN HALAMAN PENGESAHAN (pilih salah satu) ====== */
function jenis_penelitian_options(): array
{
    return ['Penelitian Dosen Pemula', 'PKM', 'Penelitian Dasar'];
}

/* ====== VALIDASI DATA PENELITIAN ====== */
function validate_penelitian(array $in): array
{
    $err = [];
    if (!in_array((string) ($in['halaman_pengesahan'] ?? ''), jenis_penelitian_options(), true)) {
        $err['halaman_pengesahan'] = 'Pilih salah satu: Penelitian Dosen Pemula, PKM, atau Penelitian Dasar.';
    }
    $nama  = trim((string) ($in['nama_dosen'] ?? ''));
    $nip   = trim((string) ($in['nip_nidn'] ?? ''));
    $judul = trim((string) ($in['judul_penelitian'] ?? ''));
    $dana  = trim((string) ($in['dana'] ?? ''));

    if ($nama === '' || mb_strlen($nama) > 150) {
        $err['nama_dosen'] = 'Nama dosen wajib diisi (maksimal 150 karakter).';
    }
    if (!preg_match('/^[0-9]{8,25}$/', $nip)) {
        $err['nip_nidn'] = 'NIP/NIDN harus berupa angka (8–25 digit).';
    }
    if ($judul === '' || mb_strlen($judul) > 500) {
        $err['judul_penelitian'] = 'Judul penelitian wajib diisi (maksimal 500 karakter).';
    }
    if (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $dana)) {
        $err['dana'] = 'Dana harus berupa angka (tanpa titik/koma ribuan).';
    }
    return $err;
}

/* ====== UPLOAD FILE ====== */
/**
 * Mengembalikan [namaFileBaru|null, pesanError|null]
 * Hanya PDF, JPG, JPEG, PNG dengan maks. 5 MB.
 */
function handle_upload(array $file): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null]; // tidak ada file diunggah (opsional)
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Upload file gagal. Coba lagi (kode ' . (int) $file['error'] . ').'];
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return [null, 'Ukuran file maksimal 5 MB.'];
    }

    $allowed = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
    ];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        return [null, 'Format file harus PDF, JPG, JPEG, atau PNG.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed[$ext], true)) {
        return [null, 'Isi file tidak sesuai dengan ekstensinya.'];
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $newName)) {
        return [null, 'Gagal menyimpan file ke folder uploads.'];
    }
    return [$newName, null];
}

function delete_upload(?string $filename): void
{
    if ($filename && preg_match('/^[a-f0-9]{32}\.(pdf|jpg|jpeg|png)$/', $filename)) {
        $path = UPLOAD_DIR . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

/* ====== IKON SVG SIDEBAR ====== */
function icon_svg(string $name): string
{
    $p = [
        'home'   => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'list'   => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/>',
        'plus'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'book'   => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($p[$name] ?? '') . '</svg>';
}

/* ====== PERPUSTAKAAN: PILIHAN & VALIDASI ====== */
function jenis_transaksi_options(): array
{
    return ['Meminjam Buku', 'Mengembalikan Buku'];
}

function validate_perpustakaan(array $in): array
{
    $err = [];
    $nama    = trim((string) ($in['nama'] ?? ''));
    $nim     = trim((string) ($in['nim'] ?? ''));
    $jenis   = (string) ($in['jenis_transaksi'] ?? '');
    $judul   = trim((string) ($in['judul_buku'] ?? ''));
    $tanggal = trim((string) ($in['tanggal_transaksi'] ?? ''));

    if ($nama === '' || mb_strlen($nama) > 150) {
        $err['nama'] = 'Nama mahasiswa wajib diisi (maksimal 150 karakter).';
    }
    if (!preg_match('/^[0-9]{5,20}$/', $nim)) {
        $err['nim'] = 'NIM harus berupa angka (5–20 digit).';
    }
    if (!in_array($jenis, jenis_transaksi_options(), true)) {
        $err['jenis_transaksi'] = 'Pilih salah satu: Meminjam Buku atau Mengembalikan Buku.';
    }
    if ($judul === '' || mb_strlen($judul) > 255) {
        $err['judul_buku'] = 'Judul buku wajib diisi (maksimal 255 karakter).';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $tanggal);
    if (!$dt || $dt->format('Y-m-d') !== $tanggal) {
        $err['tanggal_transaksi'] = 'Tanggal transaksi tidak valid.';
    }
    return $err;
}

/**
 * Form transaksi perpustakaan (dipakai di halaman daftar untuk tambah, dan di halaman edit).
 */
function render_form_perpustakaan(array $d, array $err, string $btnText, ?int $id = null, string $cancelUrl = ''): void
{
    ?>
    <form method="post" class="form-card-full" novalidate>
        <?= csrf_field() ?>
        <?php if ($id !== null): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="nama">Nama Mahasiswa</label>
                <input type="text" id="nama" name="nama" maxlength="150" required
                       value="<?= e($d['nama'] ?? '') ?>" placeholder="Nama lengkap mahasiswa">
                <?php if (isset($err['nama'])): ?><small class="field-error"><?= e($err['nama']) ?></small><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="nim">NIM</label>
                <input type="text" id="nim" name="nim" maxlength="20" inputmode="numeric" required
                       value="<?= e($d['nim'] ?? '') ?>" placeholder="Hanya angka">
                <?php if (isset($err['nim'])): ?><small class="field-error"><?= e($err['nim']) ?></small><?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label>Jenis Transaksi</label>
            <div class="radio-group">
                <?php foreach (jenis_transaksi_options() as $opsi): ?>
                    <label class="radio-option">
                        <input type="radio" name="jenis_transaksi" value="<?= e($opsi) ?>" required
                               <?= ($d['jenis_transaksi'] ?? '') === $opsi ? 'checked' : '' ?>>
                        <span><?= e($opsi) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if (isset($err['jenis_transaksi'])): ?><small class="field-error"><?= e($err['jenis_transaksi']) ?></small><?php endif; ?>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label for="judul_buku">Judul Buku</label>
                <input type="text" id="judul_buku" name="judul_buku" maxlength="255" required
                       value="<?= e($d['judul_buku'] ?? '') ?>" placeholder="Judul buku">
                <?php if (isset($err['judul_buku'])): ?><small class="field-error"><?= e($err['judul_buku']) ?></small><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="tanggal_transaksi">Tanggal Transaksi</label>
                <input type="date" id="tanggal_transaksi" name="tanggal_transaksi" required
                       value="<?= e($d['tanggal_transaksi'] ?? '') ?>">
                <?php if (isset($err['tanggal_transaksi'])): ?><small class="field-error"><?= e($err['tanggal_transaksi']) ?></small><?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= e($btnText) ?></button>
            <?php if ($cancelUrl !== ''): ?><a href="<?= e($cancelUrl) ?>" class="btn btn-light">Batal</a><?php endif; ?>
        </div>
    </form>
<?php
}

/* ====== LAYOUT (sidebar + topbar) ====== */
function render_header(string $title, string $active = ''): void
{
    $flash = get_flash();
    $nama  = $_SESSION['nama_lengkap'] ?? 'Pengguna';
    $menus = [
        'dashboard'    => ['dashboard.php', 'Dashboard', icon_svg('home')],
        'data'         => ['dashboard.php#data-penelitian', 'Data Penelitian', icon_svg('list')],
        'tambah'       => ['tambah_penelitian.php', 'Tambah Penelitian', icon_svg('plus')],
        'perpustakaan' => ['perpustakaan.php', 'Data Perpustakaan', icon_svg('book')],
    ];
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | FIP UNIMED</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-logo">FIP</div>
            <div>
                <strong>Data Penelitian</strong>
                <small>Dosen FIP UNIMED</small>
            </div>
        </div>
        <nav class="menu">
            <?php foreach ($menus as $key => $m): ?>
                <a href="<?= $m[0] ?>" class="<?= $active === $key ? 'active' : '' ?>">
                    <span class="ico"><?= $m[2] ?></span> <?= e($m[1]) ?>
                </a>
            <?php endforeach; ?>
            <a href="logout.php" class="logout" onclick="return confirm('Yakin ingin keluar?')">
                <span class="ico"><?= icon_svg('logout') ?></span> Logout
            </a>
        </nav>
    </aside>
    <div class="overlay" id="overlay"></div>

    <div class="main">
        <header class="topbar">
            <button class="burger" id="burger" aria-label="Buka menu">&#9776;</button>
            <h1 class="topbar-title"><?= e($title) ?></h1>
            <div class="user-chip">
                <span class="avatar"><?= e(mb_strtoupper(mb_substr($nama, 0, 1))) ?></span>
                <span class="user-name"><?= e($nama) ?></span>
            </div>
        </header>
        <main class="content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" data-autohide><?= e($flash['msg']) ?></div>
            <?php endif; ?>
<?php
}

function render_footer(): void
{
    ?>
        </main>
        <footer class="footer">&copy; <?= date('Y') ?> Fakultas Ilmu Pendidikan &middot; Universitas Negeri Medan</footer>
    </div>
</div>
<script>
(function () {
    var sb = document.getElementById('sidebar');
    var ov = document.getElementById('overlay');
    var bg = document.getElementById('burger');
    function toggle() { sb.classList.toggle('open'); ov.classList.toggle('show'); }
    if (bg) { bg.addEventListener('click', toggle); }
    if (ov) { ov.addEventListener('click', toggle); }

    document.querySelectorAll('[data-autohide]').forEach(function (el) {
        setTimeout(function () { el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 400); }, 4500);
    });
    document.querySelectorAll('form[data-confirm]').forEach(function (f) {
        f.addEventListener('submit', function (ev) {
            if (!confirm(f.getAttribute('data-confirm'))) { ev.preventDefault(); }
        });
    });
    var fi = document.getElementById('file_pengesahan');
    if (fi) {
        fi.addEventListener('change', function () {
            var t = document.getElementById('file-name');
            if (t) { t.textContent = fi.files.length ? fi.files[0].name : 'Belum ada file dipilih'; }
        });
    }
})();
</script>
</body>
</html>
<?php
}

/* ====== FORM PENELITIAN (dipakai tambah & edit) ====== */
function render_form_penelitian(array $d, array $err, string $btnText, ?string $fileLama = null, ?int $id = null): void
{
    ?>
    <form method="post" class="card form-card" novalidate>
        <?= csrf_field() ?>
        <?php if ($id !== null): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="nama_dosen">Nama Dosen</label>
                <input type="text" id="nama_dosen" name="nama_dosen" maxlength="150" required
                       value="<?= e($d['nama_dosen'] ?? '') ?>" placeholder="Contoh: Dr. Nama Dosen, M.Pd.">
                <?php if (isset($err['nama_dosen'])): ?><small class="field-error"><?= e($err['nama_dosen']) ?></small><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="nip_nidn">NIP / NIDN</label>
                <input type="text" id="nip_nidn" name="nip_nidn" maxlength="25" inputmode="numeric" required
                       value="<?= e($d['nip_nidn'] ?? '') ?>" placeholder="Hanya angka">
                <?php if (isset($err['nip_nidn'])): ?><small class="field-error"><?= e($err['nip_nidn']) ?></small><?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="judul_penelitian">Judul Penelitian</label>
            <textarea id="judul_penelitian" name="judul_penelitian" rows="3" maxlength="500" required
                      placeholder="Tuliskan judul penelitian"><?= e($d['judul_penelitian'] ?? '') ?></textarea>
            <?php if (isset($err['judul_penelitian'])): ?><small class="field-error"><?= e($err['judul_penelitian']) ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label for="dana">Dana (Rupiah)</label>
            <input type="number" id="dana" name="dana" min="0" step="1" required
                   value="<?= e($d['dana'] ?? '') ?>" placeholder="Contoh: 15000000">
            <?php if (isset($err['dana'])): ?><small class="field-error"><?= e($err['dana']) ?></small><?php endif; ?>
        </div>

        <div class="form-group">
            <label>Halaman Pengesahan <span class="muted">(pilih salah satu)</span></label>
            <div class="radio-group">
                <?php foreach (jenis_penelitian_options() as $i => $opsi): ?>
                    <label class="radio-option">
                        <input type="radio" name="halaman_pengesahan" value="<?= e($opsi) ?>" required
                               <?= ($d['halaman_pengesahan'] ?? '') === $opsi ? 'checked' : '' ?>>
                        <span><?= e($opsi) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if (isset($err['halaman_pengesahan'])): ?><small class="field-error"><?= e($err['halaman_pengesahan']) ?></small><?php endif; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= e($btnText) ?></button>
            <a href="dashboard.php" class="btn btn-light">Batal</a>
        </div>
    </form>
<?php
}
