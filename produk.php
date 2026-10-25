<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/koneksi.php';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Path gambar produk
function gambarProduk($gambar) {
    $gambar = trim(str_replace('\\', '/', (string)($gambar ?? '')));

    if ($gambar === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $gambar)) {
        return $gambar;
    }

    if (strpos($gambar, 'assets/') === 0) {
        return $gambar;
    }

    return 'assets/img/produk/' . basename($gambar);
}

// Data pencarian
$cari = trim($_GET['cari'] ?? '');

$idKategori = filter_input(INPUT_GET, 'kategori', FILTER_VALIDATE_INT);

if (!$idKategori || $idKategori < 1) {
    $idKategori = 0;
}

// Ambil kategori
$queryKategori = mysqli_query(
    $koneksi,
    "SELECT * FROM kategori ORDER BY nama_kategori ASC"
);

// Filter produk
$where = [];

if ($cari !== '') {
    $kata = mysqli_real_escape_string($koneksi, $cari);
    $where[] = "p.nama_produk LIKE '%$kata%'";
}

if ($idKategori > 0) {
    $where[] = "p.kategori_id = $idKategori";
}

$sql = "
    SELECT p.*, k.nama_kategori
    FROM produk p
    LEFT JOIN kategori k ON p.kategori_id = k.id
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY p.id DESC";

$queryProduk = mysqli_query($koneksi, $sql);

