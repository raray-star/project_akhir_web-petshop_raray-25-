
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/koneksi.php';

/* =========================
   CEK LOGIN ADMIN
========================= */
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../login.php');
    exit;
}

/* =========================
   FUNGSI BANTU
========================= */
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('rupiah')) {
    function rupiah($angka) {
        return 'Rp' . number_format((float)$angka, 0, ',', '.');
    }
}

if (!function_exists('hitungData')) {
    function hitungData($koneksi, $tabel) {
        $allowed = [
            'produk',
            'kategori',
            'pesanan',
            'booking_grooming'
        ];

        if (!in_array($tabel, $allowed, true)) {
            return 0;
        }

        $result = mysqli_query(
            $koneksi,
            "SELECT COUNT(*) AS total FROM `$tabel`"
        );

        if (!$result) {
            return 0;
        }

        $row = mysqli_fetch_assoc($result);
        return (int)($row['total'] ?? 0);
    }
}

if (!function_exists('hitungStatus')) {
    function hitungStatus($koneksi, $kolom, $nilai) {
        $allowed = ['status', 'status_pembayaran'];

        if (!in_array($kolom, $allowed, true)) {
            return 0;
        }

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT COUNT(*) AS total
             FROM pesanan
             WHERE LOWER(TRIM(`$kolom`)) = LOWER(TRIM(?))"
        );

        if (!$stmt) {
            return 0;
        }

        mysqli_stmt_bind_param($stmt, 's', $nilai);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : [];

        mysqli_stmt_close($stmt);

        return (int)($row['total'] ?? 0);
    }
}

/* =========================
   NAMA ADMIN & SIDEBAR
========================= */
$namaAdmin = $_SESSION['nama'] ?? 'Admin PetShop';
$halamanAktif = basename($_SERVER['PHP_SELF']);

/* =========================
   STATISTIK PELANGGAN
========================= */
$totalPelanggan = 0;

$resultPelanggan = mysqli_query(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'pelanggan'"
);

if ($resultPelanggan) {
    $row = mysqli_fetch_assoc($resultPelanggan);
    $totalPelanggan = (int)($row['total'] ?? 0);
}

/* =========================
   STATISTIK UTAMA
========================= */
$totalProduk = hitungData($koneksi, 'produk');
$totalKategori = hitungData($koneksi, 'kategori');
$totalPesanan = hitungData($koneksi, 'pesanan');
$totalGrooming = hitungData($koneksi, 'booking_grooming');

$totalTransaksi = $totalPesanan + $totalGrooming;

$pesananLunas = hitungStatus(
    $koneksi,
    'status_pembayaran',
    'Lunas'
);

$pesananSelesai = hitungStatus(
    $koneksi,
    'status',
    'Selesai'
);

$totalMenunggu = hitungStatus(
    $koneksi,
    'status_pembayaran',
    'Menunggu Pembayaran'
);

$totalDibatalkan = hitungStatus(
    $koneksi,
    'status_pembayaran',
    'Dibatalkan'
);

/* =========================
   TOTAL PENDAPATAN SEPANJANG WAKTU
   PESANAN LUNAS + GROOMING SELESAI
========================= */
$totalPendapatan = 0;

/* Pesanan produk yang sudah lunas */
$sqlTotalPendapatanPesanan = "
    SELECT COALESCE(SUM(total), 0) AS total
    FROM pesanan
    WHERE LOWER(TRIM(status_pembayaran)) = 'lunas'
";

$resultTotalPendapatanPesanan = mysqli_query(
    $koneksi,
    $sqlTotalPendapatanPesanan
);

if ($resultTotalPendapatanPesanan) {
    $row = mysqli_fetch_assoc($resultTotalPendapatanPesanan);
    $totalPendapatan += (float)($row['total'] ?? 0);
}

/* Booking grooming yang sudah selesai */
$sqlTotalPendapatanGrooming = "
    SELECT COALESCE(SUM(total), 0) AS total
    FROM booking_grooming
    WHERE LOWER(TRIM(status)) = 'selesai'
";

$resultTotalPendapatanGrooming = mysqli_query(
    $koneksi,
    $sqlTotalPendapatanGrooming
);

if ($resultTotalPendapatanGrooming) {
    $row = mysqli_fetch_assoc($resultTotalPendapatanGrooming);
    $totalPendapatan += (float)($row['total'] ?? 0);
}

