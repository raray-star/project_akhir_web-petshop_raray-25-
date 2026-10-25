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

$userId = (int)$_SESSION['user_id'];
$pesanError = '';
$nama = '';
$namaPenerimaAwal = '';
$alamatAwal = '';
$noHpAwal = '';
$metodeAwal = 'QRIS';

/* =========================
   AMBIL DATA PELANGGAN
========================= */
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT nama, email, alamat, no_hp
     FROM users
     WHERE id = ? AND role = 'pelanggan'
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
    header('Location: login.php');
    exit;
}

$nama = $user['nama'];
$namaPenerimaAwal = $user['nama'];
$alamatAwal = $user['alamat'] ?? '';
$noHpAwal = $user['no_hp'] ?? '';

/* =========================
   SIMPAN PILIHAN DARI KERANJANG
   selected_items berisi ID keranjang
========================= */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['selected_items'])
) {
    $pilihan = $_POST['selected_items'];

    if (!is_array($pilihan) || empty($pilihan)) {
        $_SESSION['pesan_keranjang'] = 'Pilih produk yang ingin di-checkout.';
        header('Location: keranjang.php');
        exit;
    }

    $cartIds = [];

    foreach ($pilihan as $id) {
        $id = filter_var($id, FILTER_VALIDATE_INT);

        if ($id !== false && $id !== null && $id > 0) {
            $cartIds[] = (int)$id;
        }
    }

    $cartIds = array_values(array_unique($cartIds));

    if (empty($cartIds)) {
        $_SESSION['pesan_keranjang'] = 'Pilihan produk tidak valid.';
        header('Location: keranjang.php');
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    $types = str_repeat('i', count($cartIds) + 1);
    $params = array_merge([$userId], $cartIds);

    $sql = "SELECT id
            FROM keranjang
            WHERE user_id = ?
            AND id IN ($placeholders)";

    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $validIds = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $validIds[] = (int)$row['id'];
    }

    mysqli_stmt_close($stmt);

    if (count($validIds) !== count($cartIds)) {
        $_SESSION['pesan_keranjang'] = 'Pilihan produk tidak valid.';
        header('Location: keranjang.php');
        exit;
    }

    $_SESSION['checkout_cart_ids'] = $validIds;

    header('Location: checkout.php');
    exit;
}

/* =========================
   AMBIL ID PRODUK TERPILIH
========================= */
$cartIds = $_SESSION['checkout_cart_ids'] ?? [];

if (!is_array($cartIds)) {
    $cartIds = [];
}

$cartIds = array_values(array_unique(array_filter(
    array_map('intval', $cartIds),
    function ($id) {
        return $id > 0;
    }
)));

