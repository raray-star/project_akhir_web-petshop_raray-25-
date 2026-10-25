
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
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiah($nilai)
{
    return 'Rp' . number_format((float)$nilai, 0, ',', '.');
}

/* =========================
   CLASS STATUS
========================= */
function statusClass($status)
{
    $status = strtolower(trim((string)$status));

    if (in_array($status, ['selesai', 'completed', 'complete'], true)) {
        return 'selesai';
    }

    if (in_array($status, ['dibatalkan', 'batal', 'cancelled', 'canceled'], true)) {
        return 'dibatalkan';
    }

    if (
        strpos($status, 'bayar') !== false ||
        strpos($status, 'pembayaran') !== false ||
        strpos($status, 'menunggu') !== false ||
        $status === 'pending'
    ) {
        return 'menunggu';
    }

    return 'diproses';
}

/* =========================
   PILIHAN STATUS
========================= */
$pilihanStatus = [
    'Menunggu Pembayaran',
    'Selesai',
    'Dibatalkan'
];

$error = '';
$sukses = $_SESSION['pesan_booking'] ?? '';
unset($_SESSION['pesan_booking']);

/* =========================
   PROSES UBAH STATUS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'ubah_status') {
        $idBooking = (int)($_POST['id_booking'] ?? 0);
        $statusBaru = trim($_POST['status'] ?? '');

        if ($idBooking <= 0) {
            $error = 'ID booking tidak valid.';
        } elseif (!in_array($statusBaru, $pilihanStatus, true)) {
            $error = 'Status booking yang dipilih tidak valid.';
        } else {
            $stmt = mysqli_prepare(
                $koneksi,
                'SELECT id FROM booking_grooming WHERE id = ?'
            );

            if (!$stmt) {
                $error = 'Query pemeriksaan booking gagal disiapkan.';
            } else {
                mysqli_stmt_bind_param($stmt, 'i', $idBooking);

                if (!mysqli_stmt_execute($stmt)) {
                    $error = 'Data booking gagal diperiksa.';
                    mysqli_stmt_close($stmt);
                } else {
                    $hasilCek = mysqli_stmt_get_result($stmt);

                    $bookingAda = $hasilCek &&
                        mysqli_num_rows($hasilCek) > 0;

                    mysqli_stmt_close($stmt);

                    if (!$bookingAda) {
                        $error = 'Data booking tidak ditemukan.';
                    } else {
                        $stmt = mysqli_prepare(
                            $koneksi,
                            'UPDATE booking_grooming
                             SET status = ?
                             WHERE id = ?'
                        );

                        if (!$stmt) {
                            $error = 'Query perubahan status gagal disiapkan.';
                        } else {
                            mysqli_stmt_bind_param(
                                $stmt,
                                'si',
                                $statusBaru,
                                $idBooking
                            );

                            try {
                                if (mysqli_stmt_execute($stmt)) {
                                    mysqli_stmt_close($stmt);

                                    $_SESSION['pesan_booking'] =
                                        'Status booking berhasil diperbarui.';

                                    header('Location: booking_grooming.php');
                                    exit;
                                }

                                $error = 'Status booking gagal diperbarui.';
                                mysqli_stmt_close($stmt);
                            } catch (mysqli_sql_exception $ex) {
                                error_log(
                                    'Gagal mengubah status booking: ' .
                                    $ex->getMessage()
                                );

                                mysqli_stmt_close($stmt);

                                $error =
                                    'Status gagal diperbarui. Pastikan ' .
                                    'pilihan status sesuai dengan database.';
                            }
                        }
                    }
                }
            }
        }
    }
}

/* =========================
   AMBIL DATA BOOKING
========================= */
$daftarBooking = [];
$hasil = false;

try {
    $hasil = mysqli_query(
        $koneksi,
        'SELECT * FROM booking_grooming ORDER BY id DESC'
    );

    if ($hasil) {
        while ($row = mysqli_fetch_assoc($hasil)) {
            $daftarBooking[] = $row;
        }
    }
} catch (mysqli_sql_exception $ex) {
    error_log('Gagal mengambil booking: ' . $ex->getMessage());

    $error = 'Data booking belum bisa ditampilkan. Periksa tabel booking_grooming.';
}

$totalBooking = count($daftarBooking);
$totalSelesai = 0;
$totalMenunggu = 0;
$totalDiproses = 0;
$totalDibatalkan = 0;

foreach ($daftarBooking as $booking) {
    $status = strtolower(trim((string)($booking['status'] ?? '')));

    if (in_array($status, ['selesai', 'completed', 'complete'], true)) {
        $totalSelesai++;
    } elseif (
        in_array(
            $status,
            ['dibatalkan', 'batal', 'cancelled', 'canceled'],
            true
        )
    ) {
        $totalDibatalkan++;
    } elseif (
        strpos($status, 'bayar') !== false ||
        strpos($status, 'pembayaran') !== false ||
        strpos($status, 'menunggu') !== false ||
        $status === 'pending'
    ) {
        $totalMenunggu++;
    } else {
        $totalDiproses++;
    }
}

