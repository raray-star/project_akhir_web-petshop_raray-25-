<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/koneksi.php';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}

function pesanGrooming($pesan, $tipe = 'error') {
    $_SESSION['pesan_grooming'] = $pesan;
    $_SESSION['tipe_pesan_grooming'] = $tipe;
}

function wajibPelanggan() {
    if (
        !isset($_SESSION['user_id']) ||
        ($_SESSION['role'] ?? '') !== 'pelanggan'
    ) {
        pesanGrooming('Silakan login sebagai pelanggan terlebih dahulu.');
        header('Location: login.php');
        exit;
    }
}

$sudahLogin = isset($_SESSION['user_id']);
$userId = $sudahLogin ? (int)$_SESSION['user_id'] : 0;
$namaPengguna = $_SESSION['nama'] ?? '';
$role = $_SESSION['role'] ?? '';

$isPelanggan = $sudahLogin && $role === 'pelanggan';
$isAdmin = $sudahLogin && $role === 'admin';

/* Jumlah barang dalam keranjang */
$totalKeranjang = 0;

if ($isPelanggan) {
    $stmtKeranjang = mysqli_prepare($koneksi, "
        SELECT COALESCE(SUM(jumlah), 0) AS total
        FROM keranjang
        WHERE user_id = ?
    ");

    if ($stmtKeranjang) {
        mysqli_stmt_bind_param($stmtKeranjang, 'i', $userId);
        mysqli_stmt_execute($stmtKeranjang);
        $hasil = mysqli_stmt_get_result($stmtKeranjang);
        $data = mysqli_fetch_assoc($hasil);
        $totalKeranjang = (int)($data['total'] ?? 0);
        mysqli_stmt_close($stmtKeranjang);
    }
}

/* Pesan notifikasi */
$pesan = $_SESSION['pesan_grooming'] ?? '';
$tipePesan = $_SESSION['tipe_pesan_grooming'] ?? 'success';

unset($_SESSION['pesan_grooming'], $_SESSION['tipe_pesan_grooming']);

/* =====================================
   TAMBAH DATA HEWAN
===================================== */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['aksi'] ?? '') === 'tambah_hewan'
) {
    wajibPelanggan();

    $jenis = trim($_POST['jenis'] ?? '');
    $ras = trim($_POST['ras'] ?? '');
    $umur = trim($_POST['umur'] ?? '');
    $jenisKelamin = trim($_POST['jenis_kelamin'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    if (!in_array($jenis, ['Kucing', 'Anjing'], true)) {
        pesanGrooming('Silakan pilih jenis hewan.');
    } elseif (
        $umur !== '' &&
        (!ctype_digit($umur) || (int)$umur > 100)
    ) {
        pesanGrooming('Umur harus berupa angka antara 0 sampai 100.');
    } elseif (
        $jenisKelamin !== '' &&
        !in_array($jenisKelamin, ['Jantan', 'Betina'], true)
    ) {
        pesanGrooming('Jenis kelamin tidak valid.');
    } else {
        $namaHewan = 'Hewan Peliharaan';

        $stmt = mysqli_prepare($koneksi, "
            INSERT INTO hewan
                (user_id, nama_hewan, jenis, ras, umur, jenis_kelamin, catatan)
            VALUES (?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?)
        ");

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                'issssss',
                $userId,
                $namaHewan,
                $jenis,
                $ras,
                $umur,
                $jenisKelamin,
                $catatan
            );

            if (mysqli_stmt_execute($stmt)) {
                pesanGrooming('Data hewan berhasil ditambahkan.', 'success');
            } else {
                pesanGrooming('Data hewan gagal disimpan.');
            }

            mysqli_stmt_close($stmt);
        } else {
            pesanGrooming('Query data hewan gagal dibuat.');
        }
    }

    header('Location: grooming.php#data-hewan');
    exit;
}

/* =====================================
   PROSES BOOKING
===================================== */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['aksi'] ?? '') === 'booking'
) {
    wajibPelanggan();

    $hewanId = (int)($_POST['hewan_id'] ?? 0);
    $layananId = (int)($_POST['layanan_id'] ?? 0);
    $jadwalId = (int)($_POST['jadwal_id'] ?? 0);

    $stmt = mysqli_prepare($koneksi, "
        SELECT id, harga, jenis_hewan
        FROM layanan_grooming
        WHERE id = ?
        LIMIT 1
    ");

    $layanan = null;

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $layananId);
        mysqli_stmt_execute($stmt);
        $hasil = mysqli_stmt_get_result($stmt);
        $layanan = mysqli_fetch_assoc($hasil);
        mysqli_stmt_close($stmt);
    }

    $stmt = mysqli_prepare($koneksi, "
        SELECT id, jenis
        FROM hewan
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");

    $hewan = null;

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $hewanId, $userId);
        mysqli_stmt_execute($stmt);
        $hasil = mysqli_stmt_get_result($stmt);
        $hewan = mysqli_fetch_assoc($hasil);
        mysqli_stmt_close($stmt);
    }

    if (!$layanan || !$hewan || $jadwalId < 1) {
        pesanGrooming('Silakan pilih layanan, hewan, dan jadwal yang benar.');
        header('Location: grooming.php#booking');
        exit;
    }

    $jenisLayanan = strtolower(trim($layanan['jenis_hewan']));
    $jenisHewan = strtolower(trim($hewan['jenis']));

    $khususKucing =
        strpos($jenisLayanan, 'kucing') !== false &&
        strpos($jenisLayanan, 'anjing') === false &&
        strpos($jenisLayanan, 'semua') === false;

    $khususAnjing =
        strpos($jenisLayanan, 'anjing') !== false &&
        strpos($jenisLayanan, 'kucing') === false &&
        strpos($jenisLayanan, 'semua') === false;

    if (
        ($khususKucing && $jenisHewan !== 'kucing') ||
        ($khususAnjing && $jenisHewan !== 'anjing')
    ) {
        pesanGrooming('Jenis hewan tidak sesuai dengan layanan yang dipilih.');
        header('Location: grooming.php#booking');
        exit;
    }

    mysqli_begin_transaction($koneksi);

    try {
        $stmt = mysqli_prepare($koneksi, "
            SELECT id
            FROM jadwal_grooming
            WHERE id = ?
              AND status = 'Tersedia'
              AND tanggal >= CURDATE()
            FOR UPDATE
        ");

        if (!$stmt) {
            throw new Exception('Query pemeriksaan jadwal gagal.');
        }

        mysqli_stmt_bind_param($stmt, 'i', $jadwalId);
        mysqli_stmt_execute($stmt);
        $hasil = mysqli_stmt_get_result($stmt);
        $jadwal = mysqli_fetch_assoc($hasil);
        mysqli_stmt_close($stmt);

        if (!$jadwal) {
            throw new Exception(
                'Jadwal sudah dipesan atau tidak tersedia. Pilih jadwal lain.'
            );
        }

        $total = (float)$layanan['harga'];

        $stmt = mysqli_prepare($koneksi, "
            INSERT INTO booking_grooming
                (user_id, hewan_id, layanan_id, jadwal_id, total)
            VALUES (?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new Exception('Query booking gagal dibuat.');
        }

        mysqli_stmt_bind_param(
            $stmt,
            'iiiid',
            $userId,
            $hewanId,
            $layananId,
            $jadwalId,
            $total
        );

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new Exception('Booking gagal disimpan.');
        }

        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($koneksi, "
            UPDATE jadwal_grooming
            SET status = 'Dibooking'
            WHERE id = ? AND status = 'Tersedia'
        ");

        if (!$stmt) {
            throw new Exception('Query pembaruan jadwal gagal dibuat.');
        }

        mysqli_stmt_bind_param($stmt, 'i', $jadwalId);

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new Exception('Jadwal gagal diperbarui.');
        }

        $berubah = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($berubah !== 1) {
            throw new Exception('Jadwal tidak berhasil dipesan.');
        }

        mysqli_commit($koneksi);
        pesanGrooming('Booking grooming berhasil dibuat!', 'success');

    } catch (Throwable $ex) {
        mysqli_rollback($koneksi);
        pesanGrooming($ex->getMessage(), 'error');
    }

    header('Location: grooming.php#booking');
    exit;
}

