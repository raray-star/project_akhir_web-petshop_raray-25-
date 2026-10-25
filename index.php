<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/koneksi.php';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Menentukan path gambar produk
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

// Ambil kategori
$kategori = mysqli_query($koneksi, "
    SELECT * FROM kategori
    ORDER BY id ASC
");

// Ambil 6 produk terbaru
$produk = mysqli_query($koneksi, "
    SELECT p.*, k.nama_kategori
    FROM produk p
    LEFT JOIN kategori k ON p.kategori_id = k.id
    ORDER BY p.id DESC
    LIMIT 6
");

// Data akun
$namaPengguna = $_SESSION['nama'] ?? '';
$role = $_SESSION['role'] ?? '';
$isLogin = isset($_SESSION['user_id']);
$isAdmin = $isLogin && $role === 'admin';
$isPelanggan = $isLogin && $role === 'pelanggan';

// Hitung isi keranjang
$jumlahKeranjang = 0;

if ($isPelanggan) {
    $uid = (int) $_SESSION['user_id'];

    $qKeranjang = mysqli_query($koneksi, "
        SELECT COALESCE(SUM(jumlah), 0) AS total
        FROM keranjang
        WHERE user_id = $uid
    ");

    if ($qKeranjang) {
        $dataKeranjang = mysqli_fetch_assoc($qKeranjang);
        $jumlahKeranjang = (int) ($dataKeranjang['total'] ?? 0);
    }
}

// Ikon kategori
$gambarKategori = [
    'makanan'      => '🍖',
    'snack'        => '🦴',
    'vitamin'      => '🧴',
    'pasir'        => '🪣',
    'shampo'       => '🧼',
    'mainan'       => '🎾',
    'aksesoris'    => '🦮',
    'tempat makan' => '🥣',
    'kandang'      => '🏠'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>PetShop — Happy Pets, Happy Life</title>

    <meta name="description"
          content="Temukan makanan, vitamin, aksesoris, dan layanan grooming untuk hewan kesayanganmu.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap"
          rel="stylesheet">

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

    <button class="menu-toggle"
            id="menuToggle"
            aria-label="Buka menu"
            aria-expanded="false">
        ☰
    </button>

    <nav class="nav-menu" id="navMenu">
        <a href="index.php" class="nav-link active">Beranda</a>
        <a href="produk.php" class="nav-link">Produk</a>
        <a href="#kategori" class="nav-link">Kategori</a>
        <a href="grooming.php" class="nav-link">Grooming</a>

        <?php if ($isPelanggan): ?>
            <a href="riwayat_pesanan.php" class="nav-link">
                Riwayat Pesanan
            </a>
        <?php endif; ?>

        <a href="#tentang" class="nav-link">Tentang Kami</a>
    </nav>

    <!-- PENCARIAN -->
    <form class="search-box" action="produk.php" method="GET">
        <span>⌕</span>
        <input type="search"
               name="cari"
               placeholder="Cari produk..."
               aria-label="Cari produk">
        <button type="submit" aria-label="Cari">⌕</button>
    </form>

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

            <a href="logout.php" class="btn-small">
                Logout
            </a>

        <?php else: ?>

            <a href="login.php" class="login-link">Login</a>
            <a href="register.php" class="btn-small">Daftar</a>

        <?php endif; ?>

    </div>
</header>

<main>

    <!-- HERO -->
    <section class="hero">

        <div class="hero-decoration paw-one">🐾</div>
        <div class="hero-decoration heart-one">♡</div>
        <div class="hero-decoration heart-two">♥</div>

        <div class="hero-content">

            <div class="hero-label">🐾 Pet Shop Kesayanganmu</div>

            <h1>
                Temukan Semua Kebutuhan
                <span>Hewan Kesayanganmu!</span>
            </h1>

            <p>
                Makanan, vitamin, aksesoris, hingga layanan grooming
                untuk sahabat berbulu kesayanganmu.
            </p>

            <div class="hero-buttons">
                <a href="produk.php" class="btn-primary">
                    Belanja Sekarang <span>→</span>
                </a>

                <a href="grooming.php" class="btn-outline">
                    Lihat Grooming
                </a>
            </div>

            <div class="hero-note">
                <span>♡</span>
                Karena mereka juga bagian dari keluarga!
            </div>

        </div>

        <div class="hero-visual">

            <div class="hero-circle"></div>

            <img
                class="hero-pets"
                src="https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&w=1000&q=85"
                alt="Hewan peliharaan"
                onerror="this.style.display='none';document.getElementById('heroFallback').style.display='flex';">

            <div class="hero-fallback" id="heroFallback">
                <span>🐶</span>
                <span>🐱</span>
                <span>🐰</span>
            </div>

            <div class="doodle doodle-heart">♡</div>
            <div class="doodle doodle-paw">🐾</div>

            <div class="hero-benefits">
                <div>
                    <span>🛡️</span>
                    <p>Produk<br><strong>Berkualitas</strong></p>
                </div>

                <div>
                    <span>✂️</span>
                    <p>Layanan<br><strong>Grooming</strong></p>
                </div>
            </div>

        </div>
    </section>

    <!-- KATEGORI -->
    <section class="section" id="kategori">

        <div class="section-heading">
            <div>
                <div class="eyebrow">
                    <span>🐾</span>
                    PILIH SESUAI KEBUTUHAN
                </div>

                <h2>
                    Kategori Produk
                    <span class="heading-heart">♡</span>
                </h2>

                <p>Pilih kategori favorit untuk sahabat kesayanganmu.</p>
            </div>

            <a href="produk.php" class="text-link">Lihat Semua →</a>
        </div>

        <div class="category-grid">

            <?php if ($kategori && mysqli_num_rows($kategori) > 0): ?>

                <?php while ($k = mysqli_fetch_assoc($kategori)): ?>
                    <?php
                    $namaKategori = $k['nama_kategori'];
                    $keyKategori = strtolower(trim($namaKategori));
                    $ikon = $gambarKategori[$keyKategori] ?? '🐾';
                    ?>

                    <a class="category-card"
                       href="produk.php?kategori=<?= (int) $k['id'] ?>">

                        <span class="category-icon"><?= $ikon ?></span>
                        <strong><?= e($namaKategori) ?></strong>
                        <span class="category-arrow">↗</span>

                    </a>
                <?php endwhile; ?>

            <?php else: ?>
                <p>Belum ada kategori produk.</p>
            <?php endif; ?>

        </div>
    </section>

    <!-- PRODUK PILIHAN -->
    <section class="section products-section" id="produk">

        <div class="section-heading">
            <div>
                <div class="eyebrow">
                    <span>🐾</span>
                    FAVORIT PARA PET LOVERS
                </div>

                <h2>
                    Semua Produk PetShop
                    <span class="heading-heart">♡</span>
                </h2>

                <p>
                    Temukan makanan, snack, vitamin, aksesoris, dan
                    perlengkapan untuk hewan kesayanganmu.
                </p>
            </div>

            <a href="produk.php" class="text-link">Lihat Semua →</a>
        </div>

        <div class="product-grid">

            <?php if ($produk && mysqli_num_rows($produk) > 0): ?>

                <?php while ($p = mysqli_fetch_assoc($produk)): ?>
                    <?php $pathGambar = gambarProduk($p['gambar'] ?? ''); ?>

                    <article class="product-card">

                        <a class="product-picture"
                           href="detail.php?id=<?= (int) $p['id'] ?>">

                            <?php if ($pathGambar !== ''): ?>
                                <img
                                    src="<?= e($pathGambar) ?>"
                                    alt="<?= e($p['nama_produk']) ?>"
                                    loading="lazy"
                                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <?php endif; ?>

                            <div class="product-fallback"
                                 style="<?= $pathGambar !== '' ? 'display:none' : '' ?>">
                                🐾
                            </div>

                            <span class="picture-heart">♡</span>
                        </a>

                        <div class="product-details">

                            <span class="product-category">
                                <?= e($p['nama_kategori'] ?? 'Produk') ?>
                            </span>

                            <h3>
                                <a href="detail.php?id=<?= (int) $p['id'] ?>">
                                    <?= e($p['nama_produk']) ?>
                                </a>
                            </h3>

                            <div class="product-rating">
                                <span>★</span>
                                Produk pilihan
                            </div>

                            <div class="product-bottom">
                                <strong class="product-price">
                                    Rp<?= number_format(
                                        (float) $p['harga'],
                                        0,
                                        ',',
                                        '.'
                                    ) ?>
                                </strong>

                                <span class="product-stock">
                                    Stok <?= (int) $p['stok'] ?>
                                </span>
                            </div>

                            <a href="detail.php?id=<?= (int) $p['id'] ?>"
                               class="btn-add-cart">
                                ♡ Lihat Produk
                            </a>

                        </div>
                    </article>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="empty-products">
                    <span>🐱</span>
                    <h3>Produk belum tersedia</h3>
                    <p>
                        Produk akan muncul di sini setelah ditambahkan
                        ke database.
                    </p>
                </div>

            <?php endif; ?>

        </div>
    </section>

    <!-- PROMO GROOMING -->
    <section class="grooming-wrap">

        <div class="grooming-card">

            <div class="grooming-copy">
                <div class="eyebrow">
                    🐾 PERAWATAN HEWAN KESAYANGAN
                </div>

                <h2>Grooming untuk Si Manis ♡</h2>

                <p>
                    Bantu hewan kesayangan tetap bersih, sehat, dan
                    nyaman dengan layanan grooming pilihan.
                </p>

                <a href="grooming.php" class="btn-primary">
                    Booking Grooming <span>→</span>
                </a>
            </div>

            <div class="grooming-art">
                <span class="grooming-sparkle">✦</span>
                <span class="grooming-pet">🐱</span>
                <span class="grooming-bubble">Clean<br>&amp; Happy!</span>
                <span class="grooming-heart">♡</span>
                <span class="grooming-tub">🛁</span>
            </div>

        </div>
    </section>

    <!-- KEUNGGULAN -->
    <section class="service-section" id="tentang">

        <div class="service-item">
            <span>🐾</span>
            <h3>Produk Pilihan</h3>
            <p>Kebutuhan hewan kesayangan.</p>
        </div>

        <div class="service-item">
            <span>📦</span>
            <h3>Belanja Praktis</h3>
            <p>Pesan kapan saja secara online.</p>
        </div>

        <div class="service-item">
            <span>🔒</span>
            <h3>Transaksi Tertata</h3>
            <p>Informasi pesanan lebih mudah dipantau.</p>
        </div>

        <div class="service-item">
            <span>💌</span>
            <h3>Siap Membantu</h3>
            <p>Utamakan kenyamanan pelanggan.</p>
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

            <p>Karena mereka juga bagian dari keluarga.
                PetShop menyediakan makanan, perlengkapan, dan layanan perawatan terbaik untuk menjaga hewan kesayanganmu tetap sehat dan bahagia. ♡
            </p>
        </div>

        <div class="footer-column">
            <h3>Menu Utama</h3>
            <a href="index.php">Beranda</a>
            <a href="produk.php">Produk</a>
            <a href="#kategori">Kategori</a>
            <a href="grooming.php">Grooming</a>
            <?php if ($isPelanggan): ?>
                <a href="riwayat_pesanan.php">Riwayat Pesanan</a>
            <?php endif; ?>
            <a href="index.php#tentang" class="nav-link">Tentang Kami</a>
        </div>

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
const menuToggle = document.getElementById('menuToggle');
const navMenu = document.getElementById('navMenu');

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
</script>

</body>
</html>