/* =========================
   DATA PENDAPATAN 12 BULAN
   PESANAN LUNAS + GROOMING SELESAI
========================= */
$bulanLabels = [];
$penjualanBulanan = [];
$jumlahPesananBulanan = [];

for ($i = 11; $i >= 0; $i--) {
    $timestamp = strtotime("-$i months");
    $key = date('Y-m', $timestamp);

    $bulanLabels[] = date('M', $timestamp);
    $penjualanBulanan[$key] = 0;
    $jumlahPesananBulanan[$key] = 0;
}

/*
 * Rentang 12 bulan kalender:
 * mulai tanggal 1 bulan 11 bulan lalu,
 * sampai tanggal 1 bulan berikutnya.
 */
$tanggalAwal = date(
    'Y-m-01',
    strtotime(date('Y-m-01') . ' -11 months')
);

$tanggalAkhir = date(
    'Y-m-01',
    strtotime(date('Y-m-01') . ' +1 month')
);

/* Pendapatan pesanan produk yang lunas */
$sqlPendapatanPesanan = "
    SELECT
        DATE_FORMAT(tanggal, '%Y-%m') AS bulan,
        COALESCE(SUM(total), 0) AS pendapatan,
        COUNT(*) AS jumlah
    FROM pesanan
    WHERE LOWER(TRIM(status_pembayaran)) = 'lunas'
      AND tanggal >= ?
      AND tanggal < ?
    GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
";

$stmtPendapatanPesanan = mysqli_prepare(
    $koneksi,
    $sqlPendapatanPesanan
);

if ($stmtPendapatanPesanan) {
    mysqli_stmt_bind_param(
        $stmtPendapatanPesanan,
        'ss',
        $tanggalAwal,
        $tanggalAkhir
    );

    mysqli_stmt_execute($stmtPendapatanPesanan);

    $resultPendapatanPesanan = mysqli_stmt_get_result(
        $stmtPendapatanPesanan
    );

    if ($resultPendapatanPesanan) {
        while ($row = mysqli_fetch_assoc($resultPendapatanPesanan)) {
            $key = $row['bulan'];

            if (isset($penjualanBulanan[$key])) {
                $penjualanBulanan[$key] += (float)$row['pendapatan'];
                $jumlahPesananBulanan[$key] += (int)$row['jumlah'];
            }
        }
    }

    mysqli_stmt_close($stmtPendapatanPesanan);
}

/* Pendapatan booking grooming yang selesai */
$sqlPendapatanGrooming = "
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS bulan,
        COALESCE(SUM(total), 0) AS pendapatan
    FROM booking_grooming
    WHERE LOWER(TRIM(status)) = 'selesai'
      AND created_at >= ?
      AND created_at < ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
";

$stmtPendapatanGrooming = mysqli_prepare(
    $koneksi,
    $sqlPendapatanGrooming
);

if ($stmtPendapatanGrooming) {
    mysqli_stmt_bind_param(
        $stmtPendapatanGrooming,
        'ss',
        $tanggalAwal,
        $tanggalAkhir
    );

    mysqli_stmt_execute($stmtPendapatanGrooming);

    $resultPendapatanGrooming = mysqli_stmt_get_result(
        $stmtPendapatanGrooming
    );

    if ($resultPendapatanGrooming) {
        while ($row = mysqli_fetch_assoc($resultPendapatanGrooming)) {
            $key = $row['bulan'];

            if (isset($penjualanBulanan[$key])) {
                $penjualanBulanan[$key] += (float)$row['pendapatan'];
            }
        }
    }

    mysqli_stmt_close($stmtPendapatanGrooming);
}

$penjualanChart = array_values($penjualanBulanan);
$pesananChart = array_values($jumlahPesananBulanan);

$bulanIni = date('Y-m');

$pendapatanBulanIni = $penjualanBulanan[$bulanIni] ?? 0;
$pesananBulanIni = $jumlahPesananBulanan[$bulanIni] ?? 0;

/* =========================
   DATA STATUS PESANAN + GROOMING
========================= */
$statusLabels = [];
$statusValues = [];

