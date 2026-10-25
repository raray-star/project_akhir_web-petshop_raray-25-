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

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$pesananId = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);

if (!$pesananId || $pesananId < 1) {
    header('Location: riwayat_pesanan.php');
    exit;
}

/* Ambil informasi pesanan milik pelanggan yang login */
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        id,
        nama_penerima,
        tanggal,
        total,
        alamat,
        no_hp,
        metode_pembayaran,
        status,
        status_pembayaran
     FROM pesanan
     WHERE id = ? AND user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, 'ii', $pesananId, $userId);
mysqli_stmt_execute($stmt);
$pesanan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$pesanan) {
    header('Location: riwayat_pesanan.php');
    exit;
}

/* Ambil detail produk dalam pesanan */
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        dp.produk_id,
        dp.harga,
        dp.jumlah,
        dp.subtotal,
        p.nama_produk,
        p.gambar
     FROM detail_pesanan dp
     LEFT JOIN produk p ON p.id = dp.produk_id
     WHERE dp.pesanan_id = ?
     ORDER BY dp.produk_id ASC"
);

mysqli_stmt_bind_param($stmt, 'i', $pesananId);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$items = [];

while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}

mysqli_stmt_close($stmt);

/* Ambil nama akun pelanggan */
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT nama FROM users WHERE id = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$nama = $user['nama'] ?? 'Pelanggan';

function statusClass($status) {
    $status = strtolower(trim((string)$status));

    if (
        strpos($status, 'lunas') !== false ||
        strpos($status, 'selesai') !== false ||
        strpos($status, 'diproses') !== false
    ) {
        return 'status-success';
    }

    if (
        strpos($status, 'batal') !== false ||
        strpos($status, 'gagal') !== false
    ) {
        return 'status-danger';
    }

    return 'status-pending';
}

$statusPembayaran = $pesanan['status_pembayaran'] ?? '';