/* =========================
   PROSES CHECKOUT
========================= */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['buat_pesanan'])
) {
    $namaPenerima = trim($_POST['nama_penerima'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $noHp = trim($_POST['no_hp'] ?? '');
    $metode = trim($_POST['metode_pembayaran'] ?? '');

    $namaPenerimaAwal = $namaPenerima;
    $alamatAwal = $alamat;
    $noHpAwal = $noHp;
    $metodeAwal = $metode;

    if ($namaPenerima === '' || $alamat === '' || $noHp === '') {
        $pesanError = 'Nama penerima, alamat, dan nomor HP wajib diisi.';
    } elseif (strlen($namaPenerima) > 100) {
        $pesanError = 'Nama penerima maksimal 100 karakter.';
    } elseif (!in_array($metode, ['QRIS', 'COD'], true)) {
        $pesanError = 'Metode pembayaran tidak valid.';
    } elseif (empty($cartIds)) {
        $pesanError = 'Pilih produk yang ingin dibeli dari keranjang.';
    } else {
        $transaksiAktif = false;

        try {
            mysqli_begin_transaction($koneksi);
            $transaksiAktif = true;

            $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
            $types = str_repeat('i', count($cartIds) + 1);
            $params = array_merge([$userId], $cartIds);

            /* Ambil hanya produk yang dipilih */
            $sql = "SELECT
                        k.id AS cart_id,
                        k.produk_id,
                        k.jumlah,
                        p.nama_produk,
                        p.harga,
                        p.stok
                    FROM keranjang k
                    JOIN produk p ON p.id = k.produk_id
                    WHERE k.user_id = ?
                    AND k.id IN ($placeholders)
                    FOR UPDATE";

            $stmtCart = mysqli_prepare($koneksi, $sql);
            mysqli_stmt_bind_param($stmtCart, $types, ...$params);
            mysqli_stmt_execute($stmtCart);

            $resultCart = mysqli_stmt_get_result($stmtCart);
            $itemsTerpilih = [];
            $total = 0;

            while ($item = mysqli_fetch_assoc($resultCart)) {
                $jumlah = (int)$item['jumlah'];
                $stok = (int)$item['stok'];

                if ($jumlah < 1) {
                    throw new Exception('Jumlah produk tidak valid.');
                }

                if ($jumlah > $stok) {
                    throw new Exception(
                        'Stok ' . $item['nama_produk'] .
                        ' tidak mencukupi. Periksa keranjang kamu.'
                    );
                }

                $item['subtotal'] = (float)$item['harga'] * $jumlah;
                $total += $item['subtotal'];
                $itemsTerpilih[] = $item;
            }

            mysqli_stmt_close($stmtCart);

            if (count($itemsTerpilih) !== count($cartIds)) {
                throw new Exception(
                    'Pilihan produk berubah. Silakan ulangi checkout dari keranjang.'
                );
            }

            if (empty($itemsTerpilih)) {
                throw new Exception('Tidak ada produk yang dipilih.');
            }

            /* Buat pesanan dengan nama penerima yang dapat diedit */
            $stmtOrder = mysqli_prepare(
                $koneksi,
                "INSERT INTO pesanan
                    (user_id, nama_penerima, total, alamat, no_hp,
                     metode_pembayaran, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'Menunggu Pembayaran')"
            );

            mysqli_stmt_bind_param(
                $stmtOrder,
                'isdsss',
                $userId,
                $namaPenerima,
                $total,
                $alamat,
                $noHp,
                $metode
            );

            if (!mysqli_stmt_execute($stmtOrder)) {
                throw new Exception('Pesanan gagal dibuat.');
            }

            $pesananId = (int)mysqli_insert_id($koneksi);
            mysqli_stmt_close($stmtOrder);

            /* Simpan rincian produk yang dipilih */
            $stmtDetail = mysqli_prepare(
                $koneksi,
                "INSERT INTO detail_pesanan
                    (pesanan_id, produk_id, harga, jumlah, subtotal)
                 VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($itemsTerpilih as $item) {
                $produkId = (int)$item['produk_id'];
                $jumlah = (int)$item['jumlah'];
                $harga = (float)$item['harga'];
                $subtotal = (float)$item['subtotal'];

                mysqli_stmt_bind_param(
                    $stmtDetail,
                    'iidid',
                    $pesananId,
                    $produkId,
                    $harga,
                    $jumlah,
                    $subtotal
                );

                if (!mysqli_stmt_execute($stmtDetail)) {
                    throw new Exception('Rincian produk gagal disimpan.');
                }
            }

            mysqli_stmt_close($stmtDetail);

            /*
             * Keranjang tidak dikosongkan di sini.
             * QRIS akan menghapus produk pesanan melalui proses_pembayaran.php
             * setelah simulasi pembayaran berhasil.
             */

            mysqli_commit($koneksi);
            $transaksiAktif = false;

            unset($_SESSION['checkout_cart_ids']);

            if ($metode === 'QRIS') {
                header('Location: pembayaran.php?id=' . $pesananId);
                exit;
            }

            header('Location: checkout.php?sukses=' . $pesananId);
            exit;

        } catch (Throwable $err) {
            if ($transaksiAktif) {
                mysqli_rollback($koneksi);
            }

            error_log('Checkout PetShop: ' . $err->getMessage());
            $pesanError = $err->getMessage();
        }
    }
}

/* =========================
   KONFIRMASI PESANAN COD
========================= */
if (isset($_GET['sukses'])) {
    $idPesanan = filter_var($_GET['sukses'], FILTER_VALIDATE_INT);
    $pesananSukses = null;

    if ($idPesanan && $idPesanan > 0) {
        $stmtSukses = mysqli_prepare(
            $koneksi,
            "SELECT id, nama_penerima, total, metode_pembayaran, status
             FROM pesanan
             WHERE id = ? AND user_id = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmtSukses,
            'ii',
            $idPesanan,
            $userId
        );

        mysqli_stmt_execute($stmtSukses);
        $pesananSukses = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmtSukses)
        );
        mysqli_stmt_close($stmtSukses);
    }

    if (!$pesananSukses) {
        header('Location: keranjang.php');
        exit;
    }

    if ($pesananSukses['metode_pembayaran'] === 'QRIS') {
        header('Location: pembayaran.php?id=' . (int)$pesananSukses['id']);
        exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pesanan Berhasil - PetShop</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <style>
            * { box-sizing: border-box; }
            body {
                margin: 0;
                background: #fff8f4;
                color: #38251f;
                font-family: Arial, sans-serif;
                font-size: 17px;
            }
            .success-card {
                width: min(600px, 92%);
                margin: 60px auto;
                padding: 35px 25px;
                background: white;
                border-radius: 24px;
                text-align: center;
                box-shadow: 0 10px 35px #59332312;
            }
            .success-icon { font-size: 60px; }
            h1 { font-size: 29px; }
            p { line-height: 1.8; }
            .order-info {
                margin: 24px 0;
                padding: 20px;
                background: #fff1e8;
                border-radius: 16px;
                text-align: left;
                line-height: 2;
                overflow-wrap: anywhere;
            }
            .btn {
                display: inline-block;
                margin: 8px 4px;
                padding: 13px 20px;
                border-radius: 12px;
                background: #813f3a;
                color: white;
                text-decoration: none;
                font-weight: bold;
            }
            .btn-light {
                background: #f6e7df;
                color: #593323;
            }
        </style>
    </head>
    <body>
        <div class="success-card">
            <div class="success-icon">🐾</div>
            <h1>Pesanan Berhasil Dibuat!</h1>
            <p>Terima kasih sudah berbelanja di PetShop. ♡</p>

            <div class="order-info">
                <div><strong>Nomor pesanan:</strong>
                    #<?= (int)$pesananSukses['id'] ?></div>
                <div><strong>Nama penerima:</strong>
                    <?= e($pesananSukses['nama_penerima']) ?></div>
                <div><strong>Total:</strong>
                    <?= rupiah($pesananSukses['total']) ?></div>
                <div><strong>Pembayaran:</strong>
                    <?= e($pesananSukses['metode_pembayaran']) ?></div>
                <div><strong>Status:</strong>
                    <?= e($pesananSukses['status']) ?></div>
            </div>

            <p>Bayar saat pesanan diterima jika layanan COD tersedia.</p>

            <a class="btn" href="produk.php">Belanja Lagi</a>
            <a class="btn btn-light" href="index.php">Beranda</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/* =========================
   TAMPILKAN PRODUK CHECKOUT
========================= */
$items = [];
$total = 0;

if (!empty($cartIds)) {
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));
    $types = str_repeat('i', count($cartIds) + 1);
    $params = array_merge([$userId], $cartIds);

    $sql = "SELECT
                k.id AS cart_id,
                p.id AS produk_id,
                p.nama_produk,
                p.harga,
                p.stok,
                p.gambar,
                k.jumlah,
                (p.harga * k.jumlah) AS subtotal
            FROM keranjang k
            JOIN produk p ON p.id = k.produk_id
            WHERE k.user_id = ?
            AND k.id IN ($placeholders)
            ORDER BY k.id DESC";

    $stmtCartView = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmtCartView, $types, ...$params);
    mysqli_stmt_execute($stmtCartView);

    $resultCartView = mysqli_stmt_get_result($stmtCartView);

    while ($item = mysqli_fetch_assoc($resultCartView)) {
        $items[] = $item;
        $total += (float)$item['subtotal'];
    }

    mysqli_stmt_close($stmtCartView);
}

