
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

/* =========================
   PENCARIAN PELANGGAN
========================= */
$cari = trim($_GET['cari'] ?? '');
$error = '';
$daftarPelanggan = [];

if ($cari !== '') {
    $kata = '%' . $cari . '%';

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id, nama, email, no_hp, alamat, created_at
         FROM users
         WHERE role = 'pelanggan'
           AND (nama LIKE ? OR email LIKE ?)
         ORDER BY id DESC"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $kata, $kata);

        if (mysqli_stmt_execute($stmt)) {
            $hasil = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($hasil)) {
                $daftarPelanggan[] = $row;
            }
        } else {
            $error = 'Data pelanggan gagal dimuat. Silakan coba kembali.';
        }

        mysqli_stmt_close($stmt);
    } else {
        $error = 'Data pelanggan gagal dimuat. Periksa struktur database.';
    }
} else {
    $hasil = mysqli_query(
        $koneksi,
        "SELECT id, nama, email, no_hp, alamat, created_at
         FROM users
         WHERE role = 'pelanggan'
         ORDER BY id DESC"
    );

    if ($hasil) {
        while ($row = mysqli_fetch_assoc($hasil)) {
            $daftarPelanggan[] = $row;
        }
    } else {
        $error = 'Data pelanggan gagal dimuat. Periksa struktur database.';
    }
}

$totalPelanggan = count($daftarPelanggan);

/* =========================
   STATISTIK TOTAL PELANGGAN
========================= */
$totalSemuaPelanggan = 0;

$queryTotal = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'pelanggan'"
);

if ($queryTotal) {
    $dataTotal = mysqli_fetch_assoc($queryTotal);
    $totalSemuaPelanggan = (int)($dataTotal['total'] ?? 0);
}