if ($statusPembayaran === '') {
    $statusPembayaran = ($pesanan['metode_pembayaran'] === 'COD')
        ? 'Bayar di Tempat'
        : ($pesanan['status'] ?? 'Menunggu Pembayaran');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Pesanan - PetShop</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #fffaf7;
            color: #38251f;
            font-family: Arial, sans-serif;
            font-size: 16px;
        }

        .detail-container {
            width: min(1050px, 92%);
            margin: 40px auto 70px;
        }

        .detail-heading { margin-bottom: 25px; }

        .eyebrow {
            color: #a94e43;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .detail-heading h1 {
            margin: 10px 0;
            font-size: clamp(28px, 4vw, 38px);
        }

        .detail-heading p {
            color: #76645c;
            line-height: 1.7;
        }

        .detail-card {
            background: white;
            border: 1px solid #f0dfd5;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 22px;
            box-shadow: 0 8px 24px #613a2810;
        }

        .detail-card h2 {
            margin: 0 0 20px;
            font-size: 22px;
        }

        .status {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }

        .status-success {
            background: #e4f5e8;
            color: #267443;
        }

        .status-pending {
            background: #fff1d7;
            color: #8a5b13;
        }

        .status-danger {
            background: #ffebe8;
            color: #a3382e;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .info-label {
            color: #76645c;
            font-size: 14px;
            margin-bottom: 7px;
        }

        .info-value {
            font-weight: bold;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .product-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0e4dc;
        }

        .product-row:last-child { border-bottom: none; }

        .product-image,
        .product-placeholder {
            width: 78px;
            height: 78px;
            flex-shrink: 0;
            border-radius: 13px;
            object-fit: cover;
            background: #fff1e8;
        }

        .product-placeholder {
            display: grid;
            place-items: center;
            font-size: 29px;
        }

        .product-info {
            flex: 1;
            min-width: 0;
        }

        .product-name {
            font-weight: bold;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .product-description {
            margin-top: 6px;
            color: #76645c;
            font-size: 14px;
            line-height: 1.6;
        }

        .product-subtotal {
            margin-top: 7px;
            color: #994c42;
            font-weight: bold;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            border-top: 1px dashed #ddc8ba;
            padding-top: 20px;
            margin-top: 10px;
            font-size: 20px;
            font-weight: bold;
        }

        .total-row strong { color: #994c42; }

        .btn {
            display: inline-block;
            padding: 13px 18px;
            border-radius: 11px;
            text-decoration: none;
            font-weight: bold;
            margin: 5px 5px 0 0;
        }

        .btn-primary {
            background: #87453e;
            color: white;
        }

        .btn-secondary {
            background: #f6e7df;
            color: #593323;
        }

        .empty-products {
            color: #76645c;
            line-height: 1.7;
        }

        @media (max-width: 600px) {
            .detail-container { margin-top: 25px; }
            .detail-card { padding: 19px; }
            .info-grid { grid-template-columns: 1fr; gap: 16px; }
            .product-image,
            .product-placeholder {
                width: 65px;
                height: 65px;
            }
        }
    </style>
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

    <nav class="nav-menu">
        <a href="index.php" class="nav-link">Beranda</a>
        <a href="produk.php" class="nav-link">Produk</a>
        <a href="kategori.php" class="nav-link">Kategori</a>
        <a href="grooming.php" class="nav-link">Grooming</a>
        <a href="keranjang.php" class="nav-link">Keranjang</a>
    </nav>

    <div class="nav-actions">
        <span class="welcome-user">Hai, <?= e($nama) ?>!</span>
    </div>
</header>

<main class="detail-container">

    <div class="detail-heading">
        <div class="eyebrow">🐾 INFORMASI PEMBELIAN</div>
        <h1>Detail Pesanan ♡</h1>
        <p>Berikut informasi lengkap pesananmu di PetShop.</p>
    </div>

    <section class="detail-card">
        <h2>📋 Informasi Pesanan #<?= (int)$pesanan['id'] ?></h2>

        <div class="info-grid">
            <div>
                <div class="info-label">Tanggal pesanan</div>
                <div class="info-value">
                    <?= e($pesanan['tanggal'] ?? '-') ?>
                </div>
            </div>

            <div>
                <div class="info-label">Nama penerima</div>
                <div class="info-value">
                    <?= e($pesanan['nama_penerima'] ?? $nama) ?>
                </div>
            </div>

            <div>
                <div class="info-label">Nomor HP</div>
                <div class="info-value">
                    <?= e($pesanan['no_hp'] ?? '-') ?>
                </div>
            </div>

            <div>
                <div class="info-label">Metode pembayaran</div>
                <div class="info-value">
                    <?= e($pesanan['metode_pembayaran'] ?? '-') ?>
                </div>
            </div>

            <div>
                <div class="info-label">Status pesanan</div>
                <div>
                    <span class="status <?= e(statusClass($pesanan['status'] ?? '')) ?>">
                        <?= e($pesanan['status'] ?? 'Belum ada status') ?>
                    </span>
                </div>
            </div>

            <div>
                <div class="info-label">Status pembayaran</div>
                <div>
                    <span class="status <?= e(statusClass($statusPembayaran)) ?>">
                        <?= e($statusPembayaran) ?>
                    </span>
                </div>
            </div>

            <div style="grid-column: 1 / -1;">
                <div class="info-label">Alamat pengiriman</div>
                <div class="info-value">
                    <?= nl2br(e($pesanan['alamat'] ?? '-')) ?>
                </div>
            </div>
        </div>
    </section>

    <section class="detail-card">
        <h2>🛍️ Produk yang Dibeli</h2>

        <?php if (empty($items)): ?>
            <p class="empty-products">
                Detail produk tidak ditemukan untuk pesanan ini.
            </p>
        <?php else: ?>

            <?php foreach ($items as $item): ?>
                <div class="product-row">
                    <?php
                    $gambar = trim((string)($item['gambar'] ?? ''));

                    if (
                        $gambar !== ''
                        && preg_match('~^https?://~i', $gambar)
                    ) {
                        $gambarPath = $gambar;
                    } elseif ($gambar !== '') {
                        $gambarPath = 'assets/img/produk/' .
                            basename(str_replace('\\', '/', $gambar));
                    } else {
                        $gambarPath = '';
                    }
                    ?>

                    <?php if ($gambarPath !== ''): ?>
                        <img
                            class="product-image"
                            src="<?= e($gambarPath) ?>"
                            alt="<?= e($item['nama_produk'] ?? 'Produk') ?>"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                        <div class="product-placeholder" style="display:none">🐾</div>
                    <?php else: ?>
                        <div class="product-placeholder">🐾</div>
                    <?php endif; ?>

                    <div class="product-info">
                        <div class="product-name">
                            <?= e($item['nama_produk'] ?? 'Produk tidak tersedia') ?>
                        </div>

                        <div class="product-description">
                            <?= rupiah($item['harga']) ?>
                            × <?= (int)$item['jumlah'] ?> produk
                        </div>

                        <div class="product-subtotal">
                            <?= rupiah($item['subtotal']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

        <div class="total-row">
            <span>Total Pesanan</span>
            <strong><?= rupiah($pesanan['total']) ?></strong>
        </div>
    </section>

    <a href="riwayat_pesanan.php" class="btn btn-primary">
        ← Riwayat Pesanan
    </a>

    <a href="produk.php" class="btn btn-secondary">
        Belanja Lagi
    </a>

</main>

</body>
</html>