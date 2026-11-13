<?php
/**
 * perpustakaan.php - data transaksi perpustakaan:
 * form tambah (pinjam / kembali), pencarian, filter jenis transaksi, daftar + pagination.
 */
require_once 'config.php';
require_login();

/* ---------- Tambah transaksi (POST) ---------- */
$data = ['nama' => '', 'nim' => '', 'jenis_transaksi' => '', 'judul_buku' => '', 'tanggal_transaksi' => date('Y-m-d')];
$err  = [];

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
        $stmt = db()->prepare(
            'INSERT INTO perpustakaan (user_id, nama, nim, jenis_transaksi, judul_buku, tanggal_transaksi)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $_SESSION['user_id'],
            $data['nama'],
            $data['nim'],
            $data['jenis_transaksi'],
            $data['judul_buku'],
            $data['tanggal_transaksi'],
        ]);
        set_flash('success', $data['jenis_transaksi'] === 'Meminjam Buku'
            ? 'Transaksi peminjaman buku berhasil dicatat.'
            : 'Transaksi pengembalian buku berhasil dicatat.');
        redirect('perpustakaan.php#data-transaksi');
    }
}

/* ---------- Pencarian + filter ---------- */
$q       = trim((string) ($_GET['q'] ?? ''));
$jenis   = (string) ($_GET['jenis'] ?? '');
if (!in_array($jenis, jenis_transaksi_options(), true)) {
    $jenis = '';
}
$perPage = 10;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$conds  = [];
$params = [];
if ($q !== '') {
    $conds[] = '(p.nama LIKE :q1 OR p.nim LIKE :q2 OR p.judul_buku LIKE :q3)';
    $like    = '%' . $q . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
}
if ($jenis !== '') {
    $conds[] = 'p.jenis_transaksi = :jenis';
    $params[':jenis'] = $jenis;
}
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

/* Ekspresi status: peminjaman dianggap "sudah dikembalikan" bila ada transaksi
   pengembalian untuk NIM + judul buku yang sama pada tanggal yang sama atau sesudahnya. */
$sudahKembali = "EXISTS (SELECT 1 FROM perpustakaan r
                          WHERE r.jenis_transaksi = 'Mengembalikan Buku'
                            AND r.nim = p.nim AND r.judul_buku = p.judul_buku
                            AND r.tanggal_transaksi >= p.tanggal_transaksi)";

/* ---------- Statistik (seluruh data) ---------- */
$stat = db()->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(p.jenis_transaksi = 'Meminjam Buku'), 0) AS pinjam,
            COALESCE(SUM(p.jenis_transaksi = 'Mengembalikan Buku'), 0) AS kembali,
            COALESCE(SUM(p.jenis_transaksi = 'Meminjam Buku' AND NOT $sudahKembali), 0) AS belum
       FROM perpustakaan p"
)->fetch();

/* ---------- Total + data ---------- */
$cnt = db()->prepare("SELECT COUNT(*) FROM perpustakaan p $where");
$cnt->execute($params);
$totalRows  = (int) $cnt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = db()->prepare(
    "SELECT p.*, ($sudahKembali) AS sudah_kembali
       FROM perpustakaan p $where
      ORDER BY p.tanggal_transaksi DESC, p.id DESC
      LIMIT :lim OFFSET :off"
);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

function perpus_url(int $p, string $q, string $jenis): string
{
    return 'perpustakaan.php?' . http_build_query(['q' => $q, 'jenis' => $jenis, 'page' => $p]) . '#data-transaksi';
}

render_header('DATA PERPUSTAKAAN', 'perpustakaan');
?>
<div class="stats">
    <div class="stat-card">
        <span class="stat-label">Total Transaksi</span>
        <span class="stat-value"><?= (int) $stat['total'] ?></span>
    </div>
    <div class="stat-card stat-blue">
        <span class="stat-label">Transaksi Peminjaman</span>
        <span class="stat-value"><?= (int) $stat['pinjam'] ?></span>
    </div>
    <div class="stat-card stat-green">
        <span class="stat-label">Transaksi Pengembalian</span>
        <span class="stat-value"><?= (int) $stat['kembali'] ?></span>
    </div>
    <div class="stat-card stat-amber">
        <span class="stat-label">Buku Belum Dikembalikan</span>
        <span class="stat-value"><?= (int) $stat['belum'] ?></span>
    </div>
