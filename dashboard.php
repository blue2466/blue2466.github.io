<?php
/**
 * dashboard.php - daftar data penelitian + pencarian + statistik
 */
require_once 'config.php';
require_login();

$q       = trim((string) ($_GET['q'] ?? ''));
$perPage = 10;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$where  = '';
$params = [];
if ($q !== '') {
    $where  = 'WHERE nama_dosen LIKE :q1 OR judul_penelitian LIKE :q2';
    $like   = '%' . $q . '%';
    $params = [':q1' => $like, ':q2' => $like];
}

// Statistik (seluruh data)
$stat = db()->query(
    'SELECT COUNT(*) AS total, COALESCE(SUM(dana),0) AS total_dana FROM penelitian'
)->fetch();

// Jumlah per pilihan Halaman Pengesahan
$perJenis = array_fill_keys(jenis_penelitian_options(), 0);
foreach (db()->query('SELECT halaman_pengesahan AS jenis, COUNT(*) AS jml FROM penelitian GROUP BY halaman_pengesahan') as $j) {
    if (isset($perJenis[$j['jenis']])) {
        $perJenis[$j['jenis']] = (int) $j['jml'];
    }
}

// Total hasil pencarian
$cnt = db()->prepare("SELECT COUNT(*) FROM penelitian $where");
$cnt->execute($params);
$totalRows  = (int) $cnt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Ambil data
$stmt = db()->prepare("SELECT * FROM penelitian $where ORDER BY id DESC LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

function page_url(int $p, string $q): string
{
    return 'dashboard.php?' . http_build_query(['q' => $q, 'page' => $p]) . '#data-penelitian';
}

render_header('DATA PENELITIAN DOSEN FIP UNIMED', 'dashboard');
?>
<div class="stats">
    <div class="stat-card">
        <span class="stat-label">Total Penelitian</span>
        <span class="stat-value"><?= (int) $stat['total'] ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Total Dana</span>
        <span class="stat-value"><?= e(rupiah($stat['total_dana'])) ?></span>
    </div>
    <?php foreach ($perJenis as $nama => $jml): ?>
    <div class="stat-card">
        <span class="stat-label"><?= e($nama) ?></span>
        <span class="stat-value"><?= $jml ?></span>
    </div>
    <?php endforeach; ?>
</div>

<section class="card" id="data-penelitian">
    <div class="card-head">
        <h2>Daftar Penelitian</h2>
        <a href="tambah_penelitian.php" class="btn btn-primary">+ Tambah Penelitian</a>
    </div>

    <form method="get" class="search-bar">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari nama dosen atau judul penelitian...">
        <button type="submit" class="btn btn-primary">Cari</button>
        <?php if ($q !== ''): ?><a href="dashboard.php" class="btn btn-light">Reset</a><?php endif; ?>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>NIP/NIDN</th>
                    <th>Judul Penelitian</th>
                    <th>Dana</th>
                    <th>Halaman Pengesahan</th>
                    <th class="col-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="empty">
                    <?= $q !== '' ? 'Data tidak ditemukan untuk pencarian "' . e($q) . '".' : 'Belum ada data penelitian.' ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td data-label="No"><?= $offset + $i + 1 ?></td>
                    <td data-label="Nama"><?= e($r['nama_dosen']) ?></td>
                    <td data-label="NIP/NIDN"><?= e($r['nip_nidn']) ?></td>
                    <td data-label="Judul Penelitian"><?= e($r['judul_penelitian']) ?></td>
                    <td data-label="Dana" class="nowrap"><?= e(rupiah($r['dana'])) ?></td>
                    <td data-label="Halaman Pengesahan">
                        <?php if (in_array($r['halaman_pengesahan'], jenis_penelitian_options(), true)): ?>
                            <span class="badge badge-blue"><?= e($r['halaman_pengesahan']) ?></span>
                        <?php else: ?>
                            <span class="badge badge-gray">Belum dipilih</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Aksi" class="col-aksi">
                        <div class="actions">
                            <a href="detail_penelitian.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm btn-light">Detail</a>
                            <a href="edit_penelitian.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                            <form method="post" action="hapus_penelitian.php" data-confirm="Yakin ingin menghapus data penelitian ini?">
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
            <?php if ($page > 1): ?><a href="<?= e(page_url($page - 1, $q)) ?>">&laquo;</a><?php endif; ?>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <a href="<?= e(page_url($p, $q)) ?>" class="<?= $p === $page ? 'current' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="<?= e(page_url($page + 1, $q)) ?>">&raquo;</a><?php endif; ?>
        </div>
    <?php endif; ?>
    <p class="muted small">Menampilkan <?= count($rows) ?> dari <?= $totalRows ?> data.</p>
</section>
<?php render_footer(); ?>