// Data pengguna
$namaPengguna = $_SESSION['nama'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';

$isLogin = $userId > 0;
$isAdmin = $isLogin && $role === 'admin';
$isPelanggan = $isLogin && $role === 'pelanggan';

// Jumlah keranjang
$jumlahKeranjang = 0;

if ($isPelanggan) {
    $queryKeranjang = mysqli_query(
        $koneksi,
        "SELECT COALESCE(SUM(jumlah), 0) AS jumlah
         FROM keranjang
         WHERE user_id = $userId"
    );

    if ($queryKeranjang) {
        $dataKeranjang = mysqli_fetch_assoc($queryKeranjang);
        $jumlahKeranjang = (int)($dataKeranjang['jumlah'] ?? 0);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Produk | PetShop</title>

    <meta name="description"
          content="Temukan makanan, vitamin, aksesoris, dan perlengkapan hewan kesayanganmu.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="assets/css/style.css">
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

        <a href="produk.php" class="nav-link active">Produk</a>

        <a href="index.php#kategori" class="nav-link">Kategori</a>

        <a href="grooming.php" class="nav-link">Grooming</a>

        <?php if ($isPelanggan): ?>
            <a href="riwayat_pesanan.php" class="nav-link">
                Riwayat Pesanan
            </a>
        <?php endif; ?>

        <a href="index.php#tentang" class="nav-link">Tentang Kami</a>

    </nav>

    <!-- AKUN DAN KERANJANG -->
    <div class="nav-actions">

        <?php if ($isPelanggan): ?>
            <a href="keranjang.php"
               class="cart-link"
               aria-label="Keranjang">

                🛒

                <?php if ($jumlahKeranjang > 0): ?>
                    <span class="cart-count">
                        <?= $jumlahKeranjang ?>
                    </span>
                <?php endif; ?>

            </a>
        <?php endif; ?>

        <?php if ($isLogin): ?>

            <span class="welcome-user">
                Hai, <?= e($namaPengguna) ?>!
            </span>

            <?php if ($isAdmin): ?>
                <a href="admin/dashboard.php" class="btn-small">
                    Dashboard
                </a>
            <?php endif; ?>

            <a href="logout.php" class="btn-small">Logout</a>

        <?php else: ?>

            <a href="login.php" class="login-link">Login</a>

            <a href="register.php" class="btn-small">Daftar</a>

        <?php endif; ?>

    </div>

</header>

<main class="catalog">

    <!-- JUDUL PRODUK -->
    <section class="catalog-banner">

        <div class="eyebrow">🐾 TEMUKAN KEBUTUHAN MEREKA</div>

        <h1>Semua Produk PetShop ♡</h1>

        <p>
            Temukan makanan, snack, vitamin, aksesoris, dan perlengkapan
            untuk hewan kesayanganmu.
        </p>

    </section>

    <!-- PENCARIAN DAN FILTER KATEGORI -->
    <form class="catalog-tools" action="produk.php" method="GET">

        <div class="catalog-search">

            <input
                type="search"
                name="cari"
                placeholder="Cari nama produk..."
                value="<?= e($cari) ?>"
            >

            <button type="submit">Cari</button>

        </div>

        <select
            name="kategori"
            aria-label="Pilih kategori"
            onchange="this.form.submit()"
        >

            <option value="0">Semua Kategori</option>

            <?php if ($queryKategori): ?>
                <?php while ($k = mysqli_fetch_assoc($queryKategori)): ?>

                    <option
                        value="<?= (int)$k['id'] ?>"
                        <?= $idKategori === (int)$k['id'] ? 'selected' : '' ?>
                    >
                        <?= e($k['nama_kategori']) ?>
                    </option>

                <?php endwhile; ?>
            <?php endif; ?>

        </select>

    </form>

    <!-- JUMLAH PRODUK -->
    <p class="catalog-count">
        Produk yang ditemukan:
        <strong>
            <?= $queryProduk ? mysqli_num_rows($queryProduk) : 0 ?>
        </strong>
    </p>

    <!-- DAFTAR PRODUK -->
    <div class="product-grid">

        <?php if ($queryProduk && mysqli_num_rows($queryProduk) > 0): ?>

            <?php while ($p = mysqli_fetch_assoc($queryProduk)): ?>

                <?php
                $pathGambar = gambarProduk($p['gambar'] ?? '');
                ?>

                <article class="product-card">

                    <a
                        class="product-picture"
                        href="detail.php?id=<?= (int)$p['id'] ?>"
                    >

                        <?php if ($pathGambar !== ''): ?>

                            <img
                                src="<?= e($pathGambar) ?>"
                                alt="<?= e($p['nama_produk']) ?>"
                                loading="lazy"
                                onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                            >

                        <?php endif; ?>

                        <div
                            class="product-fallback"
                            style="<?= $pathGambar !== '' ? 'display:none' : 'display:flex' ?>"
                        >🐾</div>

                        <span class="picture-heart">♡</span>

                    </a>

                    <div class="product-details">

                        <span class="product-category">
                            <?= e($p['nama_kategori'] ?? 'Produk') ?>
                        </span>

                        <h3>
                            <a href="detail.php?id=<?= (int)$p['id'] ?>">
                                <?= e($p['nama_produk']) ?>
                            </a>
                        </h3>

                        <div class="product-rating">
                            <span>★</span> Produk pilihan
                        </div>

                        <div class="product-bottom">

                            <strong class="product-price">
                                Rp<?= number_format(
                                    (float)$p['harga'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </strong>

                            <span class="product-stock">
                                Stok <?= (int)$p['stok'] ?>
                            </span>

                        </div>

                        <a
                            href="detail.php?id=<?= (int)$p['id'] ?>"
                            class="btn-add-cart"
                        >
                            ♡ Lihat Produk
                        </a>

                    </div>

                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="catalog-empty">

                <span>🐱</span>

                <h2>Produk belum ditemukan</h2>

                <p>
                    Coba kata pencarian lain atau pilih semua kategori.
                    Jika belum ada produk, tambahkan dahulu ke database.
                </p>

                <a href="produk.php" class="catalog-reset">
                    Lihat Semua Produk
                </a>

            </div>

        <?php endif; ?>

    </div>

</main>

<!-- FOOTER SAMA SEPERTI INDEX -->
<footer class="footer">

    <div class="footer-main">

        <!-- IDENTITAS PETSHOP -->
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

        <!-- MENU UTAMA -->
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

        <!-- AKUN SAYA -->
        <div class="footer-column">

            <h3>Akun Saya</h3>

            <?php if ($isLogin): ?>

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

        <!-- ILUSTRASI -->
        <div class="footer-illustration">

            <span>🐶</span>
            <span>🐱</span>

            <p>
                Happy Pets,<br>
                Happy Life! ♡
            </p>

        </div>

    </div>

    <!-- COPYRIGHT -->
    <div class="footer-bottom">

        <span>
            © <?= date('Y') ?> PetShop. All rights reserved.
        </span>

        <span>
            Dibuat dengan ♡ untuk sahabat berbulu.
        </span>

    </div>

</footer>

<!-- MENU RESPONSIF -->
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