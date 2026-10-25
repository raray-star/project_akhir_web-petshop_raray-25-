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
$pesananId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$pesananId || $pesananId < 1) {
    header('Location: keranjang.php');
    exit;
}

if (empty($_SESSION['csrf_pembayaran'])) {
    $_SESSION['csrf_pembayaran'] = bin2hex(random_bytes(32));
}

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT * FROM pesanan WHERE id = ? AND user_id = ? LIMIT 1"
);

mysqli_stmt_bind_param($stmt, 'ii', $pesananId, $userId);
mysqli_stmt_execute($stmt);
$pesanan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$pesanan) {
    exit('Pesanan tidak ditemukan atau bukan milik akun kamu.');
}

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT * FROM detail_pesanan WHERE pesanan_id = ?"
);

mysqli_stmt_bind_param($stmt, 'i', $pesananId);
mysqli_stmt_execute($stmt);
$resultDetail = mysqli_stmt_get_result($stmt);

$details = [];

while ($row = mysqli_fetch_assoc($resultDetail)) {
    $details[] = $row;
}

mysqli_stmt_close($stmt);

$total = (float) ($pesanan['total'] ?? 0);
$metode = $pesanan['metode_pembayaran'] ?? 'QRIS';
$metode = $pesanan['metode_pembayaran'] ?? 'QRIS';

// Status pesanan diambil dari kolom status tabel pesanan.
$statusPesanan = $pesanan['status'] ?? 'Menunggu Pembayaran';

// Ambil status pembayaran dari tabel pembayaran.
$stmtBayar = mysqli_prepare(
    $koneksi,
    "SELECT status
     FROM pembayaran
     WHERE pesanan_id = ?
     ORDER BY id DESC
     LIMIT 1"
);

mysqli_stmt_bind_param($stmtBayar, 'i', $pesananId);
mysqli_stmt_execute($stmtBayar);

$dataBayar = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmtBayar)
);

mysqli_stmt_close($stmtBayar);

// Jika pesanan sudah Lunas pada database,
// tampilkan Lunas meskipun tabel pembayaran berbeda.
$lunas = (
    strtolower(trim($pesanan['status_pembayaran'] ?? '')) === 'lunas'
    || strtolower(trim($dataBayar['status'] ?? '')) === 'diterima'
);

$statusPembayaran = $lunas
    ? 'Lunas'
    : (
        ($dataBayar['status'] ?? '') === 'Menunggu Verifikasi'
            ? 'Menunggu Verifikasi'
            : 'Belum Bayar'
    );

