
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/koneksi.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../login.php');
    exit;
}

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($angka)
{
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}

function statusClass($status)
{
    $status = strtolower(trim((string)$status));

    switch ($status) {
        case 'selesai':
        case 'lunas':
            return 'status-success';

        case 'diproses':
        case 'dikirim':
            return 'status-process';

        case 'dibatalkan':
            return 'status-danger';

        case 'menunggu':
        case 'belum bayar':
        case 'menunggu pembayaran':
        case 'sudah dibayar':
        default:
            return 'status-pending';
    }
}

/* DAFTAR STATUS PESANAN */
$daftarStatus = [
    'Menunggu',
    'Diproses',
    'Dikirim',
    'Selesai',
    'Dibatalkan'
];

/* DAFTAR STATUS PEMBAYARAN: HANYA 3 PILIHAN */
$daftarPembayaran = [
    'Menunggu',
    'Belum Bayar',
    'Lunas'
];

$pesan = '';
$jenisPesan = 'success';

/* PROSES UPDATE STATUS PESANAN */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_status'])
) {
    $id = filter_var(
        $_POST['id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $status = trim((string)($_POST['status'] ?? ''));
    $statusPembayaran = trim(
        (string)($_POST['status_pembayaran'] ?? '')
    );

    if (!$id || $id < 1) {
        $pesan = 'ID pesanan tidak valid.';
        $jenisPesan = 'error';
    } elseif (
        !in_array($status, $daftarStatus, true) ||
        !in_array($statusPembayaran, $daftarPembayaran, true)
    ) {
        $pesan = 'Status pesanan atau pembayaran tidak valid.';
        $jenisPesan = 'error';
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE pesanan
             SET status = ?, status_pembayaran = ?
             WHERE id = ?"
        );

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                'ssi',
                $status,
                $statusPembayaran,
                $id
            );

            if (mysqli_stmt_execute($stmt)) {
                $pesan = 'Status pesanan berhasil diperbarui.';
            } else {
                error_log(
                    'Gagal memperbarui pesanan: ' .
                    mysqli_stmt_error($stmt)
                );

                $pesan = 'Gagal memperbarui status pesanan. Periksa nilai status pada database.';
                $jenisPesan = 'error';
            }

            mysqli_stmt_close($stmt);
        } else {
            error_log(
                'Gagal menyiapkan update pesanan: ' .
                mysqli_error($koneksi)
            );

            $pesan = 'Terjadi kesalahan saat menyiapkan pembaruan data.';
            $jenisPesan = 'error';
        }
    }
}

/* AMBIL DATA PESANAN */
$dataPesanan = [];

$queryPesanan = mysqli_query(
    $koneksi,
    "SELECT * FROM pesanan ORDER BY id DESC"
);

$errorQuery = false;

if ($queryPesanan) {
    while ($row = mysqli_fetch_assoc($queryPesanan)) {
        $dataPesanan[] = $row;
    }
} else {
    $errorQuery = true;
    error_log(
        'Gagal mengambil data pesanan: ' .
        mysqli_error($koneksi)
    );
}

/* STATISTIK PESANAN */
$totalPesanan = count($dataPesanan);
$totalSelesai = 0;
$totalProses = 0;
$totalMenunggu = 0;
$totalPendapatan = 0;

foreach ($dataPesanan as $row) {
    $status = strtolower(
        trim((string)($row['status'] ?? ''))
    );

    if ($status === 'selesai') {
        $totalSelesai++;
        $totalPendapatan += (float)($row['total'] ?? 0);
    }

    if (in_array($status, ['diproses', 'dikirim'], true)) {
        $totalProses++;
    }

    if ($status === 'menunggu') {
        $totalMenunggu++;
    }
}