/* Pilihan tidak valid atau sudah tidak ada di keranjang */
if (empty($items) && empty($pesanError)) {
    unset($_SESSION['checkout_cart_ids']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout - PetShop</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-size: 17px;
            color: #38251f;
            background: #fffaf7;
            font-family: Arial, sans-serif;
        }
        .checkout-container {
            width: min(1120px, 92%);
            margin: 40px auto 70px;
        }
        .checkout-heading { margin-bottom: 25px; }
        .eyebrow {
            color: #a94e43;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .checkout-heading h1 {
            margin: 10px 0;
            font-size: clamp(29px, 4vw, 40px);
        }
        .checkout-heading p {
            color: #76645c;
            line-height: 1.7;
        }
        .checkout-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(280px, .8fr);
            gap: 24px;
            align-items: start;
        }
        .checkout-card {
            background: #fff;
            border: 1px solid #f0dfd5;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 22px;
            box-shadow: 0 8px 24px #613a2810;
        }
        .checkout-card h2 {
            margin: 0 0 20px;
            font-size: 22px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 9px;
            font-weight: 800;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px;
            border: 1px solid #e4d4ca;
            border-radius: 12px;
            background: #fffdfa;
            font: inherit;
            color: #38251f;
        }
        .form-group textarea {
            min-height: 110px;
            resize: vertical;
        }
        .payment-option {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px;
            border: 1px solid #e8d8cf;
            border-radius: 13px;
            margin-bottom: 12px;
            cursor: pointer;
        }
        .payment-option input {
            margin-top: 5px;
            accent-color: #914941;
        }
        .payment-option strong { display: block; }
        .payment-option small {
            display: block;
            margin-top: 4px;
            color: #76645c;
            line-height: 1.5;
        }
        .checkout-item {
            display: flex;
            gap: 13px;
            padding: 14px 0;
            border-bottom: 1px solid #f0e4dc;
        }
        .checkout-item img,
        .item-placeholder {
            width: 75px;
            height: 75px;
            border-radius: 12px;
            object-fit: cover;
            background: #fff1e8;
            flex-shrink: 0;
        }
        .item-placeholder {
            display: grid;
            place-items: center;
            font-size: 28px;
        }
        .item-info { flex: 1; min-width: 0; }
        .item-info strong {
            display: block;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }
        .item-info span {
            display: block;
            margin-top: 5px;
            color: #76645c;
            font-size: 15px;
        }
        .item-subtotal {
            margin-top: 7px;
            font-weight: 800;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 12px 0;
            line-height: 1.5;
        }
        .summary-total {
            border-top: 1px dashed #ddc8ba;
            margin-top: 8px;
            padding-top: 18px;
            font-size: 20px;
            font-weight: 900;
        }
        .summary-total strong { color: #994c42; }
        .form-hint {
            display: block;
            margin-top: 7px;
            color: #85736b;
            font-size: 14px;
            line-height: 1.6;
        }
        .btn-checkout {
            width: 100%;
            border: none;
            border-radius: 13px;
            padding: 16px;
            margin-top: 18px;
            background: #87453e;
            color: white;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
        }
        .btn-checkout:hover { background: #6f352f; }
        .back-link {
            display: inline-block;
            margin-top: 18px;
            color: #87453e;
            font-weight: 800;
            text-decoration: none;
        }
        .error-message {
            background: #fff0ef;
            border: 1px solid #e8b8b2;
            color: #8a2922;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .empty-cart {
            background: white;
            padding: 40px 25px;
            text-align: center;
            border: 1px solid #f0dfd5;
            border-radius: 20px;
        }
        .empty-icon { font-size: 55px; }
        .empty-cart a {
            display: inline-block;
            margin-top: 12px;
            padding: 14px 20px;
            background: #87453e;
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 800;
        }
        @media (max-width: 760px) {
            .checkout-grid { grid-template-columns: 1fr; }
            .checkout-card { padding: 20px; }
            .checkout-container { margin-top: 25px; }
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

<main class="checkout-container">
    <div class="checkout-heading">
        <div class="eyebrow">🐾 SATU LANGKAH LAGI!</div>
        <h1>Checkout Pesanan ♡</h1>
        <p>Pastikan produk dan alamat pengirimanmu sudah benar.</p>
    </div>

    <?php if ($pesanError !== ''): ?>
        <div class="error-message">
            <strong>Checkout belum berhasil.</strong><br>
            <?= e($pesanError) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <div class="empty-cart">
            <div class="empty-icon">🛒</div>
            <h2>Belum ada produk yang dipilih</h2>
            <p>Pilih produk yang ingin dibeli dari keranjangmu terlebih dahulu.</p>
            <a href="keranjang.php">Kembali ke Keranjang</a>
        </div>
    <?php else: ?>
        <form method="POST" action="checkout.php">
            <div class="checkout-grid">
                <div>
                    <section class="checkout-card">
                        <h2>📍 Alamat Pengiriman</h2>

                        <div class="form-group">
                            <label for="nama_penerima">Nama penerima</label>
                            <input
                                type="text"
                                id="nama_penerima"
                                name="nama_penerima"
                                value="<?= e($namaPenerimaAwal) ?>"
                                placeholder="Masukkan nama penerima"
                                maxlength="100"
                                required>
                        </div>

                        <div class="form-group">
                            <label>💳 Metode Pembayaran</label>

                            <label class="payment-option">
                                <input
                                    type="radio"
                                    name="metode_pembayaran"
                                    value="QRIS"
                                    <?= $metodeAwal === 'QRIS' ? 'checked' : '' ?>
                                    required>
                                <span>
                                    <strong>📱 QRIS Demo</strong>
                                    <small>Halaman berikutnya menampilkan QR simulasi yang tidak dapat dipindai sebagai pembayaran asli.</small>
                                </span>
                            </label>

                            <label class="payment-option">
                                <input
                                    type="radio"
                                    name="metode_pembayaran"
                                    value="COD"
                                    <?= $metodeAwal === 'COD' ? 'checked' : '' ?>
                                    required>
                                <span>
                                    <strong>📦 COD (Bayar di Tempat)</strong>
                                    <small>Bayar saat pesanan diterima jika layanan COD tersedia.</small>
                                </span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="no_hp">Nomor HP aktif</label>
                            <input
                                type="tel"
                                id="no_hp"
                                name="no_hp"
                                value="<?= e($noHpAwal) ?>"
                                placeholder="Contoh: 081234567890"
                                maxlength="20"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="alamat">Alamat lengkap</label>
                            <textarea
                                id="alamat"
                                name="alamat"
                                placeholder="Nama jalan, nomor rumah, desa, kecamatan, kabupaten..."
                                required><?= e($alamatAwal) ?></textarea>
                        </div>
                    </section>
                </div>

                <aside>
                    <section class="checkout-card">
                        <h2>🛍️ Ringkasan Pesanan</h2>

                        <?php foreach ($items as $item): ?>
                            <div class="checkout-item">
                                <?php
                                $gambar = trim((string)($item['gambar'] ?? ''));

                                if (
                                    $gambar !== ''
                                    && preg_match('~^https?://~i', $gambar)
                                ) {
                                    $gambarPath = $gambar;
                                } elseif ($gambar !== '') {
                                    $gambarPath = 'assets/img/produk/' . basename(str_replace('\\', '/', $gambar));
                                } else {
                                    $gambarPath = '';
                                }
                                ?>

                                <?php if ($gambarPath !== ''): ?>
                                    <img
                                        src="<?= e($gambarPath) ?>"
                                        alt="<?= e($item['nama_produk']) ?>"
                                        onerror="this.style.display='none';this.nextElementSibling.style.display='grid';">
                                    <div class="item-placeholder" style="display:none">🐾</div>
                                <?php else: ?>
                                    <div class="item-placeholder">🐾</div>
                                <?php endif; ?>

                                <div class="item-info">
                                    <strong><?= e($item['nama_produk']) ?></strong>
                                    <span>
                                        <?= rupiah($item['harga']) ?>
                                        × <?= (int)$item['jumlah'] ?>
                                    </span>
                                    <div class="item-subtotal">
                                        <?= rupiah($item['subtotal']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="summary-row">
                            <span>Jumlah jenis produk</span>
                            <strong><?= count($items) ?> jenis</strong>
                        </div>

                        <div class="summary-row">
                            <span>Subtotal produk</span>
                            <strong><?= rupiah($total) ?></strong>
                        </div>

                        <div class="summary-row">
                            <span>Pengiriman</span>
                            <strong>Dikonfirmasi admin</strong>
                        </div>

                        <div class="summary-row summary-total">
                            <span>Total produk</span>
                            <strong><?= rupiah($total) ?></strong>
                        </div>

                        <span class="form-hint">
                            Total belum termasuk ongkos kirim.
                        </span>

                        <button
                            type="submit"
                            name="buat_pesanan"
                            value="1"
                            class="btn-checkout">
                            Buat Pesanan ♡
                        </button>

                        <a href="keranjang.php" class="back-link">
                            ← Kembali ke Keranjang
                        </a>
                    </section>
                </aside>
            </div>
        </form>
    <?php endif; ?>
</main>

</body>
</html>