$pesan = $_SESSION['pesan_pembayaran'] ?? '';
unset($_SESSION['pesan_pembayaran']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pembayaran | PetShop</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #fffaf7;
            color: #49352f;
            font-family: Arial, sans-serif;
            font-size: 16px;
        }

        a {
            text-decoration: none;
        }

        /* NAVBAR */
        .navbar {
            background: #fff;
            border-bottom: 1px solid #f3e4dc;
            box-shadow: 0 3px 15px rgba(92, 55, 42, .05);
            position: relative;
            z-index: 10;
        }

        .navbar .container {
            width: min(1150px, 92%);
            min-height: 76px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
        }

        .navbar .logo {
            color: #87453e;
            font-size: 25px;
            font-weight: bold;
            white-space: nowrap;
        }

        .navbar nav {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 25px;
            flex-wrap: wrap;
        }

        .navbar nav a {
            color: #654b42;
            font-size: 15px;
            font-weight: 600;
            transition: color .2s;
        }

        .navbar nav a:hover {
            color: #c16e83;
        }

        /* HALAMAN PEMBAYARAN */
        .payment-page {
            width: min(1100px, 92%);
            margin: 42px auto 65px;
        }

        .payment-heading {
            text-align: center;
            margin-bottom: 32px;
        }

        .payment-heading .eyebrow {
            color: #b76b70;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .payment-heading h1 {
            margin: 12px 0;
            color: #87453e;
            font-size: clamp(27px, 4vw, 36px);
        }

        .payment-heading p {
            margin: 0;
            color: #806d65;
            line-height: 1.8;
        }

        .payment-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .payment-card {
            padding: 26px;
            margin-bottom: 22px;
            background: #fff;
            border: 1px solid #f0dfd5;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(97, 58, 40, .06);
        }

        .payment-card h2 {
            margin: 0 0 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f3e5de;
            color: #87453e;
            font-size: 20px;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            padding: 13px 0;
            border-bottom: 1px solid #f4eae5;
            line-height: 1.6;
        }

        .payment-row:last-child {
            border-bottom: none;
        }

        .payment-row > span:last-child,
        .payment-row > strong:last-child {
            text-align: right;
            overflow-wrap: anywhere;
        }

        .payment-total {
            color: #b65e75;
            font-size: 21px;
            font-weight: bold;
        }

        .payment-status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            background: #fff0d7;
            color: #805b1c;
            font-size: 12px;
            font-weight: bold;
        }

        .payment-status.paid {
            background: #e0f7e8;
            color: #217447;
        }

        .payment-alert {
            padding: 14px;
            margin-bottom: 18px;
            border: 1px solid #edc3bd;
            border-radius: 12px;
            background: #fff0ef;
            color: #8a2922;
            line-height: 1.7;
        }

        .success-notice {
            padding: 16px;
            margin: 12px 0;
            border: 1px solid #cdebd6;
            border-radius: 13px;
            background: #e8f8ed;
            color: #217447;
            line-height: 1.8;
        }

        /* QR DEMO */
        .demo-qr {
            display: grid;
            grid-template-columns: repeat(21, 1fr);
            grid-template-rows: repeat(21, 1fr);
            gap: 1px;
            width: 230px;
            height: 230px;
            max-width: 100%;
            padding: 10px;
            margin: 22px auto 12px;
            border: 1px solid #eadfe7;
            border-radius: 8px;
            background: #fff;
        }

        .qr-cell {
            background: white;
        }

        .qr-cell.dark {
            background: #222;
        }

        .qr-label {
            margin: 12px 0 18px;
            color: #87453e;
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .qr-total {
            margin: 18px 0;
            color: #806d65;
            text-align: center;
            line-height: 1.8;
        }

        .qr-total strong {
            display: block;
            color: #b65e75;
            font-size: 25px;
        }

        /* TOMBOL */
        .payment-button {
            display: block;
            width: 100%;
            padding: 14px 18px;
            margin-top: 12px;
            border: none;
            border-radius: 12px;
            background: #87453e;
            color: white;
            font-family: inherit;
            font-size: 15px;
            font-weight: bold;
            text-align: center;
            cursor: pointer;
            transition: .2s;
        }

        .payment-button:hover {
            background: #6f352f;
            transform: translateY(-1px);
        }

        .payment-button.secondary {
            background: #f8eae3;
            color: #704b43;
        }

        .payment-button.secondary:hover {
            background: #edd7cb;
        }

        .empty-detail {
            padding: 15px 0;
            color: #85736b;
            text-align: center;
        }

        .payment-footer {
            padding: 25px 15px;
            border-top: 1px solid #f0dfd5;
            background: #fff;
            color: #806b65;
            text-align: center;
        }

        .payment-footer p {
            margin: 0;
        }

        @media (max-width: 800px) {
            .navbar .container {
                padding: 16px 0;
                align-items: flex-start;
                flex-direction: column;
                gap: 15px;
            }

            .navbar nav {
                justify-content: flex-start;
                gap: 14px 20px;
            }

            .payment-grid {
                grid-template-columns: 1fr;
            }

            .payment-page {
                margin: 28px auto 45px;
            }

            .payment-card {
                padding: 20px;
            }
        }

        @media (max-width: 420px) {
            .navbar nav {
                gap: 12px;
            }

            .navbar nav a {
                font-size: 13px;
            }

            .payment-row {
                gap: 9px;
                font-size: 14px;
            }

            .payment-heading h1 {
                font-size: 26px;
            }
        }
    </style>
</head>

<body>

<header class="navbar">
    <div class="container">
        <a href="index.php" class="logo">🐾 PetShop</a>

        <nav>
            <a href="index.php">Beranda</a>
            <a href="produk.php">Produk</a>
            <a href="kategori.php">Kategori</a>
            <a href="grooming.php">Grooming</a>
            <a href="keranjang.php">Keranjang</a>
        </nav>
    </div>
</header>

<main class="payment-page">

    <div class="payment-heading">
        <div class="eyebrow">PETSHOP · PEMBAYARAN</div>
        <h1>🐾 Pembayaran Pesanan</h1>
        <p>Terima kasih sudah berbelanja di PetShop! ♡</p>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="payment-alert">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <div class="payment-grid">

        <section>
            <div class="payment-card">
                <h2>📦 Ringkasan Pesanan</h2>

                <div class="payment-row">
                    <span>Nomor pesanan</span>
                    <strong>#<?= (int) $pesanan['id'] ?></strong>
                </div>

                <div class="payment-row">
                    <span>Nama penerima</span>
                    <strong>
                        <?php
                        $stmtNama = mysqli_prepare(
                            $koneksi,
                            "SELECT nama FROM users WHERE id = ? LIMIT 1"
                        );

                        mysqli_stmt_bind_param($stmtNama, 'i', $pesanan['user_id']);
                        mysqli_stmt_execute($stmtNama);

                        $dataNama = mysqli_fetch_assoc(
                            mysqli_stmt_get_result($stmtNama)
                        );

                        mysqli_stmt_close($stmtNama);

                        echo e($dataNama['nama'] ?? '-');
                        ?>
                    </strong>
                </div>

                <div class="payment-row">
                    <span>Alamat pengiriman</span>
                    <span>
                        <?= e($pesanan['alamat'] ?? $pesanan['alamat_pengiriman'] ?? '-') ?>
                    </span>
                </div>

                <div class="payment-row">
                    <span>Nomor HP</span>
                    <span><?= e($pesanan['no_hp'] ?? '-') ?></span>
                </div>

                <div class="payment-row">
                    <span>Metode pembayaran</span>
                    <strong><?= e($metode) ?></strong>
                </div>

                <div class="payment-row">
                    <span>Status pesanan</span>
                    <strong><?= e($statusPesanan) ?></strong>
                </div>

                <div class="payment-row">
                    <span>Status pembayaran</span>
                    <span class="payment-status <?= $lunas ? 'paid' : '' ?>">
                        <?= e($statusPembayaran) ?>
                    </span>
                </div>
            </div>

            <div class="payment-card">
                <h2>🛍️ Produk yang Dibeli</h2>

                <?php if (empty($details)): ?>
                    <p class="empty-detail">
                        Detail produk pesanan tidak ditemukan.
                    </p>
                <?php else: ?>
                    <?php foreach ($details as $item): ?>
                        <div class="payment-row">
                            <div>
                                <strong>
                                    <?= e($item['nama_produk'] ?? 'Produk') ?>
                                </strong>
                                <div>
                                    <?= (int) ($item['jumlah'] ?? 0) ?>
                                    ×
                                    <?= rupiah($item['harga'] ?? 0) ?>
                                </div>
                            </div>

                            <strong>
                                <?= rupiah(
                                    $item['subtotal'] ??
                                    ((float) ($item['harga'] ?? 0) *
                                    (int) ($item['jumlah'] ?? 0))
                                ) ?>
                            </strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="payment-row">
                    <strong>Total pembayaran</strong>
                    <span class="payment-total"><?= rupiah($total) ?></span>
                </div>
            </div>
        </section>

        <section>
            <div class="payment-card">
                <h2>💗 Pembayaran</h2>

                <?php if ($metode === 'COD'): ?>

                    <div class="success-notice">
                        <strong>📦 Pembayaran COD</strong><br>
                        Pembayaran dilakukan saat pesanan diterima.
                        Siapkan uang sesuai total pesanan.
                    </div>

                    <div class="payment-row">
                        <span>Total yang harus dibayar</span>
                        <strong class="payment-total"><?= rupiah($total) ?></strong>
                    </div>

                <?php elseif ($lunas): ?>

                    <div class="success-notice">
                        <strong>✓ Pembayaran berhasil!</strong><br>
                        Pesanan kamu sudah ditandai lunas dan sedang diproses.
                    </div>

                <?php else: ?>

                    <div class="demo-qr" aria-label="Ilustrasi QR demo">
                        <?php
                        $seed = 'petshop-demo-' . $pesananId;

                        for ($i = 0; $i < 441; $i++):
                            $x = $i % 21;
                            $y = (int) ($i / 21);
                            $finder = false;
                            $dark = false;

                            foreach ([[0, 0], [14, 0], [0, 14]] as $pos) {
                                $dx = $x - $pos[0];
                                $dy = $y - $pos[1];

                                if ($dx >= 0 && $dx < 7 &&
                                    $dy >= 0 && $dy < 7) {
                                    $finder = true;
                                    $ring = (
                                        $dx === 0 || $dx === 6 ||
                                        $dy === 0 || $dy === 6
                                    );
                                    $center = (
                                        $dx >= 2 && $dx <= 4 &&
                                        $dy >= 2 && $dy <= 4
                                    );
                                    $dark = $ring || $center;
                                    break;
                                }
                            }

                            if (!$finder) {
                                $number = hexdec(
                                    substr(hash('sha256', $seed . '-' . $i), 0, 2)
                                );
                                $dark = ($number % 2 === 0);
                            }
                        ?>
                            <span class="qr-cell <?= $dark ? 'dark' : '' ?>"></span>
                        <?php endfor; ?>
                    </div>

                    <div class="qr-label">PETSHOP · QR DEMO</div>

                    <div class="qr-total">
                        Total pembayaran
                        <strong><?= rupiah($total) ?></strong>
                    </div>

                    <form
                        action="proses_pembayaran.php"
                        method="POST"
                        onsubmit="return confirm('Jalankan simulasi pembayaran pesanan ini?');">

                        <input
                            type="hidden"
                            name="pesanan_id"
                            value="<?= (int) $pesanan['id'] ?>">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['csrf_pembayaran']) ?>">

                        <button type="submit" class="payment-button">
                            ✓ Saya Sudah Membayar
                        </button>
                    </form>

                    <p style="font-size:13px;color:#806d65;line-height:1.6;text-align:center">
                        QR ini hanya ilustrasi dan tidak bisa dipindai
                        untuk pembayaran sungguhan.
                    </p>

                <?php endif; ?>

                <a href="index.php" class="payment-button secondary">
                    Kembali ke Beranda
                </a>

                <a href="keranjang.php" class="payment-button secondary">
                    Kembali ke Keranjang
                </a>
            </div>
        </section>

    </div>
</main>

<footer class="payment-footer">
    <p>🐾 PetShop — Sahabat Terbaik Hewan Kesayanganmu</p>
</footer>

</body>
</html>