/* HALAMAN AKTIF SIDEBAR */
$halamanAktif = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Pesanan - PetShop Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <style>
        :root {
            --brown: #70432e;
            --brown-dark: #59321f;
            --orange: #e97e35;
            --cream: #fff8ee;
            --cream-dark: #fff0dc;
            --text: #403129;
            --muted: #76675d;
            --line: #f0e5d9;
            --white: #ffffff;
            --green: #4f9870;
            --red: #d95f5f;
            --blue: #4f8ac9;
            --shadow: 0 8px 25px rgba(112, 67, 46, .07);
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--cream);
            color: var(--text);
            font-family: 'Nunito', sans-serif;
            font-size: 14px;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        /* SIDEBAR */

        .sidebar {
            width: 225px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            overflow-y: auto;
            padding: 25px 16px;
            background: var(--white);
            border-right: 1px solid var(--line);
        }

        .brand {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0 8px;
            padding: 0 9px 25px;
            color: var(--brown);
        }

        .brand-icon {
            display: inline-flex;
            width: 42px;
            height: 42px;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            background: var(--cream-dark);
            font-size: 23px;
        }

        .brand-name {
            font-size: 23px;
            font-weight: 900;
            letter-spacing: -.8px;
        }

        .brand-name span {
            color: var(--orange);
        }

        .brand small {
            display: block;
            width: 100%;
            padding: 5px 0 0 50px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
        }

        .menu-label {
            margin: 8px 10px 12px;
            color: #a09287;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1.3px;
        }

        .side-link {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 44px;
            margin: 5px 0;
            padding: 11px 13px;
            border-radius: 12px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            transition: .2s ease;
        }

        .side-link:hover {
            background: var(--cream);
            color: var(--brown);
        }

        .side-link.active {
            background: var(--brown);
            color: var(--white);
            box-shadow: 0 5px 13px rgba(112, 67, 46, .16);
        }

        .side-icon {
            width: 22px;
            text-align: center;
            font-size: 16px;
        }

        .side-link.logout {
            margin-top: 25px;
            color: var(--red);
        }

        .side-link.logout:hover {
            background: #fff0ed;
        }

        /* KONTEN UTAMA */

        .dashboard-main {
            min-height: 100vh;
            margin-left: 225px;
            padding: 28px 32px 20px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .eyebrow {
            margin: 0 0 5px;
            color: var(--orange);
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 1.7px;
        }

        .topbar h1 {
            margin: 0;
            color: var(--brown);
            font-size: 27px;
            font-weight: 900;
            letter-spacing: -.6px;
        }

        .header-subtitle {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .website-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 39px;
            padding: 10px 15px;
            border: 1px solid #ead7c5;
            border-radius: 10px;
            background: var(--white);
            color: var(--brown);
            font-size: 11px;
            font-weight: 900;
            white-space: nowrap;
            transition: .2s ease;
        }

        .website-button:hover {
            border-color: var(--orange);
            background: var(--cream-dark);
        }

        /* BANNER */

        .welcome-panel {
            position: relative;
            display: flex;
            min-height: 180px;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            overflow: hidden;
            margin-bottom: 28px;
            padding: 28px 32px;
            border-radius: 19px;
            background: linear-gradient(
                115deg,
                var(--brown-dark),
                var(--brown) 65%,
                #8b5b3c
            );
            color: var(--white);
            box-shadow: var(--shadow);
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            max-width: 620px;
        }

        .welcome-tag {
            display: inline-block;
            padding: 6px 10px;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 20px;
            background: rgba(255,255,255,.1);
            color: #ffe2c6;
            font-size: 9px;
            font-weight: 900;
            letter-spacing: 1px;
        }

        .welcome-content h2 {
            margin: 12px 0 7px;
            color: var(--white);
            font-size: 25px;
            font-weight: 900;
        }

        .welcome-content p {
            max-width: 520px;
            margin: 0;
            color: #f5e5d8;
            font-size: 12px;
            line-height: 1.8;
        }

        .welcome-art {
            display: flex;
            flex: 0 0 112px;
            width: 112px;
            height: 112px;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 32px;
            background: rgba(255,255,255,.1);
            font-size: 57px;
            transform: rotate(4deg);
        }

        /* STATISTIK */

        .section-block {
            margin-bottom: 28px;
        }

        .section-heading,
        .panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 15px;
        }

        .section-heading h2,
        .panel-heading h2 {
            margin: 0;
            color: var(--brown);
            font-size: 16px;
            font-weight: 900;
        }

        .section-heading p,
        .panel-heading p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 11px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
            padding: 19px 16px;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: var(--white);
            box-shadow: var(--shadow);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(112,67,46,.1);
        }

        .stat-icon {
            display: flex;
            flex: 0 0 45px;
            width: 45px;
            height: 45px;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            font-size: 20px;
        }

        .icon-orange {
            background: #fff0df;
        }

        .icon-pink {
            background: #fff0ed;
        }

        .icon-cream {
            background: #f8f0df;
        }

        .icon-red {
            background: #ffe8e6;
        }

        .stat-card p {
            margin: 0 0 4px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
        }

        .stat-card strong {
            display: block;
            color: var(--brown);
            font-size: 24px;
            font-weight: 900;
            line-height: 1.2;
        }

        .stat-card small {
            display: block;
            margin-top: 5px;
            color: #a09287;
            font-size: 10px;
        }

        /* TABEL PESANAN */

        .card {
            min-width: 0;
            margin-bottom: 24px;
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--white);
            box-shadow: var(--shadow);
        }

        .panel-heading {
            margin-bottom: 20px;
        }

        .pesanan-count {
            flex-shrink: 0;
            padding: 7px 11px;
            border-radius: 20px;
            background: var(--cream-dark);
            color: var(--brown);
            font-size: 10px;
            font-weight: 900;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        thead {
            background: var(--cream);
        }

        th {
            padding: 15px 14px;
            border-bottom: 1px solid var(--line);
            color: var(--brown);
            text-align: left;
            font-size: 10px;
            font-weight: 900;
        }

        td {
            padding: 14px;
            border-bottom: 1px solid #f5eee7;
            color: var(--muted);
            font-size: 11px;
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #fffaf4;
        }

        .order-number {
            color: var(--brown);
            font-weight: 900;
        }

        .customer-name {
            display: block;
            max-width: 180px;
            overflow: hidden;
            color: var(--brown);
            font-weight: 900;
            text-overflow: ellipsis;
        }

        .customer-phone {
            display: block;
            margin-top: 4px;
            color: #a09287;
            font-size: 10px;
        }

        .address-cell {
            width: 210px;
            max-width: 210px;
            white-space: normal;
            line-height: 1.6;
        }

        .order-total {
            color: var(--brown);
            font-weight: 900;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 900;
        }

        .status-success {
            background: #e8f6ec;
            color: #34784b;
        }

        .status-process {
            background: #eaf2ff;
            color: #3e6fb0;
        }

        .status-pending {
            background: #fff0df;
            color: #a86624;
        }

        .status-danger {
            background: #ffeded;
            color: #bf4848;
        }

        .status-form {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 155px;
        }

        .status-form select {
            width: 100%;
            min-height: 35px;
            padding: 7px 9px;
            border: 1px solid #e8dbce;
            border-radius: 8px;
            outline: none;
            background: #fffdfb;
            color: var(--text);
            font-size: 10px;
        }

        .status-form select:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px rgba(233,126,53,.12);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 35px;
            padding: 8px 12px;
            border: 1px solid transparent;
            border-radius: 9px;
            cursor: pointer;
            font-size: 10px;
            font-weight: 900;
            transition: .2s ease;
        }

        .btn-primary {
            background: var(--brown);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--brown-dark);
            transform: translateY(-1px);
        }

        /* NOTIFIKASI */

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
            padding: 13px 16px;
            border: 1px solid transparent;
            border-radius: 11px;
            font-size: 12px;
            font-weight: 800;
        }

        .alert-success {
            border-color: #cfe9d5;
            background: #edf8ef;
            color: #34784b;
        }

        .alert-error {
            border-color: #f4d0cc;
            background: #fff0ed;
            color: #bf4848;
        }

        .empty-state {
            padding: 45px 15px;
            text-align: center;
            white-space: normal;
        }

        .empty-icon {
            margin-bottom: 10px;
            font-size: 38px;
        }

        .empty-state strong {
            display: block;
            color: var(--brown);
            font-size: 14px;
            font-weight: 900;
        }

        .empty-state p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 11px;
        }

        .dashboard-footer {
            padding: 20px 0 5px;
            border-top: 1px solid var(--line);
            color: #a09287;
            text-align: center;
            font-size: 10px;
        }

        .dashboard-footer strong {
            color: var(--brown);
        }

        /* RESPONSIVE */

        @media (max-width: 1200px) {
            .dashboard-main {
                padding: 25px 22px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .sidebar {
                width: 200px;
                padding-right: 12px;
                padding-left: 12px;
            }

            .dashboard-main {
                margin-left: 200px;
            }
        }

        @media (max-width: 650px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                padding: 14px;
                border-right: none;
                border-bottom: 1px solid var(--line);
            }

            .brand {
                padding-bottom: 12px;
            }

            .menu-label {
                margin-top: 5px;
            }

            .side-link {
                display: inline-flex;
                min-height: 36px;
                margin: 3px 2px;
                padding: 8px 10px;
            }

            .side-link.logout {
                margin-top: 3px;
            }

            .dashboard-main {
                margin-left: 0;
                padding: 20px 14px;
            }

            .topbar {
                align-items: flex-start;
            }

            .topbar h1 {
                font-size: 23px;
            }

            .topbar .website-button {
                display: none;
            }

            .welcome-panel {
                min-height: 160px;
                padding: 22px;
            }

            .welcome-content h2 {
                font-size: 21px;
            }

            .welcome-art {
                flex-basis: 65px;
                width: 65px;
                height: 65px;
                border-radius: 20px;
                font-size: 35px;
            }

            .stats-grid {
                gap: 10px;
            }

            .stat-card {
                gap: 9px;
                padding: 13px 10px;
            }

            .stat-icon {
                flex-basis: 34px;
                width: 34px;
                height: 34px;
                font-size: 16px;
            }

            .stat-card strong {
                font-size: 20px;
            }

            .stat-card p {
                font-size: 9px;
            }

            .card {
                padding: 16px;
            }

            .section-heading {
                align-items: flex-start;
            }

            .section-heading .website-button {
                padding: 9px;
                font-size: 10px;
            }
        }

        @media (max-width: 380px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .welcome-art {
                display: none;
            }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->

<aside class="sidebar">
    <a href="dashboard.php" class="brand">
        <span class="brand-icon">🐾</span>
        <span class="brand-name">Pet<span>Shop</span></span>
        <small>Happy Pets, Happy Life</small>
    </a>

    <p class="menu-label">MENU ADMIN</p>

    <a href="dashboard.php"
       class="side-link <?= $halamanAktif === 'dashboard.php' ? 'active' : '' ?>">
        <span class="side-icon">🏠</span> Dashboard
    </a>

    <a href="../index.php" class="side-link">
        <span class="side-icon">🌸</span> Lihat Website
    </a>

    <a href="produk.php"
       class="side-link <?= $halamanAktif === 'produk.php' ? 'active' : '' ?>">
        <span class="side-icon">📦</span> Kelola Produk
    </a>

    <a href="kategori.php"
       class="side-link <?= $halamanAktif === 'kategori.php' ? 'active' : '' ?>">
        <span class="side-icon">🏷️</span> Kelola Kategori
    </a>

    <a href="pesanan.php"
       class="side-link <?= $halamanAktif === 'pesanan.php' ? 'active' : '' ?>">
        <span class="side-icon">🛒</span> Data Pesanan
    </a>

    <a href="booking_grooming.php"
       class="side-link <?= $halamanAktif === 'booking_grooming.php' ? 'active' : '' ?>">
        <span class="side-icon">🛁</span> Booking Grooming
    </a>

    <a href="pelanggan.php"
       class="side-link <?= $halamanAktif === 'pelanggan.php' ? 'active' : '' ?>">
        <span class="side-icon">👥</span> Data Pelanggan
    </a>

    <a href="../logout.php" class="side-link logout">
        <span class="side-icon">🚪</span> Logout
    </a>
</aside>

<!-- KONTEN PESANAN -->

<main class="dashboard-main">

    <header class="topbar">
        <div>
            <p class="eyebrow">PETSHOP ADMIN PANEL</p>
            <h1>Data Pesanan</h1>
            <p class="header-subtitle">
                Pantau pesanan pelanggan dan kelola status pembayaran PetShop.
            </p>
        </div>

        <a href="../index.php" class="website-button">
            🌐 Lihat Website
        </a>
    </header>

    <!-- BANNER -->

    <section class="welcome-panel">
        <div class="welcome-content">
            <span class="welcome-tag">🐾 MANAJEMEN PESANAN</span>
            <h2>Kelola Pesanan Pelanggan 🛒</h2>
            <p>
                Periksa detail pesanan, pantau pembayaran, dan perbarui
                status pesanan pelanggan dengan mudah.
            </p>
        </div>

        <div class="welcome-art">📦</div>
    </section>

    <!-- NOTIFIKASI -->

    <?php if ($pesan !== ''): ?>
        <div class="alert <?= $jenisPesan === 'error' ? 'alert-error' : 'alert-success' ?>">
            <span><?= $jenisPesan === 'error' ? '⚠️' : '✅' ?></span>
            <span><?= e($pesan) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($errorQuery): ?>
        <div class="alert alert-error">
            <span>⚠️</span>
            <span>
                Data pesanan gagal dimuat. Periksa tabel pesanan dan koneksi database.
            </span>
        </div>
    <?php endif; ?>

    <!-- STATISTIK -->

    <section class="section-block">
        <div class="section-heading">
            <div>
                <h2>Ringkasan Pesanan</h2>
                <p>Statistik berdasarkan data pesanan yang tersimpan.</p>
            </div>
        </div>

        <div class="stats-grid">

            <article class="stat-card">
                <div class="stat-icon icon-orange">🛒</div>
                <div>
                    <p>Total Pesanan</p>
                    <strong><?= number_format($totalPesanan, 0, ',', '.') ?></strong>
                    <small>Semua pesanan</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-pink">⏳</div>
                <div>
                    <p>Menunggu</p>
                    <strong><?= number_format($totalMenunggu, 0, ',', '.') ?></strong>
                    <small>Perlu ditindaklanjuti</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-cream">📦</div>
                <div>
                    <p>Dalam Proses</p>
                    <strong><?= number_format($totalProses, 0, ',', '.') ?></strong>
                    <small>Diproses atau dikirim</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-red">✅</div>
                <div>
                    <p>Selesai</p>
                    <strong><?= number_format($totalSelesai, 0, ',', '.') ?></strong>
                    <small>Pesanan telah selesai</small>
                </div>
            </article>

        </div>
    </section>

    <!-- DAFTAR PESANAN -->

    <section class="section-block">

        <div class="section-heading">
            <div>
                <h2>Daftar Pesanan</h2>
                <p>
                    Lihat informasi pelanggan dan perbarui status setiap pesanan.
                </p>
            </div>

            <span class="pesanan-count">
                <?= number_format($totalPesanan, 0, ',', '.') ?> Pesanan
            </span>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-responsive">

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pelanggan</th>
                            <th>Alamat</th>
                            <th>Tanggal</th>
                            <th>Total</th>
                            <th>Pembayaran</th>
                            <th>Status Pesanan</th>
                            <th>Ubah Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!$errorQuery && $totalPesanan > 0): ?>

                        <?php foreach ($dataPesanan as $row): ?>

                            <?php
                            $statusSaatIni = trim(
                                (string)($row['status'] ?? 'Menunggu')
                            );

                            $pembayaranSaatIni = trim(
                                (string)($row['status_pembayaran'] ?? 'Menunggu')
                            );

                            /*
                             * Normalisasi data pembayaran lama.
                             * Nilai lama tetap ditampilkan dengan pilihan
                             * yang sesuai pada dropdown baru.
                             */
                            if (
                                strcasecmp(
                                    $pembayaranSaatIni,
                                    'Sudah Dibayar'
                                ) === 0
                            ) {
                                $pembayaranSaatIni = 'Lunas';
                            } elseif (
                                strcasecmp(
                                    $pembayaranSaatIni,
                                    'Menunggu Pembayaran'
                                ) === 0
                            ) {
                                $pembayaranSaatIni = 'Menunggu';
                            } elseif (
                                strcasecmp(
                                    $pembayaranSaatIni,
                                    'Belum Dibayar'
                                ) === 0
                            ) {
                                $pembayaranSaatIni = 'Belum Bayar';
                            }

                            /*
                             * Daftar pilihan status pesanan.
                             */
                            $opsiStatus = $daftarStatus;

                            if (
                                $statusSaatIni !== '' &&
                                !in_array($statusSaatIni, $opsiStatus, true)
                            ) {
                                $opsiStatus[] = $statusSaatIni;
                            }

                            /*
                             * Dropdown pembayaran hanya berisi:
                             * Menunggu, Belum Bayar, dan Lunas.
                             */
                            $opsiPembayaran = $daftarPembayaran;
                            ?>

                            <tr>
                                <td>
                                    <span class="order-number">
                                        #<?= e($row['id'] ?? '-') ?>
                                    </span>
                                </td>

                                <td>
                                    <strong class="customer-name">
                                        <?= e($row['nama_penerima'] ?? '-') ?>
                                    </strong>

                                    <span class="customer-phone">
                                        📞 <?= e($row['no_hp'] ?? '-') ?>
                                    </span>
                                </td>

                                <td class="address-cell">
                                    <?= e($row['alamat'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= e($row['tanggal'] ?? '-') ?>
                                </td>

                                <td>
                                    <span class="order-total">
                                        <?= rupiah($row['total'] ?? 0) ?>
                                    </span>
                                </td>

                                <td>
                                    <div>
                                        <?= e($row['metode_pembayaran'] ?? '-') ?>
                                    </div>

                                    <span class="status-badge <?= statusClass($pembayaranSaatIni) ?>">
                                        <?= e($pembayaranSaatIni) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status-badge <?= statusClass($statusSaatIni) ?>">
                                        <?= e($statusSaatIni) ?>
                                    </span>
                                </td>

                                <td>
                                    <form
                                        action="pesanan.php"
                                        method="POST"
                                        class="status-form"
                                    >
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= e($row['id'] ?? '') ?>"
                                        >

                                        <select
                                            name="status"
                                            aria-label="Status pesanan"
                                            required
                                        >
                                            <?php foreach ($opsiStatus as $opsi): ?>
                                                <option
                                                    value="<?= e($opsi) ?>"
                                                    <?= $statusSaatIni === $opsi ? 'selected' : '' ?>
                                                >
                                                    <?= e($opsi) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <select
                                            name="status_pembayaran"
                                            aria-label="Status pembayaran"
                                            required
                                        >
                                            <?php foreach ($opsiPembayaran as $opsi): ?>
                                                <option
                                                    value="<?= e($opsi) ?>"
                                                    <?= $pembayaranSaatIni === $opsi ? 'selected' : '' ?>
                                                >
                                                    <?= e($opsi) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button
                                            type="submit"
                                            name="update_status"
                                            value="1"
                                            class="btn btn-primary"
                                        >
                                            💾 Simpan Perubahan
                                        </button>
                                    </form>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="empty-state">
                                <div class="empty-icon">🛒</div>

                                <strong>
                                    <?= $errorQuery
                                        ? 'Data Pesanan Tidak Dapat Ditampilkan'
                                        : 'Belum Ada Pesanan' ?>
                                </strong>

                                <p>
                                    <?= $errorQuery
                                        ? 'Silakan periksa database dan coba muat ulang halaman.'
                                        : 'Pesanan pelanggan akan muncul di halaman ini.' ?>
                                </p>
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>
                </table>

            </div>
        </div>

    </section>

    <footer class="dashboard-footer">
        © <?= date('Y') ?> <strong>PetShop</strong>
        — Happy Pets, Happy Life ♡
    </footer>

</main>

</body>
</html>