/* Status pesanan produk */
$sqlStatusPesanan = "
    SELECT
        COALESCE(
            NULLIF(TRIM(status), ''),
            'Belum diatur'
        ) AS nama_status,
        COUNT(*) AS jumlah
    FROM pesanan
    GROUP BY COALESCE(
        NULLIF(TRIM(status), ''),
        'Belum diatur'
    )
    ORDER BY jumlah DESC
";

$resultStatusPesanan = mysqli_query(
    $koneksi,
    $sqlStatusPesanan
);

if ($resultStatusPesanan) {
    while ($row = mysqli_fetch_assoc($resultStatusPesanan)) {
        $statusLabels[] = 'Pesanan - ' . $row['nama_status'];
        $statusValues[] = (int)$row['jumlah'];
    }
}

/* Status booking grooming */
$sqlStatusGrooming = "
    SELECT
        COALESCE(
            NULLIF(TRIM(status), ''),
            'Belum diatur'
        ) AS nama_status,
        COUNT(*) AS jumlah
    FROM booking_grooming
    GROUP BY COALESCE(
        NULLIF(TRIM(status), ''),
        'Belum diatur'
    )
    ORDER BY jumlah DESC
";

$resultStatusGrooming = mysqli_query(
    $koneksi,
    $sqlStatusGrooming
);

if ($resultStatusGrooming) {
    while ($row = mysqli_fetch_assoc($resultStatusGrooming)) {
        $statusLabels[] = 'Grooming - ' . $row['nama_status'];
        $statusValues[] = (int)$row['jumlah'];
    }
}

if (empty($statusLabels)) {
    $statusLabels = ['Belum ada transaksi'];
    $statusValues = [0];
}

/* =========================
   PESANAN TERBARU
========================= */
$resultTerbaru = mysqli_query(
    $koneksi,
    "SELECT
        id,
        nama_penerima,
        tanggal,
        total,
        status,
        status_pembayaran
     FROM pesanan
     ORDER BY tanggal DESC, id DESC
     LIMIT 7"
);

/* =========================
   DATA JSON UNTUK CHART.JS
========================= */
$bulanJson = json_encode(
    $bulanLabels,
    JSON_UNESCAPED_UNICODE
);

$penjualanJson = json_encode(
    $penjualanChart,
    JSON_NUMERIC_CHECK
);

$pesananJson = json_encode(
    $pesananChart,
    JSON_NUMERIC_CHECK
);

$statusLabelsJson = json_encode(
    $statusLabels,
    JSON_UNESCAPED_UNICODE
);