$halamanAktif = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Booking Grooming - PetShop Admin</title>

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

        button, input, select {
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

        /* TOMBOL LIHAT WEBSITE - KANAN ATAS */
        .website-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 16px;
            border: 1px solid var(--line);
            border-radius: 11px;
            background: var(--white);
            color: var(--brown);
            font-size: 11px;
            font-weight: 900;
            white-space: nowrap;
            text-decoration: none;
            transition: .2s ease;
        }

        .website-button:hover {
            background: var(--brown);
            border-color: var(--brown);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 5px 13px rgba(112, 67, 46, .15);
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

        .section-heading {
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

        .icon-orange { background: #fff0df; }
        .icon-green { background: #e8f6ec; }
        .icon-blue { background: #eaf2ff; }
        .icon-red { background: #ffe8e6; }

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

        /* ALERT */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
            padding: 13px 15px;
            border: 1px solid transparent;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.6;
        }

        .alert.success {
            border-color: #d5ead9;
            background: #edf8ef;
            color: #32734e;
        }

        .alert.error {
            border-color: #f2d7d4;
            background: #fff0ee;
            color: #a63e3e;
        }

        /* TABEL */
        .card {
            min-width: 0;
            margin-bottom: 24px;
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--white);
            box-shadow: var(--shadow);
        }

        .card-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .card-heading h2 {
            margin: 0;
            color: var(--brown);
            font-size: 16px;
            font-weight: 900;
        }

        .card-heading p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 11px;
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

        .table-wrap {
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

        .id-badge {
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

        /* BADGE STATUS */
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 900;
        }

        .status-menunggu {
            background: #fff1dc;
            color: #a66b19;
        }

        .status-diproses {
            background: #eaf2ff;
            color: #3e6fb0;
        }

        .status-selesai {
            background: #e8f6ec;
            color: #34784b;
        }

        .status-dibatalkan {
            background: #ffeded;
            color: #bf4848;
        }

        /* FORM STATUS */
        .status-form {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .status-select {
            min-width: 155px;
            min-height: 35px;
            padding: 7px 9px;
            border: 1px solid #e8dbce;
            border-radius: 9px;
            outline: none;
            background: #fffdfb;
            color: var(--text);
            font-size: 11px;
            font-weight: 800;
        }

        .status-select:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px rgba(233,126,53,.12);
        }

        .btn-save {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 35px;
            padding: 8px 11px;
            border: 0;
            border-radius: 9px;
            background: var(--brown);
            color: var(--white);
            cursor: pointer;
            font-size: 10px;
            font-weight: 900;
            transition: .2s ease;
        }

        .btn-save:hover {
            background: var(--brown-dark);
            transform: translateY(-1px);
        }

        .empty {
            padding: 45px 15px;
            text-align: center;
            white-space: normal;
        }

        .empty span {
            display: block;
            margin-bottom: 10px;
            font-size: 38px;
        }

        .empty strong {
            display: block;
            color: var(--brown);
            font-size: 14px;
            font-weight: 900;
        }

        .empty p {
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

            .website-button {
                padding: 9px 10px;
                font-size: 10px;
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

            .status-form {
                align-items: flex-start;
                flex-direction: column;
            }

            .status-select,
            .btn-save {
                width: 100%;
            }
        }

        @media (max-width: 380px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .welcome-art {
                display: none;
            }

            .topbar {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body>

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

<main class="dashboard-main">

    <header class="topbar">
        <div>
            <p class="eyebrow">PETSHOP ADMIN PANEL</p>
            <h1>Booking Grooming 🛁</h1>
            <p class="header-subtitle">
                Kelola booking dan jadwal perawatan hewan pelanggan PetShop.
            </p>
        </div>

        <!-- TOMBOL LIHAT WEBSITE DI KANAN ATAS -->
        <a href="../index.php" class="website-button">
            🌐 Lihat Website
        </a>
    </header>

    <section class="welcome-panel">
        <div class="welcome-content">
            <span class="welcome-tag">🐾 MANAJEMEN GROOMING</span>
            <h2>Booking Grooming 🛁</h2>
            <p>
                Pantau jadwal perawatan hewan, periksa data booking,
                dan ubah status booking sesuai proses layanan.
            </p>
        </div>

        <div class="welcome-art">🐶</div>
    </section>

    <?php if ($sukses !== ''): ?>
        <div class="alert success">
            <span>✅</span>
            <span><?= e($sukses) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert error">
            <span>⚠️</span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <section class="section-block">
        <div class="section-heading">
            <h2>📊 Ringkasan Booking Grooming</h2>
            <p>Statistik berdasarkan status booking yang tersimpan.</p>
        </div>

        <div class="stats-grid">
            <article class="stat-card">
                <div class="stat-icon icon-orange">🛁</div>
                <div>
                    <p>Total Booking</p>
                    <strong><?= number_format($totalBooking, 0, ',', '.') ?></strong>
                    <small>Seluruh booking</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-green">✅</div>
                <div>
                    <p>Booking Selesai</p>
                    <strong><?= number_format($totalSelesai, 0, ',', '.') ?></strong>
                    <small>Perawatan selesai</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-blue">📅</div>
                <div>
                    <p>Dalam Proses</p>
                    <strong><?= number_format($totalDiproses, 0, ',', '.') ?></strong>
                    <small>Menunggu proses layanan</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-red">⏳</div>
                <div>
                    <p>Menunggu</p>
                    <strong><?= number_format($totalMenunggu, 0, ',', '.') ?></strong>
                    <small>Menunggu konfirmasi atau pembayaran</small>
                </div>
            </article>
        </div>
    </section>

    <section class="card">
        <div class="card-heading">
            <div>
                <h2>Daftar Booking Pelanggan</h2>
                <p>Lihat informasi booking dan perbarui statusnya.</p>
            </div>

            <span class="count-label">
                <?= number_format($totalBooking, 0, ',', '.') ?> Booking
            </span>
        </div>

        <?php if ($hasil === false): ?>
            <div class="alert error">
                Data booking belum bisa ditampilkan.
                Pastikan tabel <strong>booking_grooming</strong>
                tersedia dan dapat diakses.
            </div>

        <?php elseif ($totalBooking === 0): ?>
            <div class="empty">
                <span>🐾</span>
                <strong>Belum Ada Booking</strong>
                <p>Belum ada booking grooming yang tercatat di database.</p>
            </div>

        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>

                            <?php foreach (array_keys($daftarBooking[0]) as $column): ?>
                                <th><?= e(ucwords(str_replace('_', ' ', $column))) ?></th>
                            <?php endforeach; ?>

                            <th>Ubah Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($daftarBooking as $index => $row): ?>
                            <tr>
                                <td>
                                    <span class="id-badge">
                                        <?= $index + 1 ?>
                                    </span>
                                </td>

                                <?php foreach ($row as $column => $value): ?>
                                    <td>
                                        <?php if (strtolower((string)$column) === 'status'): ?>
                                            <span class="status-badge status-<?= e(statusClass($value)) ?>">
                                                <?= e($value) ?>
                                            </span>

                                        <?php elseif (
                                            is_numeric($value) &&
                                            in_array(
                                                strtolower((string)$column),
                                                ['total', 'harga', 'amount'],
                                                true
                                            )
                                        ): ?>
                                            <?= rupiah($value) ?>

                                        <?php else: ?>
                                            <?= e($value) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>

                                <td>
                                    <form
                                        method="POST"
                                        action="booking_grooming.php"
                                        class="status-form"
                                    >
                                        <input
                                            type="hidden"
                                            name="aksi"
                                            value="ubah_status"
                                        >

                                        <input
                                            type="hidden"
                                            name="id_booking"
                                            value="<?= (int)($row['id'] ?? 0) ?>"
                                        >

                                        <select
                                            name="status"
                                            class="status-select"
                                            aria-label="Pilih status booking"
                                            required
                                        >
                                            <?php
                                            $statusSekarang = trim(
                                                (string)($row['status'] ?? '')
                                            );

                                            $opsiTampil = $pilihanStatus;

                                            /*
                                             * Status lama selain Diproses
                                             * tetap ditampilkan jika diperlukan.
                                             */
                                            if (
                                                $statusSekarang !== '' &&
                                                strcasecmp($statusSekarang, 'Diproses') !== 0 &&
                                                !in_array(
                                                    $statusSekarang,
                                                    $opsiTampil,
                                                    true
                                                )
                                            ) {
                                                array_unshift(
                                                    $opsiTampil,
                                                    $statusSekarang
                                                );
                                            }
                                            ?>

                                            <?php foreach ($opsiTampil as $pilihan): ?>
                                                <option
                                                    value="<?= e($pilihan) ?>"
                                                    <?= $statusSekarang === $pilihan ? 'selected' : '' ?>
                                                >
                                                    <?= e($pilihan) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button type="submit" class="btn-save">
                                            💾 Simpan
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <footer class="dashboard-footer">
        © <?= date('Y') ?> <strong>PetShop</strong>
        — Happy Pets, Happy Life ♡
    </footer>

</main>

</body>
</html>
