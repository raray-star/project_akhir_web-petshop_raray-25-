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

function statusClass($status) {
    $status = strtolower(trim((string)$status));

    if (in_array($status, [
        'lunas', 'selesai', 'berhasil', 'dibayar',
        'dikonfirmasi', 'dikonfirmasi admin'
    ])) {
        return 'status-success';
    }

    if (in_array($status, [
        'dibatalkan', 'batal', 'gagal'
    ])) {
        return 'status-danger';
    }

    return 'status-pending';
}

// Harus login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$namaPengguna = $_SESSION['nama'] ?? '';
$role = $_SESSION['role'] ?? '';
$isPelanggan = $role === 'pelanggan';

// Halaman ini hanya untuk pelanggan
if (!$isPelanggan) {
    header('Location: admin/dashboard.php');
    exit;
}

// Ambil kategori
$kategori = mysqli_query($koneksi, "
    SELECT * FROM kategori
    ORDER BY id ASC
");

// Hitung jumlah keranjang
$jumlahKeranjang = 0;

$stmtKeranjang = mysqli_prepare($koneksi, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM keranjang
    WHERE user_id = ?
");

if ($stmtKeranjang) {
    mysqli_stmt_bind_param($stmtKeranjang, 'i', $userId);
    mysqli_stmt_execute($stmtKeranjang);

    $hasilKeranjang = mysqli_stmt_get_result($stmtKeranjang);
    $dataKeranjang = mysqli_fetch_assoc($hasilKeranjang);

    $jumlahKeranjang = (int) ($dataKeranjang['total'] ?? 0);

    mysqli_stmt_close($stmtKeranjang);
}

// ==========================================
// RIWAYAT PESANAN PRODUK
// ==========================================

$stmtPesanan = mysqli_prepare($koneksi, "
    SELECT *
    FROM pesanan
    WHERE user_id = ?
    ORDER BY id DESC
");

if (!$stmtPesanan) {
    die('Gagal mengambil riwayat pesanan.');
}

mysqli_stmt_bind_param($stmtPesanan, 'i', $userId);
mysqli_stmt_execute($stmtPesanan);

$pesanan = mysqli_stmt_get_result($stmtPesanan);

// ==========================================
// RIWAYAT BOOKING GROOMING
// ==========================================
//
// Query ini mengasumsikan:
// booking_grooming memiliki kolom
// user_id, layanan_id, jadwal_id, hewan_id,
// total, status, dan created_at.
//
// Jika struktur tabel berbeda, nama kolom perlu disesuaikan.

$stmtGrooming = mysqli_prepare($koneksi, "
    SELECT
        b.id,
        b.total,
        b.status,
        b.created_at,
        lg.nama_layanan,
        j.tanggal,
        j.jam,
        h.jenis AS jenis_hewan
    FROM booking_grooming b
    LEFT JOIN layanan_grooming lg
        ON b.layanan_id = lg.id
    LEFT JOIN jadwal_grooming j
        ON b.jadwal_id = j.id
    LEFT JOIN hewan h
        ON b.hewan_id = h.id
    WHERE b.user_id = ?
    ORDER BY b.id DESC
");

if (!$stmtGrooming) {
    die(
        'Gagal mengambil riwayat grooming. Periksa nama tabel dan kolom database.'
    );
}

mysqli_stmt_bind_param($stmtGrooming, 'i', $userId);
mysqli_stmt_execute($stmtGrooming);

$riwayatGrooming = mysqli_stmt_get_result($stmtGrooming);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Riwayat Pesanan | PetShop</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .riwayat-page {
            padding: 45px 6% 65px;
            min-height: 65vh;
            font-family: 'Nunito', sans-serif;
            color: #76675d;
            box-sizing: border-box;
        }

        .riwayat-title {
            padding: 35px;
            margin-bottom: 30px;
            border-radius: 22px;
            background: linear-gradient(120deg, #fff0dc, #fff8ee);
            text-align: center;
        }

        .riwayat-title .eyebrow {
            color: #a86458;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .riwayat-title h1 {
            margin: 10px 0;
            color: #70432e;
            font-size: 30px;
            font-weight: 900;
            line-height: 1.4;
        }

        .riwayat-title p {
            margin-bottom: 0;
            color: #827267;
            font-size: 15px;
            line-height: 1.8;
        }

        .riwayat-block {
            padding: 28px;
            margin-bottom: 28px;
            border: 1px solid #f0e5d9;
            border-radius: 22px;
            background: #fff;
            box-sizing: border-box;
        }

        .riwayat-block h2 {
            margin-bottom: 10px;
            color: #70432e;
            font-size: 23px;
            font-weight: 900;
        }

        .riwayat-block > p {
            color: #76675d;
            font-size: 14px;
            line-height: 1.8;
        }

        .riwayat-list {
            display: grid;
            gap: 18px;
        }

        .riwayat-card {
            padding: 22px;
            border: 1px solid #f0e5d9;
            border-radius: 18px;
            background: #fff8ee;
        }

        .riwayat-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0e5d9;
        }

        .riwayat-card-top h3 {
            margin: 0 0 5px;
            color: #70432e;
            font-size: 17px;
            font-weight: 900;
            overflow-wrap: anywhere;
        }

        .riwayat-card-top p {
            margin: 0;
            color: #827267;
            font-size: 13px;
        }

        .riwayat-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
        }

        .status-success {
            background: #e7f5e6;
            color: #36723b;
        }

        .status-pending {
            background: #fff0dc;
            color: #8a572d;
        }

        .status-danger {
            background: #ffeded;
            color: #a13e31;
        }

        .riwayat-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            padding: 18px 0;
        }

        .riwayat-info small {
            display: block;
            margin-bottom: 5px;
            color: #827267;
            font-size: 12px;
        }

        .riwayat-info strong {
            color: #403129;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .riwayat-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 15px;
            border-top: 1px solid #f0e5d9;
        }

        .riwayat-total span {
            color: #76675d;
            font-size: 14px;
        }

        .riwayat-total strong {
            color: #e97e35;
            font-size: 21px;
            font-weight: 900;
        }

        .riwayat-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 17px;
        }

        .riwayat-page .grooming-btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            box-sizing: border-box;
            padding: 11px 18px;
            border: none;
            border-radius: 30px;
            background: #70432e;
            color: #fff;
            font-family: inherit;
            font-size: 13px;
            font-weight: 900;
            text-decoration: none;
            cursor: pointer;
            transition: .2s;
        }

        .riwayat-page .grooming-btn:hover {
            background: #e97e35;
            color: #fff;
        }

        .riwayat-page .grooming-btn.outline {
            border: 1px solid #eaded2;
            background: #fff;
            color: #70432e;
        }

        .riwayat-page .grooming-btn.outline:hover {
            border-color: #e97e35;
            color: #e97e35;
        }

        .riwayat-actions form {
            margin: 0;
        }

        .riwayat-empty {
            padding: 35px 20px;
            border-radius: 18px;
            background: #fff8ee;
            color: #76675d;
            text-align: center;
            font-size: 14px;
            line-height: 1.8;
        }

        .riwayat-empty > span {
            display: block;
            margin-bottom: 10px;
            font-size: 45px;
        }

        .riwayat-empty h3 {
            margin: 0 0 8px;
            color: #70432e;
            font-size: 20px;
        }

        .riwayat-empty p {
            margin: 0 0 17px;
        }

        .riwayat-page .section-icon {
            margin-right: 5px;
        }

        @media (max-width: 760px) {
            .riwayat-page {
                padding: 28px 5% 45px;
            }

            .riwayat-title {
                padding: 25px 20px;
            }

            .riwayat-title h1 {
                font-size: 25px;
            }

            .riwayat-title p {
                font-size: 14px;
            }

            .riwayat-block {
                padding: 20px;
            }

            .riwayat-info {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .riwayat-card {
                padding: 17px;
            }
        }
    </style>
</head>

<body>

<!-- NAVBAR -->
<header class="navbar">

    <a href="index.php" class="brand">
        <span class="brand-paw">🐾</span>

        <span>
            <strong>Pet<span class="brand-orange">Shop</span></strong>
            <small>Happy Pets, Happy Life</small>
        </span>
    </a>

    <button
        class="menu-toggle"
        id="menuToggle"
        type="button"
        aria-label="Buka menu"
        aria-expanded="false"
    >☰</button>

    <nav class="nav-menu" id="navMenu">
        <a href="index.php" class="nav-link">Beranda</a>
        <a href="produk.php" class="nav-link">Produk</a>
        <a href="index.php#kategori" class="nav-link">Kategori</a>
        <a href="grooming.php" class="nav-link">Grooming</a>
        <a href="riwayat_pesanan.php" class="nav-link active">Riwayat Pesanan</a>
        <a href="index.php#tentang" class="nav-link">Tentang Kami</a>
    </nav>

    <div class="nav-actions">

        <a href="keranjang.php" class="cart-link" aria-label="Keranjang">
            🛒
            <?php if ($jumlahKeranjang > 0): ?>
                <span class="cart-count"><?= $jumlahKeranjang ?></span>
            <?php endif; ?>
        </a>

        <span class="welcome-user">
            Hai, <?= e($namaPengguna) ?>!
        </span>

        <a href="logout.php" class="btn-small">Logout</a>

    </div>

</header>

<main class="riwayat-page">

    <!-- JUDUL -->
    <section class="riwayat-title">
        <div class="eyebrow">🐾 PESANANMU</div>
        <h1>Riwayat Pesanan ♡</h1>
        <p>
            Pantau pembelian produk dan jadwal grooming
            hewan kesayanganmu di satu tempat.
        </p>
    </section>

    <!-- RIWAYAT PESANAN PRODUK -->
    <section class="riwayat-block">

        <h2>Pesanan Produk 🛍️</h2>

        <p>
            Berikut daftar transaksi produk yang pernah kamu lakukan.
        </p>

        <div class="riwayat-list">

            <?php if (mysqli_num_rows($pesanan) > 0): ?>

                <?php while ($p = mysqli_fetch_assoc($pesanan)): ?>

                    <?php
                    $statusPesanan = $p['status'] ?? 'Diproses';
                    $statusPembayaran = $p['status_pembayaran'] ?? 'Belum Dibayar';
                    $metodePembayaran = $p['metode_pembayaran'] ?? '-';
                    $totalPesanan = $p['total'] ?? 0;
                    $tanggalPesanan = $p['tanggal'] ?? '';
                    ?>

                    <article class="riwayat-card">

                        <div class="riwayat-card-top">
                            <div>
                                <h3>Pesanan #<?= (int)$p['id'] ?></h3>

                                <p>
                                    <?= $tanggalPesanan !== ''
                                        ? e(date('d-m-Y H:i', strtotime($tanggalPesanan)))
                                        : 'Tanggal tidak tersedia' ?>
                                </p>
                            </div>

                            <span class="riwayat-status <?= e(statusClass($statusPesanan)) ?>">
                                <?= e($statusPesanan) ?>
                            </span>
                        </div>

                        <div class="riwayat-info">

                            <div>
                                <small>Nama Penerima</small>
                                <strong>
                                    <?= e($p['nama_penerima'] ?? $namaPengguna) ?>
                                </strong>
                            </div>

                            <div>
                                <small>Metode Pembayaran</small>
                                <strong><?= e($metodePembayaran) ?></strong>
                            </div>

                            <div>
                                <small>Status Pembayaran</small>
                                <strong><?= e($statusPembayaran) ?></strong>
                            </div>

                            <div>
                                <small>Nomor Pesanan</small>
                                <strong>#<?= (int)$p['id'] ?></strong>
                            </div>

                        </div>

                        <div class="riwayat-total">
                            <span>Total Pesanan</span>
                            <strong><?= rupiah($totalPesanan) ?></strong>
                        </div>

                        <div class="riwayat-actions">

                            <?php
                            $statusBayar = strtolower(trim((string)$statusPembayaran));
                            $statusOrder = strtolower(trim((string)$statusPesanan));

                            $belumBayar =
                                !in_array($statusBayar, [
                                    'lunas', 'dibayar', 'berhasil', 'paid'
                                ]) &&
                                !in_array($statusOrder, [
                                    'dibatalkan', 'batal', 'gagal'
                                ]);
                            ?>

                            <?php if (
                                strtolower(trim((string)$metodePembayaran)) === 'qris'
                                && $belumBayar
                            ): ?>

                                <a
                                    href="pembayaran.php?id=<?= (int)$p['id'] ?>"
                                    class="grooming-btn"
                                >
                                    Bayar Sekarang
                                </a>

                            <?php endif; ?>

                            <a
                                href="detail_pesanan.php?id=<?= (int)$p['id'] ?>"
                                class="grooming-btn outline"
                            >
                                Detail Pesanan
                            </a>

                            <form
                                action="hapus_riwayat.php"
                                method="POST"
                                onsubmit="return confirm('Yakin ingin menghapus riwayat pesanan ini?')"
                            >
                                <input
                                    type="hidden"
                                    name="pesanan_id"
                                    value="<?= (int)$p['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="grooming-btn outline"
                                >
                                    Hapus Riwayat
                                </button>
                            </form>

                        </div>

                    </article>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="riwayat-empty">
                    <span>🛍️</span>
                    <h3>Belum Ada Pesanan Produk</h3>

                    <p>
                        Kamu belum memiliki transaksi produk.
                        Yuk, cari kebutuhan hewan kesayanganmu!
                    </p>

                    <a href="produk.php" class="grooming-btn">
                        Mulai Belanja →
                    </a>
                </div>

            <?php endif; ?>

        </div>
    </section>

    <!-- RIWAYAT BOOKING GROOMING -->
    <section class="riwayat-block">

        <h2>Booking Grooming Saya 🐶</h2>

        <p>
            Berikut daftar layanan grooming yang pernah kamu pesan.
        </p>

        <div class="riwayat-list">

            <?php if (mysqli_num_rows($riwayatGrooming) > 0): ?>

                <?php while ($g = mysqli_fetch_assoc($riwayatGrooming)): ?>

                    <?php
                    $statusGrooming = $g['status'] ?? 'Menunggu Konfirmasi';
                    ?>

                    <article class="riwayat-card">

                        <div class="riwayat-card-top">

                            <div>
                                <h3>
                                    Booking Grooming #<?= (int)$g['id'] ?>
                                </h3>

                                <p>
                                    Dipesan:
                                    <?= !empty($g['created_at'])
                                        ? e(date(
                                            'd-m-Y H:i',
                                            strtotime($g['created_at'])
                                        ))
                                        : 'Tanggal tidak tersedia' ?>
                                </p>
                            </div>

                            <span class="riwayat-status <?= e(statusClass($statusGrooming)) ?>">
                                <?= e($statusGrooming) ?>
                            </span>

                        </div>

                        <div class="riwayat-info">

                            <div>
                                <small>Jenis Layanan</small>
                                <strong>
                                    <?= e($g['nama_layanan'] ?? 'Layanan Grooming') ?>
                                </strong>
                            </div>

                            <div>
                                <small>Jenis Hewan</small>
                                <strong>
                                    <?= e($g['jenis_hewan'] ?? '-') ?>
                                </strong>
                            </div>

                            <div>
                                <small>Tanggal Grooming</small>
                                <strong>
                                    <?= !empty($g['tanggal'])
                                        ? e(date(
                                            'd-m-Y',
                                            strtotime($g['tanggal'])
                                        ))
                                        : 'Belum ditentukan' ?>
                                </strong>
                            </div>

                            <div>
                                <small>Jam Grooming</small>
                                <strong>
                                    <?= !empty($g['jam'])
                                        ? e(date(
                                            'H:i',
                                            strtotime($g['jam'])
                                        )) . ' WIB'
                                        : 'Belum ditentukan' ?>
                                </strong>
                            </div>

                        </div>

                        <div class="riwayat-total">
                            <span>Total Biaya Grooming</span>

                            <strong>
                                <?= rupiah($g['total'] ?? 0) ?>
                            </strong>
                        </div>

                        <div class="riwayat-actions">

                            <a
                                href="grooming.php"
                                class="grooming-btn outline"
                            >
                                Lihat Layanan Grooming
                            </a>

                        </div>

                    </article>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="riwayat-empty">
                    <span>🐶</span>

                    <h3>Belum Ada Booking Grooming</h3>

                    <p>
                        Kamu belum memiliki jadwal grooming.
                        Yuk, pilih layanan perawatan untuk hewan kesayanganmu!
                    </p>

                    <a href="grooming.php" class="grooming-btn">
                        Pilih Layanan Grooming →
                    </a>
                </div>

            <?php endif; ?>

        </div>

    </section>

</main>

<!-- FOOTER -->
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
                PetShop menyediakan makanan, perlengkapan, dan layanan
                perawatan untuk menjaga hewan kesayanganmu tetap sehat
                dan bahagia. ♡
            </p>

        </div>

        <div class="footer-column">
            <h3>Menu Utama</h3>

            <a href="index.php">Beranda</a>
            <a href="produk.php">Produk</a>
            <a href="index.php#kategori">Kategori</a>
            <a href="grooming.php">Grooming</a>
            <a href="riwayat_pesanan.php">Riwayat Pesanan</a>
            <a href="index.php#tentang">Tentang Kami</a>
        </div>

        <div class="footer-column">
            <h3>Akun Saya</h3>

            <a href="logout.php">Logout</a>
            <a href="keranjang.php">Keranjang</a>
        </div>

        <div class="footer-illustration">
            <span>🐶</span>
            <span>🐱</span>

            <p>
                Happy Pets,<br>
                Happy Life! ♡
            </p>
        </div>

    </div>

    <div class="footer-bottom">
        <span>
            © <?= date('Y') ?> PetShop. All rights reserved.
        </span>

        <span>
            Dibuat dengan ♡ untuk sahabat berbulu.
        </span>
    </div>

</footer>

<script>
const menuToggle = document.getElementById('menuToggle');
const navMenu = document.getElementById('navMenu');

if (menuToggle && navMenu) {
    menuToggle.addEventListener('click', function () {
        const isOpen = navMenu.classList.toggle('show');

        menuToggle.setAttribute(
            'aria-expanded',
            isOpen ? 'true' : 'false'
        );
    });

    navMenu.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            navMenu.classList.remove('show');

            menuToggle.setAttribute(
                'aria-expanded',
                'false'
            );
        });
    });
}
</script>

</body>
</html>

<?php
mysqli_stmt_close($stmtPesanan);
mysqli_stmt_close($stmtGrooming);
?>