$statusValuesJson = json_encode(
    $statusValues,
    JSON_NUMERIC_CHECK
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin - PetShop</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        select {
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
            flex-shrink: 0;
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

        /* KONTEN */
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

        /* SECTION */
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
        .panel-heading h3 {
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

        /* KARTU STATISTIK */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
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
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .stat-card small {
            display: block;
            margin-top: 5px;
            color: #a09287;
            font-size: 10px;
        }

        /* GRAFIK */
        .chart-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.65fr) minmax(280px, 1fr);
            gap: 18px;
            margin-bottom: 20px;
        }

        .chart-card,
        .table-panel {
            min-width: 0;
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--white);
            box-shadow: var(--shadow);
        }

        .chart-summary {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            margin-bottom: 14px;
            padding: 13px 15px;
            border-radius: 11px;
            background: var(--cream);
        }

        .chart-summary span,
        .chart-summary small {
            color: var(--muted);
            font-size: 10px;
        }

        .chart-summary strong {
            color: var(--brown);
            font-size: 22px;
            font-weight: 900;
        }

        .chart-container {
            position: relative;
            width: 100%;
            height: 250px;
        }

        .sales-chart {
            height: 245px;
        }

        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 13px;
        }

        .chart-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
        }

        .chart-legend-color {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
        }

        .legend-orange {
            background: var(--orange);
        }

        .legend-gray {
            background: #9babbf;
        }

        .status-chart-card {
            display: flex;
            flex-direction: column;
        }

        .donut-wrap {
            position: relative;
            width: 100%;
            max-width: 230px;
            height: 210px;
            margin: 0 auto 15px;
        }

        .donut-wrap canvas {
            position: relative;
            z-index: 1;
        }

        .donut-center {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .donut-center strong {
            color: var(--brown);
            font-size: 27px;
            font-weight: 900;
        }

        .donut-center span {
            color: var(--muted);
            font-size: 10px;
        }

        .status-legend {
            display: flex;
            flex-direction: column;
            gap: 9px;
            max-height: 150px;
            overflow-y: auto;
            padding: 3px 0;
        }

        .status-legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .status-legend-dot {
            flex: 0 0 9px;
            width: 9px;
            height: 9px;
            border-radius: 50%;
        }

        .status-legend-name {
            flex: 1;
            overflow-wrap: anywhere;
            color: var(--muted);
            font-size: 10px;
        }

        .status-legend-item strong {
            color: var(--brown);
            font-size: 10px;
        }

        .status-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 16px;
            padding-top: 15px;
            border-top: 1px solid var(--line);
        }

        .status-summary div {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .status-summary strong {
            color: var(--brown);
            font-size: 19px;
            font-weight: 900;
        }

        .status-summary span {
            color: var(--muted);
            font-size: 10px;
        }

        /* GRAFIK PESANAN & STATISTIK MINI */
        .overview-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(220px, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .orders-chart-card {
            min-height: 300px;
        }

        .orders-chart {
            height: 235px;
        }

        .mini-stat-column {
            display: grid;
            grid-template-rows: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .mini-stat-card {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 8px;
            padding: 16px 18px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--white);
            box-shadow: var(--shadow);
        }

        .mini-stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
        }

        .mini-stat-icon {
            display: flex;
            width: 28px;
            height: 28px;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: var(--cream-dark);
            color: var(--brown);
            font-size: 14px;
        }

        .mini-stat-card > strong {
            overflow-wrap: anywhere;
            color: var(--brown);
            font-size: 21px;
            font-weight: 900;
        }

        .mini-stat-card p {
            margin: 0;
            color: #a09287;
            font-size: 10px;
        }

        /* TABEL PESANAN */
        .table-panel {
            padding: 0;
            overflow: hidden;
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
            padding: 15px 17px;
            border-bottom: 1px solid var(--line);
            color: var(--brown);
            text-align: left;
            font-size: 10px;
            font-weight: 900;
        }

        td {
            padding: 15px 17px;
            border-bottom: 1px solid #f5eee7;
            color: var(--muted);
            font-size: 11px;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        tbody tr:hover {
            background: #fffaf4;
        }

        td strong {
            color: var(--brown);
            font-weight: 900;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 900;
        }

        .status-pending {
            background: #fff1d8;
            color: #9a6a18;
        }

        .status-selesai,
        .status-lunas {
            background: #e8f6ec;
            color: #34784b;
        }

        .status-dibatalkan {
            background: #ffeded;
            color: #bf4848;
        }

        .status-diproses {
            background: #eaf2ff;
            color: #3e6fb0;
        }

        .table-empty {
            padding: 35px 15px;
            color: #a09287;
            text-align: center;
        }

        /* MENU PENGELOLAAN */
        .management-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .management-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            min-height: 185px;
            padding: 20px;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: var(--white);
            box-shadow: var(--shadow);
            transition: transform .2s ease, border-color .2s ease;
        }

        .management-card:hover {
            transform: translateY(-3px);
            border-color: #e5bd9d;
        }

        .management-icon {
            display: flex;
            width: 43px;
            height: 43px;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            background: var(--cream-dark);
            font-size: 21px;
        }

        .management-card h3 {
            margin: 13px 0 5px;
            color: var(--brown);
            font-size: 13px;
            font-weight: 900;
        }

        .management-card p {
            margin: 0 0 13px;
            color: var(--muted);
            font-size: 10px;
            line-height: 1.7;
        }

        .card-link {
            margin-top: auto;
            color: var(--orange);
            font-size: 10px;
            font-weight: 900;
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

            .chart-grid {
                grid-template-columns: minmax(0, 1.4fr) minmax(250px, 1fr);
            }

            .management-grid {
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

            .chart-grid,
            .overview-grid {
                grid-template-columns: 1fr;
            }

            .mini-stat-column {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                grid-template-rows: auto;
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

            .chart-card {
                padding: 16px;
            }

            .chart-container {
                height: 220px;
            }

            .mini-stat-column {
                grid-template-columns: 1fr;
            }

            .management-grid {
                grid-template-columns: 1fr;
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
            .welcome-art {
                display: none;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR ADMIN -->
<aside class="sidebar">
    <a href="dashboard.php" class="brand">
        <span class="brand-icon">🐾</span>
        <span class="brand-name">Pet<span>Shop</span></span>
        <small>Happy Pets, Happy Life</small>
    </a>

    <p class="menu-label">MENU ADMIN</p>

    <a
        href="dashboard.php"
        class="side-link <?= $halamanAktif === 'dashboard.php' ? 'active' : '' ?>"
    >
        <span class="side-icon">🏠</span>
        Dashboard
    </a>

    <a href="../index.php" class="side-link">
        <span class="side-icon">🌸</span>
        Lihat Website
    </a>

    <a
        href="produk.php"
        class="side-link <?= $halamanAktif === 'produk.php' ? 'active' : '' ?>"
    >
        <span class="side-icon">📦</span>
        Kelola Produk
    </a>

    <a
        href="kategori.php"
        class="side-link <?= $halamanAktif === 'kategori.php' ? 'active' : '' ?>"
    >
        <span class="side-icon">🏷️</span>
        Kelola Kategori
    </a>

    <a
        href="pesanan.php"
        class="side-link <?= $halamanAktif === 'pesanan.php' ? 'active' : '' ?>"
    >
        <span class="side-icon">🛒</span>
        Data Pesanan
    </a>

    <a
        href="booking_grooming.php"
        class="side-link <?= $halamanAktif === 'booking_grooming.php' ? 'active' : '' ?>"
    >
        <span class="side-icon">🛁</span>
        Booking Grooming
    </a>

    <a
        href="pelanggan.php"
        class="side-link <?= $halamanAktif === 'pelanggan.php' ? 'active' : '' ?>"
    >
        <span class="side-icon">👥</span>
        Data Pelanggan
    </a>

    <a href="../logout.php" class="side-link logout">
        <span class="side-icon">🚪</span>
        Logout
    </a>
</aside>

<!-- KONTEN DASHBOARD -->
<main class="dashboard-main">

    <header class="topbar">
        <div>
            <p class="eyebrow">PETSHOP ADMIN PANEL</p>
            <h1>Dashboard</h1>
            <p class="header-subtitle">
                Ringkasan aktivitas toko dan transaksi PetShop.
            </p>
        </div>

        <a href="../index.php" class="website-button">
            🌐 Lihat Website
        </a>
    </header>

    <!-- SAMBUTAN -->
    <section class="welcome-panel">
        <div class="welcome-content">
            <span class="welcome-tag">🐾 SELAMAT DATANG</span>

            <h2>Halo, <?= e($namaAdmin) ?>! ♡</h2>

            <p>
                Pantau produk, transaksi pelanggan, pembayaran,
                dan layanan grooming melalui dashboard PetShop.
            </p>
        </div>

        <div class="welcome-art">🐶</div>
    </section>

    <!-- STATISTIK UTAMA -->
    <section class="section-block">
        <div class="section-heading">
            <div>
                <h2>Ringkasan Data</h2>
                <p>Statistik berdasarkan data yang tersimpan.</p>
            </div>
        </div>

        <div class="stats-grid">

            <article class="stat-card">
                <div class="stat-icon icon-orange">👥</div>
                <div>
                    <p>Total Pelanggan</p>
                    <strong><?= number_format($totalPelanggan) ?></strong>
                    <small>Akun pelanggan</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-pink">📦</div>
                <div>
                    <p>Total Produk</p>
                    <strong><?= number_format($totalProduk) ?></strong>
                    <small>Produk terdaftar</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-cream">🛒</div>
                <div>
                    <p>Total Pesanan</p>
                    <strong><?= number_format($totalPesanan) ?></strong>
                    <small>Transaksi produk</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-pink">🛁</div>
                <div>
                    <p>Booking Grooming</p>
                    <strong><?= number_format($totalGrooming) ?></strong>
                    <small>Pemesanan layanan</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-cream">💰</div>
                <div>
                    <p>Total Pendapatan</p>
                    <strong style="font-size: 19px;">
                        <?= rupiah($totalPendapatan) ?>
                    </strong>
                    <small>Pesanan lunas + grooming selesai</small>
                </div>
            </article>

        </div>
    </section>

    <!-- GRAFIK PENJUALAN DAN STATUS -->
    <section class="chart-grid">

        <article class="chart-card">
            <div class="panel-heading">
                <div>
                    <h3>📈 Pendapatan 12 Bulan Terakhir</h3>
                    <p>Pendapatan pesanan lunas dan grooming selesai.</p>
                </div>
            </div>

            <div class="chart-summary">
                <span>Total pendapatan bulan ini</span>
                <strong><?= rupiah($pendapatanBulanIni) ?></strong>
                <small>
                    <?= number_format($pesananBulanIni) ?>
                    pesanan produk lunas bulan ini.
                    Pendapatan termasuk grooming selesai.
                </small>
            </div>

            <div class="chart-container sales-chart">
                <canvas id="grafikPenjualan"></canvas>
            </div>

            <div class="chart-legend">
                <span class="chart-legend-item">
                    <span class="chart-legend-color legend-orange"></span>
                    Pendapatan gabungan
                </span>

                <span class="chart-legend-item">
                    <span class="chart-legend-color legend-gray"></span>
                    Jumlah pesanan produk lunas
                </span>
            </div>
        </article>

        <article class="chart-card status-chart-card">
            <div class="panel-heading">
                <div>
                    <h3>📊 Status Pesanan & Grooming</h3>
                    <p>Perbandingan status pesanan produk dan booking grooming.</p>
                </div>
            </div>

            <div class="donut-wrap">
                <canvas id="grafikStatus"></canvas>

                <div class="donut-center">
                    <strong><?= number_format($totalTransaksi) ?></strong>
                    <span>Total Transaksi</span>
                </div>
            </div>

            <div class="status-legend" id="statusLegend"></div>

            <div class="status-summary">
                <div>
                    <strong><?= number_format($pesananSelesai) ?></strong>
                    <span>Pesanan produk selesai</span>
                </div>

                <div>
                    <strong><?= number_format($totalGrooming) ?></strong>
                    <span>Total booking grooming</span>
                </div>
            </div>
        </article>

    </section>

    <!-- AKTIVITAS PESANAN -->
    <section class="overview-grid">

        <article class="chart-card orders-chart-card">
            <div class="panel-heading">
                <div>
                    <h3>📦 Aktivitas Pesanan</h3>
                    <p>Jumlah pesanan produk lunas per bulan.</p>
                </div>
            </div>

            <div class="chart-container orders-chart">
                <canvas id="grafikJumlahPesanan"></canvas>
            </div>
        </article>

        <div class="mini-stat-column">

            <article class="mini-stat-card">
                <div class="mini-stat-top">
                    <span>💰 Pendapatan Bulan Ini</span>
                    <span class="mini-stat-icon">↗</span>
                </div>

                <strong><?= rupiah($pendapatanBulanIni) ?></strong>
                <p>Pesanan lunas dan grooming selesai bulan ini.</p>
            </article>

            <article class="mini-stat-card">
                <div class="mini-stat-top">
                    <span>💳 Menunggu Pembayaran</span>
                    <span class="mini-stat-icon">◷</span>
                </div>

                <strong><?= number_format($totalMenunggu) ?></strong>
                <p>Pesanan produk yang menunggu pembayaran.</p>
            </article>

            <article class="mini-stat-card">
                <div class="mini-stat-top">
                    <span>🛁 Booking Grooming</span>
                    <span class="mini-stat-icon">♡</span>
                </div>

                <strong><?= number_format($totalGrooming) ?></strong>
                <p>Total pemesanan layanan grooming.</p>
            </article>

        </div>
    </section>

    <!-- PESANAN TERBARU -->
    <section class="section-block">
        <div class="section-heading">
            <div>
                <h2>🧾 Pesanan Terbaru</h2>
                <p>Transaksi terbaru dari database PetShop.</p>
            </div>

            <a href="pesanan.php" class="website-button">
                Lihat Semua →
            </a>
        </div>

        <div class="table-panel">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pelanggan</th>
                            <th>Tanggal</th>
                            <th>Total</th>
                            <th>Status Pesanan</th>
                            <th>Status Pembayaran</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (
                        $resultTerbaru &&
                        mysqli_num_rows($resultTerbaru) > 0
                    ): ?>

                        <?php while ($p = mysqli_fetch_assoc($resultTerbaru)): ?>

                            <?php
                            $status = $p['status'] ?? 'Belum diatur';
                            $bayar = $p['status_pembayaran'] ?? 'Belum diatur';

                            $statusLower = strtolower(trim($status));
                            $bayarLower = strtolower(trim($bayar));

                            $classStatus = 'status-pending';

                            if ($statusLower === 'selesai') {
                                $classStatus = 'status-selesai';
                            } elseif ($statusLower === 'dibatalkan') {
                                $classStatus = 'status-dibatalkan';
                            } elseif (
                                in_array(
                                    $statusLower,
                                    ['diproses', 'dikirim'],
                                    true
                                )
                            ) {
                                $classStatus = 'status-diproses';
                            }

                            $classBayar = 'status-pending';

                            if (
                                in_array(
                                    $bayarLower,
                                    ['lunas', 'sudah dibayar'],
                                    true
                                )
                            ) {
                                $classBayar = 'status-lunas';
                            } elseif ($bayarLower === 'dibatalkan') {
                                $classBayar = 'status-dibatalkan';
                            }

                            $tanggalTampil = '-';

                            if (!empty($p['tanggal'])) {
                                $timestampTanggal = strtotime($p['tanggal']);

                                if ($timestampTanggal !== false) {
                                    $tanggalTampil = date(
                                        'd/m/Y H:i',
                                        $timestampTanggal
                                    );
                                }
                            }
                            ?>

                            <tr>
                                <td>
                                    <strong>#<?= e($p['id']) ?></strong>
                                </td>

                                <td>
                                    <?= e($p['nama_penerima'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= e($tanggalTampil) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= rupiah($p['total'] ?? 0) ?>
                                    </strong>
                                </td>

                                <td>
                                    <span class="status-badge <?= e($classStatus) ?>">
                                        <?= e($status) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status-badge <?= e($classBayar) ?>">
                                        <?= e($bayar) ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="table-empty">
                                Belum ada data pesanan.
                            </td>
                        </tr>

                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- MENU PENGELOLAAN -->
    <section class="section-block">
        <div class="section-heading">
            <div>
                <h2>⚙️ Menu Pengelolaan</h2>
                <p>Pilih menu untuk mengelola sistem PetShop.</p>
            </div>
        </div>

        <div class="management-grid">

            <a href="produk.php" class="management-card">
                <span class="management-icon">📦</span>
                <h3>Kelola Produk</h3>
                <p>
                    Tambah, edit, hapus produk, dan atur stok barang.
                </p>
                <span class="card-link">Buka Menu →</span>
            </a>

            <a href="kategori.php" class="management-card">
                <span class="management-icon">🏷️</span>
                <h3>Kelola Kategori</h3>
                <p>Atur kategori produk di PetShop.</p>
                <span class="card-link">Buka Menu →</span>
            </a>

            <a href="pesanan.php" class="management-card">
                <span class="management-icon">🛒</span>
                <h3>Data Pesanan</h3>
                <p>Pantau pembayaran dan proses pesanan.</p>
                <span class="card-link">Buka Menu →</span>
            </a>

            <a href="booking_grooming.php" class="management-card">
                <span class="management-icon">🛁</span>
                <h3>Booking Grooming</h3>
                <p>Kelola jadwal dan status grooming.</p>
                <span class="card-link">Buka Menu →</span>
            </a>

            <a href="pelanggan.php" class="management-card">
                <span class="management-icon">👥</span>
                <h3>Data Pelanggan</h3>
                <p>Lihat akun pelanggan yang terdaftar.</p>
                <span class="card-link">Buka Menu →</span>
            </a>

        </div>
    </section>

    <footer class="dashboard-footer">
        © <?= date('Y') ?> <strong>PetShop</strong>
        — Happy Pets, Happy Life ♡
    </footer>

</main>

<script>
const bulanLabels = <?= $bulanJson ?>;
const dataPenjualan = <?= $penjualanJson ?>;
const dataPesanan = <?= $pesananJson ?>;
const statusLabels = <?= $statusLabelsJson ?>;
const statusValues = <?= $statusValuesJson ?>;

Chart.defaults.font.family = "'Nunito', sans-serif";
Chart.defaults.color = '#76675d';

const formatRupiah = value =>
    'Rp' + Number(value).toLocaleString('id-ID');

const warnaStatus = [
    '#70432e',
    '#e97e35',
    '#4f8ac9',
    '#df6666',
    '#e9bd59',
    '#6eae88',
    '#a997c4',
    '#d48caa',
    '#68a9a3',
    '#b58b68',
    '#8c91cf',
    '#d6a85b'
];

/* GRAFIK PENDAPATAN GABUNGAN */
new Chart(document.getElementById('grafikPenjualan'), {
    type: 'line',

    data: {
        labels: bulanLabels,

        datasets: [
            {
                label: 'Pendapatan gabungan',
                data: dataPenjualan,
                borderColor: '#e97e35',
                backgroundColor: 'rgba(233,126,53,.13)',
                fill: true,
                tension: .35,
                borderWidth: 3,
                pointRadius: 3,
                pointHoverRadius: 6,
                yAxisID: 'y'
            },
            {
                label: 'Pesanan produk lunas',
                data: dataPesanan,
                borderColor: '#9babbf',
                backgroundColor: 'rgba(155,171,191,.1)',
                borderDash: [5, 4],
                fill: false,
                tension: .35,
                borderWidth: 2,
                pointRadius: 2,
                yAxisID: 'y1'
            }
        ]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,

        interaction: {
            mode: 'index',
            intersect: false
        },

        plugins: {
            legend: {
                display: false
            },

            tooltip: {
                callbacks: {
                    label(context) {
                        if (context.datasetIndex === 0) {
                            return 'Pendapatan: ' +
                                formatRupiah(context.raw);
                        }

                        return 'Pesanan produk lunas: ' + context.raw;
                    }
                }
            }
        },

        scales: {
            x: {
                grid: {
                    display: false
                }
            },

            y: {
                beginAtZero: true,

                ticks: {
                    maxTicksLimit: 5,

                    callback(value) {
                        return formatRupiah(value);
                    }
                }
            },

            y1: {
                beginAtZero: true,
                position: 'right',

                grid: {
                    drawOnChartArea: false
                },

                ticks: {
                    precision: 0,
                    maxTicksLimit: 5
                }
            }
        }
    }
});

/* DONUT STATUS PESANAN + GROOMING */
new Chart(document.getElementById('grafikStatus'), {
    type: 'doughnut',

    data: {
        labels: statusLabels,

        datasets: [{
            data: statusValues,

            backgroundColor: statusValues.map(
                (_, i) => warnaStatus[i % warnaStatus.length]
            ),

            borderColor: '#ffffff',
            borderWidth: 3,
            spacing: 2,
            hoverOffset: 5
        }]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',

        layout: {
            padding: 5
        },

        plugins: {
            legend: {
                display: false
            },

            tooltip: {
                callbacks: {
                    label(context) {
                        return context.label + ': ' +
                            Number(context.raw).toLocaleString('id-ID') +
                            ' transaksi';
                    }
                }
            }
        }
    }
});

/* LEGENDA STATUS */
const statusLegend = document.getElementById('statusLegend');

statusLabels.forEach((label, index) => {
    const item = document.createElement('div');
    item.className = 'status-legend-item';

    const dot = document.createElement('span');
    dot.className = 'status-legend-dot';
    dot.style.backgroundColor =
        warnaStatus[index % warnaStatus.length];

    const name = document.createElement('span');
    name.className = 'status-legend-name';
    name.textContent = label;

    const count = document.createElement('strong');
    count.textContent = Number(
        statusValues[index] || 0
    ).toLocaleString('id-ID');

    item.append(dot, name, count);
    statusLegend.appendChild(item);
});

/* GRAFIK BATANG JUMLAH PESANAN PRODUK LUNAS */
new Chart(document.getElementById('grafikJumlahPesanan'), {
    type: 'bar',

    data: {
        labels: bulanLabels,

        datasets: [{
            label: 'Pesanan produk lunas',
            data: dataPesanan,
            backgroundColor: '#e9b18d',
            hoverBackgroundColor: '#e97e35',
            borderRadius: 5,
            maxBarThickness: 28
        }]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
            legend: {
                display: false
            },

            tooltip: {
                callbacks: {
                    label(context) {
                        return 'Pesanan produk lunas: ' + context.raw;
                    }
                }
            }
        },

        scales: {
            x: {
                grid: {
                    display: false
                }
            },

            y: {
                beginAtZero: true,

                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>

</body>
</html>