/* =========================
   HALAMAN AKTIF SIDEBAR
========================= */
$halamanAktif = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Pelanggan - PetShop Admin</title>

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

        /* =========================
           SIDEBAR
           SAMA DENGAN produk.php
        ========================= */
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

        /* =========================
           KONTEN UTAMA
        ========================= */
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
            line-height: 1.7;
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

        /* =========================
           BANNER
        ========================= */
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
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: 20px;
            background: rgba(255, 255, 255, .1);
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
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 32px;
            background: rgba(255, 255, 255, .1);
            font-size: 57px;
            transform: rotate(4deg);
        }

        /* =========================
           RINGKASAN
        ========================= */
        .section-block {
            margin-bottom: 28px;
        }

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 15px;
        }

        .section-heading h2 {
            margin: 0;
            color: var(--brown);
            font-size: 16px;
            font-weight: 900;
        }

        .section-heading p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 11px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
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
            box-shadow: 0 12px 28px rgba(112, 67, 46, .1);
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

        .icon-green {
            background: #e8f6ec;
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

        /* =========================
           CARD DAN TABEL
        ========================= */
        .card {
            min-width: 0;
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--white);
            box-shadow: var(--shadow);
        }

        .panel-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .panel-heading h2 {
            margin: 0;
            color: var(--brown);
            font-size: 16px;
            font-weight: 900;
        }

        .panel-heading p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        .count-label {
            flex-shrink: 0;
            padding: 7px 11px;
            border-radius: 20px;
            background: var(--cream-dark);
            color: var(--brown);
            font-size: 10px;
            font-weight: 900;
        }

        /* =========================
           PENCARIAN
        ========================= */
        .table-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 18px;
        }

        .search-form {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            max-width: 470px;
        }

        .search-form input {
            width: 100%;
            min-width: 0;
            min-height: 40px;
            padding: 10px 12px;
            border: 1px solid #e8dbce;
            border-radius: 10px;
            outline: none;
            background: #fffdfb;
            color: var(--text);
            font-size: 11px;
            transition: border-color .2s, box-shadow .2s;
        }

        .search-form input:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px rgba(233, 126, 53, .12);
        }

        .search-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            min-height: 40px;
            padding: 10px 14px;
            border: 0;
            border-radius: 10px;
            background: var(--brown);
            color: var(--white);
            cursor: pointer;
            font-size: 11px;
            font-weight: 900;
            transition: .2s ease;
        }

        .search-button:hover {
            background: var(--brown-dark);
        }

        .search-clear {
            flex-shrink: 0;
            color: var(--muted);
            font-size: 11px;
            font-weight: 900;
        }

        .search-clear:hover {
            color: var(--orange);
        }

        /* =========================
           TABEL PELANGGAN
        ========================= */
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
            padding: 15px 17px;
            border-bottom: 1px solid var(--line);
            color: var(--brown);
            text-align: left;
            font-size: 10px;
            font-weight: 900;
        }

        td {
            padding: 13px 17px;
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

        .number-badge {
            display: inline-flex;
            min-width: 30px;
            align-items: center;
            justify-content: center;
            padding: 6px 9px;
            border-radius: 8px;
            background: var(--cream-dark);
            color: var(--brown);
            font-size: 10px;
            font-weight: 900;
        }

        .customer-name {
            color: var(--brown);
            font-weight: 900;
        }

        .customer-email {
            color: #8b6b56;
        }

        .customer-phone {
            color: var(--text);
            font-weight: 800;
        }

        .customer-address {
            display: block;
            max-width: 260px;
            overflow: hidden;
            color: var(--muted);
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .date-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 9px;
            border-radius: 8px;
            background: #f8f0e8;
            color: #8b6b56;
            font-size: 10px;
            font-weight: 800;
        }

        /* =========================
           PESAN DAN DATA KOSONG
        ========================= */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
            padding: 13px 15px;
            border: 1px solid #f2d7d4;
            border-radius: 10px;
            background: #fff0ee;
            color: #a63e3e;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.7;
        }

        .empty-state {
            padding: 40px 15px;
            text-align: center;
            white-space: normal;
        }

        .empty-icon {
            margin-bottom: 10px;
            font-size: 36px;
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
            line-height: 1.7;
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

        /* =========================
           RESPONSIVE
           SAMA DENGAN produk.php
        ========================= */
        @media (max-width: 1200px) {
            .dashboard-main {
                padding: 25px 22px;
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

            .table-toolbar {
                align-items: flex-start;
            }

            .search-form {
                max-width: none;
            }

            .section-heading {
                align-items: flex-start;
            }
        }

        @media (max-width: 380px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .welcome-art {
                display: none;
            }

            .search-form {
                flex-wrap: wrap;
            }

            .search-form input {
                flex: 1 1 100%;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR: SAMA DENGAN produk.php -->
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

<!-- KONTEN UTAMA -->
<main class="dashboard-main">

    <header class="topbar">
        <div>
            <p class="eyebrow">PETSHOP ADMIN PANEL</p>
            <h1>Data Pelanggan</h1>
            <p class="header-subtitle">
                Kelola dan lihat informasi akun pelanggan yang terdaftar di PetShop.
            </p>
        </div>

        <a href="../index.php" class="website-button">
            🌐 Lihat Website
        </a>
    </header>

    <!-- BANNER -->
    <section class="welcome-panel">
        <div class="welcome-content">
            <span class="welcome-tag">🐾 MANAJEMEN PELANGGAN</span>
            <h2>Data Pelanggan PetShop 👥</h2>
            <p>
                Lihat informasi pelanggan yang telah terdaftar,
                mulai dari nama, email, nomor HP, hingga tanggal pendaftaran.
            </p>
        </div>

        <div class="welcome-art">🐶</div>
    </section>

    <!-- RINGKASAN -->
    <section class="section-block">
        <div class="section-heading">
            <div>
                <h2>Ringkasan Pelanggan</h2>
                <p>Informasi jumlah pelanggan yang terdaftar di database.</p>
            </div>
        </div>

        <div class="stats-grid">
            <article class="stat-card">
                <div class="stat-icon icon-orange">👥</div>
                <div>
                    <p>Total Pelanggan</p>
                    <strong><?= number_format($totalSemuaPelanggan, 0, ',', '.') ?></strong>
                    <small>Seluruh akun pelanggan</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-green">📋</div>
                <div>
                    <p>Hasil Ditampilkan</p>
                    <strong><?= number_format($totalPelanggan, 0, ',', '.') ?></strong>
                    <small>
                        <?= $cari !== '' ? 'Hasil pencarian' : 'Data pelanggan terbaru' ?>
                    </small>
                </div>
            </article>
        </div>
    </section>

    <!-- DAFTAR PELANGGAN -->
    <section class="section-block">
        <div class="section-heading">
            <div>
                <h2>Daftar Pelanggan</h2>
                <p>Lihat informasi akun pelanggan yang sudah terdaftar.</p>
            </div>

            <span class="count-label">
                <?= number_format($totalPelanggan, 0, ',', '.') ?> pelanggan
            </span>
        </div>

        <div class="card">
            <div class="panel-heading">
                <div>
                    <h2>Data Akun Pelanggan</h2>
                    <p>
                        Cari pelanggan berdasarkan nama atau alamat email.
                    </p>
                </div>
            </div>

            <!-- FORM PENCARIAN -->
            <form action="pelanggan.php" method="GET" class="search-form"
                  style="max-width: 520px; margin-bottom: 20px;">

                <input
                    type="text"
                    name="cari"
                    placeholder="🔍 Cari nama atau email pelanggan..."
                    value="<?= e($cari) ?>"
                    aria-label="Cari nama atau email pelanggan"
                >

                <button type="submit" class="search-button">
                    🔍 Cari
                </button>

                <?php if ($cari !== ''): ?>
                    <a href="pelanggan.php" class="search-clear">
                        Reset
                    </a>
                <?php endif; ?>
            </form>

            <?php if ($error !== ''): ?>

                <div class="alert">
                    <span>⚠️</span>
                    <span><?= e($error) ?></span>
                </div>

            <?php elseif ($totalPelanggan === 0): ?>

                <div class="empty-state">
                    <div class="empty-icon">👥</div>

                    <strong>
                        <?= $cari !== ''
                            ? 'Pelanggan Tidak Ditemukan'
                            : 'Belum Ada Pelanggan' ?>
                    </strong>

                    <p>
                        <?= $cari !== ''
                            ? 'Coba gunakan kata kunci nama atau email yang berbeda.'
                            : 'Belum ada akun pelanggan yang terdaftar di database.' ?>
                    </p>
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama Pelanggan</th>
                                <th>Email</th>
                                <th>No. HP</th>
                                <th>Alamat</th>
                                <th>Tanggal Daftar</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($daftarPelanggan as $index => $row): ?>
                                <tr>
                                    <td>
                                        <span class="number-badge">
                                            <?= $index + 1 ?>
                                        </span>
                                    </td>

                                    <td>
                                        <strong class="customer-name">
                                            <?= e($row['nama']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <span class="customer-email">
                                            <?= e($row['email']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="customer-phone">
                                            <?= e($row['no_hp'] ?: '-') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span
                                            class="customer-address"
                                            title="<?= e($row['alamat'] ?: '-') ?>"
                                        >
                                            <?= e($row['alamat'] ?: '-') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="date-badge">
                                            📅 <?= e($row['created_at'] ?: '-') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="dashboard-footer">
        © <?= date('Y') ?> <strong>PetShop</strong>
        — Happy Pets, Happy Life ♡
    </footer>

</main>

</body>
</html>