/* =====================================
   PENCARIAN DAN DAFTAR LAYANAN
===================================== */
$cari = trim($_GET['cari'] ?? '');
$layananDipilih = (int)($_GET['layanan'] ?? 0);

if ($cari !== '') {
    $keyword = '%' . $cari . '%';

    $stmtLayanan = mysqli_prepare($koneksi, "
        SELECT *
        FROM layanan_grooming
        WHERE nama_layanan LIKE ?
           OR jenis_hewan LIKE ?
           OR deskripsi LIKE ?
        ORDER BY id DESC
    ");

    if ($stmtLayanan) {
        mysqli_stmt_bind_param(
            $stmtLayanan,
            'sss',
            $keyword,
            $keyword,
            $keyword
        );
    }
} else {
    $stmtLayanan = mysqli_prepare($koneksi, "
        SELECT *
        FROM layanan_grooming
        ORDER BY id DESC
    ");
}

$daftarLayanan = [];

if ($stmtLayanan) {
    mysqli_stmt_execute($stmtLayanan);
    $hasilLayanan = mysqli_stmt_get_result($stmtLayanan);

    while ($data = mysqli_fetch_assoc($hasilLayanan)) {
        $daftarLayanan[] = $data;
    }

    mysqli_stmt_close($stmtLayanan);
}

/* =====================================
   DATA HEWAN PELANGGAN
===================================== */
$daftarHewan = [];

if ($isPelanggan) {
    $stmtHewan = mysqli_prepare($koneksi, "
        SELECT id, jenis, ras, umur
        FROM hewan
        WHERE user_id = ?
        ORDER BY id DESC
    ");

    if ($stmtHewan) {
        mysqli_stmt_bind_param($stmtHewan, 'i', $userId);
        mysqli_stmt_execute($stmtHewan);
        $hasilHewan = mysqli_stmt_get_result($stmtHewan);

        while ($data = mysqli_fetch_assoc($hasilHewan)) {
            $daftarHewan[] = $data;
        }

        mysqli_stmt_close($stmtHewan);
    }
}

/* =====================================
   JADWAL TERSEDIA
===================================== */
$daftarJadwal = [];

$hasilJadwal = mysqli_query($koneksi, "
    SELECT id, tanggal, jam
    FROM jadwal_grooming
    WHERE status = 'Tersedia'
      AND tanggal >= CURDATE()
    ORDER BY tanggal ASC, jam ASC
");

if ($hasilJadwal) {
    while ($data = mysqli_fetch_assoc($hasilJadwal)) {
        $daftarJadwal[] = $data;
    }
}

/* =====================================
   RIWAYAT BOOKING PELANGGAN
===================================== */
$riwayat = [];

if ($isPelanggan) {
    $stmtRiwayat = mysqli_prepare($koneksi, "
        SELECT
            b.id AS booking_id,
            b.total,
            b.status AS status_booking,
            lg.nama_layanan,
            j.tanggal,
            j.jam,
            h.jenis
        FROM booking_grooming b
        LEFT JOIN layanan_grooming lg ON b.layanan_id = lg.id
        LEFT JOIN jadwal_grooming j ON b.jadwal_id = j.id
        LEFT JOIN hewan h ON b.hewan_id = h.id
        WHERE b.user_id = ?
        ORDER BY b.id DESC
    ");

    if ($stmtRiwayat) {
        mysqli_stmt_bind_param($stmtRiwayat, 'i', $userId);
        mysqli_stmt_execute($stmtRiwayat);
        $hasilRiwayat = mysqli_stmt_get_result($stmtRiwayat);

        while ($data = mysqli_fetch_assoc($hasilRiwayat)) {
            $riwayat[] = $data;
        }

        mysqli_stmt_close($stmtRiwayat);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grooming | PetShop</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="navbar">
    <a href="index.php" class="brand">
        <span class="brand-paw">🐾</span>
        <span>
            <strong>Pet<span class="brand-orange">Shop</span></strong>
            <small>Happy Pets, Happy Life</small>
        </span>
    </a>

    <button type="button" class="menu-toggle" id="menuToggle"
            aria-label="Buka menu" aria-expanded="false">☰</button>

    <nav class="nav-menu" id="navMenu">
        <a href="index.php" class="nav-link">Beranda</a>
        <a href="produk.php" class="nav-link">Produk</a>
        <a href="index.php#kategori" class="nav-link">Kategori</a>
        <a href="grooming.php" class="nav-link active">Grooming</a>

        <?php if ($isPelanggan): ?>
            <a href="riwayat_pesanan.php" class="nav-link">Riwayat Pesanan</a>
        <?php endif; ?>

        <a href="index.php#tentang" class="nav-link">Tentang Kami</a>
    </nav>

    <div class="nav-actions">
        <?php if ($isPelanggan): ?>
            <a href="keranjang.php" class="cart-link"
               aria-label="Keranjang, <?= $totalKeranjang ?> barang">
                <span class="cart-icon">🛒</span>
                <?php if ($totalKeranjang > 0): ?>
                    <span class="cart-count">
                        <?= $totalKeranjang > 99 ? '99+' : $totalKeranjang ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if ($sudahLogin): ?>
            <span class="welcome-user">Hai, <?= e($namaPengguna) ?>!</span>

            <?php if ($isAdmin): ?>
                <a href="admin/dashboard.php" class="btn-small">Dashboard</a>
            <?php endif; ?>

            <a href="logout.php" class="btn-small">Logout</a>
        <?php else: ?>
            <a href="login.php" class="login-link">Login</a>
            <a href="register.php" class="btn-small">Daftar</a>
        <?php endif; ?>
    </div>
</header>

<main class="catalog">

    <!-- BANNER GROOMING -->
    <section class="catalog-banner">
        <div class="eyebrow">🐾 PERAWATAN HEWAN KESAYANGAN</div>
        <h1>Grooming untuk Si Manis ♡</h1>
        <p>
            Bantu hewan kesayangan tetap bersih, sehat, dan nyaman
            dengan layanan grooming pilihan.
        </p>
    </section>

    <?php if ($pesan !== ''): ?>
        <div class="grooming-alert <?= e($tipePesan) ?>">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <!-- DAFTAR LAYANAN: MENGGUNAKAN STYLE KARTU PRODUK -->
    <section id="daftar-layanan">
        <div class="section-heading">
            <div>
                <p class="eyebrow">♡ RAWAT SI SAHABAT BERBULU</p>
                <h2>Pilihan Layanan Grooming</h2>
                <p>Pilih perawatan yang sesuai dengan kebutuhan hewan kesayanganmu.</p>
            </div>
        </div>

        <div class="catalog-tools">
            <form method="GET" action="grooming.php" class="catalog-search">
                <input
                    type="search"
                    name="cari"
                    value="<?= e($cari) ?>"
                    placeholder="Cari layanan atau jenis hewan..."
                    aria-label="Cari layanan grooming"
                >
                <button type="submit">Cari</button>
            </form>

            <?php if ($cari !== ''): ?>
                <a href="grooming.php" class="catalog-reset">Lihat Semua</a>
            <?php endif; ?>
        </div>

        <p class="catalog-count">
            Menampilkan <strong><?= count($daftarLayanan) ?></strong>
            layanan grooming
            <?php if ($cari !== ''): ?>
                untuk pencarian <strong>"<?= e($cari) ?>"</strong>
            <?php endif; ?>
        </p>

        <?php if (count($daftarLayanan) > 0): ?>
            <div class="product-grid">
                <?php foreach ($daftarLayanan as $layanan): ?>
                    <?php
                    $jenisIkon = strtolower(trim($layanan['jenis_hewan'] ?? ''));

                    if (
                        strpos($jenisIkon, 'anjing') !== false &&
                        strpos($jenisIkon, 'kucing') === false
                    ) {
                        $ikon = '🐶';
                    } elseif (
                        strpos($jenisIkon, 'semua') !== false ||
                        (
                            strpos($jenisIkon, 'anjing') !== false &&
                            strpos($jenisIkon, 'kucing') !== false
                        )
                    ) {
                        $ikon = '🐶🐱';
                    } else {
                        $ikon = '🐱';
                    }
                    ?>

                    <article class="product-card">
                        <a
                            href="detail_grooming.php?id=<?= (int)$layanan['id'] ?>"
                            class="product-picture"
                            aria-label="Lihat detail <?= e($layanan['nama_layanan']) ?>"
                        >
                            <div class="product-fallback"><?= $ikon ?></div>
                            <span class="picture-heart">♡</span>
                        </a>

                        <div class="product-details">
                            <span class="product-category">
                                <?= e($layanan['jenis_hewan'] ?? 'Semua Hewan') ?>
                            </span>

                            <h3>
                                <a href="detail_grooming.php?id=<?= (int)$layanan['id'] ?>">
                                    <?= e($layanan['nama_layanan']) ?>
                                </a>
                            </h3>

                            <div class="product-rating">
                                <span>★</span>
                                Perawatan hewan kesayangan
                            </div>

                            <p style="color:#76675d;font-size:14px;line-height:1.8;margin-bottom:14px;overflow-wrap:anywhere;">
                                <?= e(
                                    $layanan['deskripsi']
                                    ?: 'Perawatan untuk menjaga kebersihan dan kenyamanan hewan.'
                                ) ?>
                            </p>

                            <div class="product-bottom">
                                <span class="product-price">
                                    <?= rupiah($layanan['harga']) ?>
                                </span>
                                <span class="product-stock">
                                    ⏱ <?= e($layanan['durasi'] ?? '-') ?> menit
                                </span>
                            </div>

                            <a
                                href="detail_grooming.php?id=<?= (int)$layanan['id'] ?>"
                                class="btn-add-cart"
                            >
                                ♡ Lihat Detail
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="catalog-empty">
                <span>🔎</span>
                <h2>Layanan Belum Ditemukan</h2>
                <p>Coba gunakan kata kunci pencarian yang berbeda.</p>
                <a href="grooming.php" class="catalog-reset">Lihat Semua Layanan</a>
            </div>
        <?php endif; ?>
    </section>

    <!-- DATA HEWAN -->
    <section class="grooming-block" id="data-hewan" style="margin-top:40px;">
        <h2>🐱 Data Hewan Peliharaan</h2>
        <p class="grooming-description">
            Tambahkan jenis hewan untuk keperluan booking. Nama hewan tidak perlu diisi.
        </p>

        <?php if ($isPelanggan): ?>
            <form method="POST" action="grooming.php#data-hewan" class="grooming-form">
                <input type="hidden" name="aksi" value="tambah_hewan">

                <div class="grooming-row">
                    <div>
                        <label for="jenis_hewan_baru">Jenis Hewan *</label>
                        <select name="jenis" id="jenis_hewan_baru" required>
                            <option value="">-- Pilih jenis hewan --</option>
                            <option value="Kucing">Kucing</option>
                            <option value="Anjing">Anjing</option>
                        </select>
                    </div>

                    <div>
                        <label for="ras">Ras (opsional)</label>
                        <input type="text" name="ras" id="ras" placeholder="Contoh: Persia">
                    </div>
                </div>

                <div class="grooming-row">
                    <div>
                        <label for="umur">Umur (opsional)</label>
                        <input type="number" name="umur" id="umur" min="0" max="100"
                               placeholder="Umur dalam tahun">
                    </div>

                    <div>
                        <label for="jenis_kelamin">Jenis Kelamin (opsional)</label>
                        <select name="jenis_kelamin" id="jenis_kelamin">
                            <option value="">Tidak diisi</option>
                            <option value="Jantan">Jantan</option>
                            <option value="Betina">Betina</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="catatan">Catatan (opsional)</label>
                    <textarea name="catatan" id="catatan"
                              placeholder="Catatan tambahan jika diperlukan"></textarea>
                </div>

                <button type="submit" class="grooming-btn">+ Simpan Data Hewan</button>
            </form>
        <?php else: ?>
            <div class="grooming-empty">
                <span>🔐</span>
                <strong>Login sebagai pelanggan terlebih dahulu.</strong>
                <p>Login diperlukan untuk menyimpan data hewan peliharaan.</p>
                <a href="login.php" class="grooming-btn">Login Sekarang</a>
            </div>
        <?php endif; ?>
    </section>

    <!-- BOOKING GROOMING -->
    <section class="grooming-block" id="booking">
        <h2>📅 Booking Grooming</h2>
        <p class="grooming-description">
            Lengkapi pilihan layanan, hewan, dan jadwal yang tersedia.
        </p>

        <?php if ($isPelanggan): ?>
            <form method="POST" action="grooming.php#booking"
                  class="grooming-form" id="formBooking">

                <input type="hidden" name="aksi" value="booking">

                <div>
                    <label for="layanan_id">Layanan Grooming *</label>
                    <select name="layanan_id" id="layanan_id" required>
                        <option value="">-- Pilih layanan grooming --</option>
                        <?php foreach ($daftarLayanan as $layanan): ?>
                            <option
                                value="<?= (int)$layanan['id'] ?>"
                                data-jenis="<?= e($layanan['jenis_hewan']) ?>"
                                <?= $layananDipilih === (int)$layanan['id'] ? 'selected' : '' ?>
                            >
                                <?= e($layanan['nama_layanan']) ?>
                                — <?= rupiah($layanan['harga']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="grooming-info" id="infoLayanan"></p>
                </div>

                <div>
                    <label for="hewan_id">Jenis Hewan Peliharaan *</label>
                    <select name="hewan_id" id="hewan_id" required>
                        <option value="">-- Pilih hewan --</option>
                        <?php foreach ($daftarHewan as $hewan): ?>
                            <option
                                value="<?= (int)$hewan['id'] ?>"
                                data-jenis="<?= e($hewan['jenis']) ?>"
                            >
                                <?= e($hewan['jenis']) ?>
                                <?= !empty($hewan['ras']) ? ' - ' . e($hewan['ras']) : '' ?>
                                <?= $hewan['umur'] !== null && $hewan['umur'] !== ''
                                    ? ' (' . e($hewan['umur']) . ' tahun)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (count($daftarHewan) === 0): ?>
                        <p class="grooming-notice">
                            Tambahkan data hewan terlebih dahulu sebelum booking.
                        </p>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="jadwal_id">Tanggal dan Jam *</label>
                    <select name="jadwal_id" id="jadwal_id" required>
                        <option value="">-- Pilih jadwal tersedia --</option>
                        <?php foreach ($daftarJadwal as $jadwal): ?>
                            <option value="<?= (int)$jadwal['id'] ?>">
                                <?= e(date('d-m-Y', strtotime($jadwal['tanggal']))) ?>
                                — <?= e(substr($jadwal['jam'], 0, 5)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (count($daftarJadwal) === 0): ?>
                        <p class="grooming-notice">
                            Belum ada jadwal tersedia. Silakan hubungi admin.
                        </p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="grooming-btn"
                    <?= count($daftarHewan) === 0 ||
                        count($daftarJadwal) === 0 ||
                        count($daftarLayanan) === 0 ? 'disabled' : '' ?>>
                    Konfirmasi Booking
                </button>
            </form>
        <?php else: ?>
            <div class="grooming-empty">
                <span>🔐</span>
                <strong>Login sebagai pelanggan untuk melakukan booking.</strong>
                <p>Setelah login, kamu bisa memilih layanan dan jadwal grooming.</p>
                <a href="login.php" class="grooming-btn">Login Sekarang</a>
            </div>
        <?php endif; ?>
    </section>

    <!-- RIWAYAT BOOKING -->
    <section class="grooming-block" id="riwayat">
        <h2>📋 Riwayat Booking</h2>
        <p class="grooming-description">
            Daftar pemesanan layanan grooming yang pernah kamu lakukan.
        </p>

        <?php if (!$isPelanggan): ?>
            <div class="grooming-empty">
                <span>🔐</span>
                <strong>Login sebagai pelanggan untuk melihat riwayat booking.</strong>
                <p>Riwayat booking hanya tersedia untuk akun pelanggan.</p>
                <a href="login.php" class="grooming-btn">Login Sekarang</a>
            </div>
        <?php elseif (count($riwayat) > 0): ?>
            <div class="grooming-table-wrap">
                <table class="grooming-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Layanan</th>
                            <th>Jenis Hewan</th>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat as $index => $booking): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= e($booking['nama_layanan'] ?? '-') ?></td>
                                <td><?= e($booking['jenis'] ?? '-') ?></td>
                                <td>
                                    <?= !empty($booking['tanggal'])
                                        ? e(date('d-m-Y', strtotime($booking['tanggal'])))
                                        : '-' ?>
                                </td>
                                <td>
                                    <?= !empty($booking['jam'])
                                        ? e(substr($booking['jam'], 0, 5))
                                        : '-' ?>
                                </td>
                                <td><?= rupiah($booking['total']) ?></td>
                                <td><?= e($booking['status_booking'] ?? 'Diproses') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="grooming-empty">
                <span>🐾</span>
                <strong>Belum ada riwayat booking.</strong>
                <p>Booking grooming pertamamu akan muncul di sini.</p>
            </div>
        <?php endif; ?>
    </section>

</main>

<footer class="footer">
    <div class="footer-main">
        <div class="footer-brand">
            <a href="index.php" class="brand footer-logo">
                <span class="brand-paw">🐾</span>
                <span>
                    <strong>Pet<span class="brand-orange">Shop</span></strong>
                    <small>Happy Pets, Happy Life</small>
                </span>
            </a>
            <p>
                Karena mereka juga bagian dari keluarga.
                PetShop menyediakan makanan, perlengkapan, dan layanan perawatan
                terbaik untuk menjaga hewan kesayanganmu tetap sehat dan bahagia. ♡
            </p>
        </div>

        <div class="footer-column">
            <h3>Menu Utama</h3>
            <a href="index.php">Beranda</a>
            <a href="produk.php">Produk</a>
            <a href="index.php#kategori">Kategori</a>
            <a href="grooming.php">Grooming</a>
            <?php if ($isPelanggan): ?>
                <a href="riwayat_pesanan.php">Riwayat Pesanan</a>
            <?php endif; ?>
            <a href="index.php#tentang">Tentang Kami</a>
        </div>

        <div class="footer-column">
            <h3>Akun Saya</h3>
            <?php if ($sudahLogin): ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Buat Akun</a>
            <?php endif; ?>

            <?php if ($isPelanggan): ?>
                <a href="keranjang.php">Keranjang</a>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <a href="admin/dashboard.php">Dashboard Admin</a>
            <?php endif; ?>
        </div>

        <div class="footer-illustration">
            <span>🐶</span>
            <span>🐱</span>
            <p>Happy Pets,<br>Happy Life! ♡</p>
        </div>
    </div>

    <div class="footer-bottom">
        <span>© <?= date('Y') ?> PetShop. All rights reserved.</span>
        <span>Dibuat dengan ♡ untuk sahabat berbulu.</span>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.getElementById('menuToggle');
    const navMenu = document.getElementById('navMenu');

    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', function () {
            const terbuka = navMenu.classList.toggle('show');
            menuToggle.setAttribute(
                'aria-expanded',
                terbuka ? 'true' : 'false'
            );
        });

        navMenu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                navMenu.classList.remove('show');
                menuToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    const selectLayanan = document.getElementById('layanan_id');
    const selectHewan = document.getElementById('hewan_id');
    const infoLayanan = document.getElementById('infoLayanan');

    if (!selectLayanan || !selectHewan || !infoLayanan) return;

    function cocokDenganHewan(jenisLayanan, jenisHewan) {
        const layanan = (jenisLayanan || '').toLowerCase();
        const hewan = (jenisHewan || '').toLowerCase();

        if (layanan.includes('semua')) return true;

        const khususKucing =
            layanan.includes('kucing') && !layanan.includes('anjing');

        const khususAnjing =
            layanan.includes('anjing') && !layanan.includes('kucing');

        if (khususKucing) return hewan === 'kucing';
        if (khususAnjing) return hewan === 'anjing';

        return true;
    }

    function pilihHewanYangCocok(jenisLayanan) {
        const pilihan = Array.from(selectHewan.options).find(function (option) {
            return option.value !== '' &&
                cocokDenganHewan(jenisLayanan, option.dataset.jenis);
        });

        selectHewan.value = pilihan ? pilihan.value : '';
    }

    function tampilkanInfoLayanan() {
        const option = selectLayanan.options[selectLayanan.selectedIndex];

        infoLayanan.textContent =
            option && option.value !== ''
                ? 'Layanan dipilih: ' + option.text.trim()
                : '';
    }

    selectLayanan.addEventListener('change', function () {
        const option = selectLayanan.options[selectLayanan.selectedIndex];

        tampilkanInfoLayanan();

        if (option && option.value !== '') {
            pilihHewanYangCocok(option.dataset.jenis);
        } else {
            selectHewan.value = '';
        }
    });

    const params = new URLSearchParams(window.location.search);
    const idDariURL = params.get('layanan');

    if (idDariURL) {
        selectLayanan.value = idDariURL;

        const option = selectLayanan.options[selectLayanan.selectedIndex];

        if (option && option.value !== '') {
            pilihHewanYangCocok(option.dataset.jenis);
        }
    }

    tampilkanInfoLayanan();
});
</script>

</body>
</html>