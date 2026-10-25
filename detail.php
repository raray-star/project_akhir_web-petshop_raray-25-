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

// Validasi ID produk
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: produk.php');
    exit;
}

// Data pengguna
$namaPengguna = $_SESSION['nama'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';

$isLogin = $userId > 0;
$isAdmin = $isLogin && $role === 'admin';
$isPelanggan = $isLogin && $role === 'pelanggan';

$data = null;
$pesan = '';
$tidakDitemukan = false;

// Ambil data produk
$stmt = mysqli_prepare($koneksi, "
    SELECT p.*, k.nama_kategori
    FROM produk p
    LEFT JOIN kategori k ON p.kategori_id = k.id
    WHERE p.id = ?
    LIMIT 1
");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    $hasil = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($hasil);

    mysqli_stmt_close($stmt);
}

if (!$data) {
    $tidakDitemukan = true;
    http_response_code(404);
}

// Tambahkan produk ke keranjang
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['tambah_keranjang']) &&
    !$tidakDitemukan
) {
    if (!$isLogin) {
        header('Location: login.php');
        exit;
    }

    if (!$isPelanggan) {
        $pesan = 'Fitur keranjang hanya tersedia untuk pelanggan.';
    } else {
        $jumlahInput = filter_input(
            INPUT_POST,
            'jumlah',
            FILTER_VALIDATE_INT
        );

        $jumlah = (
            $jumlahInput === false || $jumlahInput === null
        ) ? 0 : $jumlahInput;

        if ($jumlah < 1) {
            $pesan = 'Jumlah produk minimal 1.';
        } else {
            mysqli_begin_transaction($koneksi);

            try {
                // Periksa stok produk
                $stmt = mysqli_prepare($koneksi, "
                    SELECT stok
                    FROM produk
                    WHERE id = ?
                    FOR UPDATE
                ");

                if (!$stmt) {
                    throw new Exception('Gagal memeriksa stok produk.');
                }

                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);

                $produkTerbaru = mysqli_fetch_assoc(
                    mysqli_stmt_get_result($stmt)
                );

                mysqli_stmt_close($stmt);

                if (!$produkTerbaru) {
                    throw new Exception('Produk tidak ditemukan.');
                }

                $stokTerbaru = (int)$produkTerbaru['stok'];

                if ($stokTerbaru < 1) {
                    throw new Exception('Maaf, produk sedang habis.');
                }

                if ($jumlah > $stokTerbaru) {
                    throw new Exception(
                        'Jumlah melebihi stok yang tersedia.'
                    );
                }

                // Periksa produk di keranjang
                $stmt = mysqli_prepare($koneksi, "
                    SELECT jumlah
                    FROM keranjang
                    WHERE user_id = ? AND produk_id = ?
                    LIMIT 1
                    FOR UPDATE
                ");

                if (!$stmt) {
                    throw new Exception('Gagal memeriksa keranjang.');
                }

                mysqli_stmt_bind_param($stmt, 'ii', $userId, $id);
                mysqli_stmt_execute($stmt);

                $item = mysqli_fetch_assoc(
                    mysqli_stmt_get_result($stmt)
                );

                mysqli_stmt_close($stmt);

                if ($item) {
                    $jumlahBaru = (int)$item['jumlah'] + $jumlah;

                    if ($jumlahBaru > $stokTerbaru) {
                        throw new Exception(
                            'Jumlah di keranjang melebihi stok tersedia.'
                        );
                    }

                    $stmt = mysqli_prepare($koneksi, "
                        UPDATE keranjang
                        SET jumlah = ?
                        WHERE user_id = ? AND produk_id = ?
                    ");

                    if (!$stmt) {
                        throw new Exception(
                            'Gagal memperbarui keranjang.'
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        'iii',
                        $jumlahBaru,
                        $userId,
                        $id
                    );
                } else {
                    $stmt = mysqli_prepare($koneksi, "
                        INSERT INTO keranjang (user_id, produk_id, jumlah)
                        VALUES (?, ?, ?)
                    ");

                    if (!$stmt) {
                        throw new Exception(
                            'Gagal menambahkan produk ke keranjang.'
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        'iii',
                        $userId,
                        $id,
                        $jumlah
                    );
                }

                if (!mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);

                    throw new Exception(
                        'Gagal menyimpan produk ke keranjang.'
                    );
                }

                mysqli_stmt_close($stmt);
                mysqli_commit($koneksi);

                header('Location: keranjang.php');
                exit;

            } catch (Throwable $error) {
                mysqli_rollback($koneksi);
                $pesan = $error->getMessage();
            }
        }
    }
}

// Siapkan gambar produk
$gambar = '';

if (!$tidakDitemukan && !empty($data['gambar'])) {
    $gambarTersimpan = trim(
        str_replace('\\', '/', $data['gambar'])
    );

    if (preg_match('~^https?://~i', $gambarTersimpan)) {
        $gambar = $gambarTersimpan;
    } elseif (strpos($gambarTersimpan, 'assets/') === 0) {
        $gambar = $gambarTersimpan;
    } else {
        $gambar = 'assets/img/produk/' . basename($gambarTersimpan);
    }
}

// Jumlah produk di keranjang
$jumlahKeranjang = 0;

if ($isPelanggan) {
    $stmtKeranjang = mysqli_prepare($koneksi, "
        SELECT COALESCE(SUM(jumlah), 0) AS jumlah
        FROM keranjang
        WHERE user_id = ?
    ");

    if ($stmtKeranjang) {
        mysqli_stmt_bind_param($stmtKeranjang, 'i', $userId);
        mysqli_stmt_execute($stmtKeranjang);

        $hasilKeranjang = mysqli_stmt_get_result($stmtKeranjang);
        $dataKeranjang = mysqli_fetch_assoc($hasilKeranjang);

        $jumlahKeranjang = (int)($dataKeranjang['jumlah'] ?? 0);

        mysqli_stmt_close($stmtKeranjang);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $tidakDitemukan
        ? 'Produk Tidak Ditemukan'
        : e($data['nama_produk']) ?> | PetShop</title>

    <meta
        name="description"
        content="Lihat detail produk, harga, stok, dan deskripsi produk PetShop."
    >

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
        <a href="produk.php" class="nav-link active">Produk</a>
        <a href="index.php#kategori" class="nav-link">Kategori</a>

        <!-- MENU GROOMING UNTUK ADMIN DAN PELANGGAN -->
        <a href="grooming.php" class="nav-link">Grooming</a>

        <?php if ($isPelanggan): ?>
            <a href="riwayat_pesanan.php" class="nav-link">
                Riwayat Pesanan
            </a>
        <?php endif; ?>

        <a href="index.php#tentang" class="nav-link">Tentang Kami</a>
    </nav>

    <div class="nav-actions">

        <?php if ($isPelanggan): ?>
            <a
                href="keranjang.php"
                class="cart-link"
                aria-label="Keranjang"
            >
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

<!-- DETAIL PRODUK -->
<main class="detail-page">

    <div class="breadcrumb">
        <a href="index.php">Beranda</a> /
        <a href="produk.php">Produk</a> /
        <span>Detail Produk</span>
    </div>

    <?php if ($tidakDitemukan): ?>

        <section class="detail-not-found">
            <span>🐾</span>

            <h1>Produk tidak ditemukan</h1>

            <p>
                Produk mungkin sudah dihapus atau tautannya tidak benar.
            </p>

            <a href="produk.php" class="detail-back">
                ← Kembali ke Produk
            </a>
        </section>

    <?php else: ?>

        <section class="detail-card">

            <!-- GAMBAR PRODUK -->
            <div class="detail-picture">

                <?php if ($gambar !== ''): ?>

                    <img
                        src="<?= e($gambar) ?>"
                        alt="<?= e($data['nama_produk']) ?>"
                        onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                    >

                    <div
                        class="detail-placeholder"
                        style="display:none"
                    >🐾</div>

                <?php else: ?>

                    <div class="detail-placeholder">🐾</div>

                <?php endif; ?>

            </div>

            <!-- INFORMASI PRODUK -->
            <div class="detail-info">

                <span class="detail-category">
                    <?= e($data['nama_kategori'] ?? 'Produk') ?>
                </span>

                <h1><?= e($data['nama_produk']) ?></h1>

                <div class="detail-price">
                    <?= rupiah($data['harga']) ?>
                </div>

                <div class="detail-stock">
                    Stok tersedia: <?= (int)$data['stok'] ?>
                </div>

                <div class="detail-description">
                    <?= !empty($data['deskripsi'])
                        ? nl2br(e($data['deskripsi']))
                        : 'Produk pilihan untuk kebutuhan hewan kesayanganmu.' ?>
                </div>

                <?php if ($pesan !== ''): ?>
                    <div class="detail-message">
                        <?= e($pesan) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">

                    <label class="quantity-label" for="jumlah">
                        Jumlah produk
                    </label>

                    <div class="quantity-control">

                        <button
                            type="button"
                            class="quantity-btn"
                            id="kurangJumlah"
                            aria-label="Kurangi jumlah"
                            <?= (int)$data['stok'] < 1 ? 'disabled' : '' ?>
                        >−</button>

                        <input
                            class="quantity-input"
                            type="number"
                            name="jumlah"
                            id="jumlah"
                            value="1"
                            min="1"
                            max="<?= (int)$data['stok'] ?>"
                            required
                            <?= (int)$data['stok'] < 1 ? 'disabled' : '' ?>
                        >

                        <button
                            type="button"
                            class="quantity-btn"
                            id="tambahJumlah"
                            aria-label="Tambah jumlah"
                            <?= (int)$data['stok'] < 1 ? 'disabled' : '' ?>
                        >+</button>

                    </div>

                    <button
                        type="submit"
                        name="tambah_keranjang"
                        class="detail-submit"
                        <?= (int)$data['stok'] < 1 ? 'disabled' : '' ?>
                    >
                        <?= (int)$data['stok'] < 1
                            ? 'Stok Habis'
                            : '🛒 Tambah ke Keranjang' ?>
                    </button>

                </form>

                <a href="produk.php" class="detail-back">
                    ← Kembali ke Produk
                </a>

            </div>

        </section>

    <?php endif; ?>

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
                PetShop menyediakan makanan dan perlengkapan untuk
                menjaga hewan kesayanganmu tetap sehat dan bahagia. ♡
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

<!-- JAVASCRIPT -->
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

// Tombol jumlah produk
const inputJumlah = document.getElementById('jumlah');
const tombolKurang = document.getElementById('kurangJumlah');
const tombolTambah = document.getElementById('tambahJumlah');

if (inputJumlah && tombolKurang && tombolTambah) {

    function perbaruiTombol() {
        const min = Number(inputJumlah.min) || 1;
        const max = Number(inputJumlah.max) || 1;
        const jumlah = Number(inputJumlah.value) || min;

        tombolKurang.disabled = jumlah <= min;
        tombolTambah.disabled = jumlah >= max;
    }

    tombolKurang.addEventListener('click', function () {
        const min = Number(inputJumlah.min) || 1;
        const jumlah = Number(inputJumlah.value) || min;

        if (jumlah > min) {
            inputJumlah.value = jumlah - 1;
        }

        perbaruiTombol();
    });

    tombolTambah.addEventListener('click', function () {
        const max = Number(inputJumlah.max) || 1;
        const jumlah = Number(inputJumlah.value) || 1;

        if (jumlah < max) {
            inputJumlah.value = jumlah + 1;
        }

        perbaruiTombol();
    });

    inputJumlah.addEventListener('input', perbaruiTombol);

    inputJumlah.addEventListener('change', function () {
        const min = Number(inputJumlah.min) || 1;
        const max = Number(inputJumlah.max) || 1;
        let jumlah = Number(inputJumlah.value) || min;

        jumlah = Math.max(min, Math.min(jumlah, max));
        inputJumlah.value = jumlah;

        perbaruiTombol();
    });

    perbaruiTombol();
}
</script>

</body>
</html>