</div>

<section class="card" id="form-transaksi">
    <div class="card-head">
        <h2>Input Transaksi Perpustakaan</h2>
    </div>
    <?php render_form_perpustakaan($data, $err, 'Simpan Data'); ?>
</section>

<section class="card" id="data-transaksi">
    <div class="card-head">
        <h2>Daftar Transaksi</h2>
    </div>

    <form method="get" class="search-bar">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari nama, NIM, atau judul buku...">
        <select name="jenis" aria-label="Filter jenis transaksi">
            <option value="">Semua Jenis Transaksi</option>
            <?php foreach (jenis_transaksi_options() as $opsi): ?>
                <option value="<?= e($opsi) ?>" <?= $jenis === $opsi ? 'selected' : '' ?>><?= e($opsi) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Cari</button>
        <?php if ($q !== '' || $jenis !== ''): ?><a href="perpustakaan.php#data-transaksi" class="btn btn-light">Reset</a><?php endif; ?>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama</th>
                    <th>NIM</th>
                    <th>Jenis Transaksi</th>
                    <th>Judul Buku</th>
                    <th>Tanggal Transaksi</th>
                    <th>Status</th>
                    <th class="col-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="empty">
                    <?= ($q !== '' || $jenis !== '') ? 'Data tidak ditemukan untuk pencarian/filter yang dipilih.' : 'Belum ada data transaksi perpustakaan.' ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $i => $r): ?>
                <?php
                $isPinjam = $r['jenis_transaksi'] === 'Meminjam Buku';
                if ($isPinjam) {
                    $statusText  = $r['sudah_kembali'] ? 'Sudah Dikembalikan' : 'Dipinjam';
                    $statusClass = $r['sudah_kembali'] ? 'badge-ok' : 'badge-warn';
                } else {
                    $statusText  = 'Dikembalikan';
                    $statusClass = 'badge-ok';
                }
                ?>
                <tr>
                    <td data-label="No."><?= $offset + $i + 1 ?></td>
                    <td data-label="Nama"><?= e($r['nama']) ?></td>
                    <td data-label="NIM"><?= e($r['nim']) ?></td>
                    <td data-label="Jenis Transaksi">
                        <span class="badge <?= $isPinjam ? 'badge-blue' : 'badge-teal' ?>"><?= e($r['jenis_transaksi']) ?></span>
                    </td>
                    <td data-label="Judul Buku"><?= e($r['judul_buku']) ?></td>
                    <td data-label="Tanggal Transaksi" class="nowrap"><?= e(date('d-m-Y', strtotime($r['tanggal_transaksi']))) ?></td>
                    <td data-label="Status"><span class="badge <?= $statusClass ?>"><?= e($statusText) ?></span></td>
                    <td data-label="Aksi" class="col-aksi">
                        <div class="actions">
                            <a href="edit_perpustakaan.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                            <form method="post" action="hapus_perpustakaan.php" data-confirm="Yakin ingin menghapus data transaksi ini?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?><a href="<?= e(perpus_url($page - 1, $q, $jenis)) ?>">&laquo;</a><?php endif; ?>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <a href="<?= e(perpus_url($p, $q, $jenis)) ?>" class="<?= $p === $page ? 'current' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e(perpus_url($page + 1, $q, $jenis)) ?>">&raquo;</a><?php endif; ?>
        </div>
    <?php endif; ?>
    <p class="muted small">Menampilkan <?= count($rows) ?> dari <?= $totalRows ?> data.</p>
</section>
<?php render_footer(); ?>
