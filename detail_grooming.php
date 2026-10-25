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

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$layanan = null;

$namaPengguna = $_SESSION['nama'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';

if ($id && $id > 0) {
    $stmt = mysqli_prepare($koneksi, "
        SELECT *
        FROM layanan_grooming
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $hasil = mysqli_stmt_get_result($stmt);
        $layanan = mysqli_fetch_assoc($hasil);
        mysqli_stmt_close($stmt);
    }
}

if (!$layanan) {
    http_response_code(404);
}

$gambar = '';

if ($layanan && !empty($layanan['gambar'])) {
    $gambarTersimpan = str_replace(
        '\\',
        '/',
        $layanan['gambar']
    );

    if (preg_match('~^https?://~i', $gambarTersimpan)) {
        $gambar = $gambarTersimpan;
    } elseif (strpos($gambarTersimpan, 'assets/') === 0) {
        $gambar = $gambarTersimpan;
    } else {
        $gambar = 'assets/img/grooming/' . basename($gambarTersimpan);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Grooming | PetShop</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/detail.css">
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

        <a href="grooming.php" class="nav-link active">Grooming</a>

        <?php if ($role === 'pelanggan'): ?>
            <a href="riwayat_pesanan.php" class="nav-link">
                Riwayat Pesanan
            </a>
        <?php endif; ?>

        <a href="index.php#tentang" class="nav-link">Tentang Kami</a>
    </nav>

    <div class="nav-actions">

        <?php if ($role === 'pelanggan'): ?>
            <a href="keranjang.php" class="cart-link" aria-label="Keranjang">
                🛒
            </a>
        <?php endif; ?>

        <?php if ($userId > 0): ?>

            <span class="welcome-user">
                Hai, <?= e($namaPengguna) ?>!
            </span>

            <?php if ($role === 'admin'): ?>
                <a href="admin/dashboard.php" class="btn-small">
                    Dashboard
                </a>
            <?php endif; ?>

            <a href="logout.php" class="btn-small">Keluar</a>

        <?php else: ?>

            <a href="login.php" class="login-link">Login</a>
            <a href="register.php" class="btn-small">Daftar</a>

        <?php endif; ?>

    </div>
</header>

<!-- KONTEN DETAIL -->
<main class="detail-page">

    <div class="breadcrumb">
        <a href="index.php">Beranda</a> /
        <a href="grooming.php">Grooming</a> /
        Detail Grooming
    </div>

    <?php if (!$layanan): ?>

        <section class="detail-not-found">
            <span>🐾</span>

            <h1>Layanan tidak ditemukan</h1>

            <p>
                Layanan mungkin sudah dihapus atau tautannya tidak benar.
            </p>

            <a href="grooming.php" class="detail-back">
                ← Kembali ke Grooming
            </a>
        </section>

    <?php else: ?>

        <section class="detail-card">

            <!-- GAMBAR LAYANAN -->
            <div class="detail-picture">

                <?php if ($gambar !== ''): ?>

                    <img
                        src="<?= e($gambar) ?>"
                        alt="<?= e($layanan['nama_layanan']) ?>"
                        onerror="this.style.display='none';this.nextElementSibling.style.display='block';"
                    >

                    <div class="detail-placeholder" style="display:none">
                        <?php
                        $jenisHewan = strtolower(
                            trim((string)($layanan['jenis_hewan'] ?? ''))
                        );

                        echo $jenisHewan === 'anjing'
                            ? '🐶'
                            : ($jenisHewan === 'semua' ? '🐾' : '🐱');
                        ?>
                    </div>

                <?php else: ?>

                    <div class="detail-placeholder">
                        <?php
                        $jenisHewan = strtolower(
                            trim((string)($layanan['jenis_hewan'] ?? ''))
                        );

                        echo $jenisHewan === 'anjing'
                            ? '🐶'
                            : ($jenisHewan === 'semua' ? '🐾' : '🐱');
                        ?>
                    </div>

                <?php endif; ?>

            </div>

            <!-- INFORMASI LAYANAN -->
            <div class="detail-info">

                <span class="detail-category">
                    ✂️ Layanan Grooming
                </span>

                <h1><?= e($layanan['nama_layanan']) ?></h1>

                <div class="detail-price">
                    <?= rupiah($layanan['harga']) ?>
                </div>

                <div class="detail-extra">
                    <span>
                        🐾 Jenis hewan:
                        <?= e($layanan['jenis_hewan'] ?? 'Semua') ?>
                    </span>

                    <span>
                        ⏱ Durasi:
                        <?= (int)($layanan['durasi'] ?? 0) ?> menit
                    </span>
                </div>

                <div class="detail-description">
                    <?php if (!empty($layanan['deskripsi'])): ?>
                        <?= nl2br(e($layanan['deskripsi'])) ?>
                    <?php else: ?>
                        Layanan perawatan untuk membantu menjaga kebersihan
                        dan kenyamanan hewan kesayanganmu.
                    <?php endif; ?>
                </div>

                <a
                    class="detail-submit"
                    href="grooming.php?layanan=<?= (int)$layanan['id'] ?>#booking"
                >
                    🐾 Booking Sekarang
                </a>

                <a href="grooming.php" class="detail-back">
                    ← Kembali ke Grooming
                </a>

            </div>

        </section>

    <?php endif; ?>

</main>

<!-- FOOTER LENGKAP, DISESUAIKAN DENGAN detail.php -->
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

            <p>Karena mereka juga bagian dari keluarga. 
                PetShop menyediakan makanan, perlengkapan, dan layanan perawatan terbaik untuk menjaga hewan kesayanganmu tetap sehat dan bahagia. ♡
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

            <?php if ($userId > 0): ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Buat Akun</a>
            <?php endif; ?>

            <?php if ($role === 'pelanggan'): ?>
                <a href="keranjang.php">Keranjang</a>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
                <a href="admin/dashboard.php">Dashboard Admin</a>
            <?php endif; ?>
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
            menuToggle.setAttribute('aria-expanded', 'false');
        });
    });
}
</script>

</body>
</html>