```php
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

function kembaliKategori($query = '')
{
    header('Location: kategori.php' . $query);
    exit;
}

$error = '';
$sukses = $_SESSION['pesan_kategori'] ?? '';
unset($_SESSION['pesan_kategori']);

/* =========================
   PROSES TAMBAH, EDIT, HAPUS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah' || $aksi === 'edit') {
        $namaKategori = trim($_POST['nama_kategori'] ?? '');
        $idKategori = (int)($_POST['id_kategori'] ?? 0);

        if ($namaKategori === '') {
            $error = 'Nama kategori wajib diisi.';
        } elseif (mb_strlen($namaKategori) > 100) {
            $error = 'Nama kategori maksimal 100 karakter.';
        } elseif ($aksi === 'edit' && $idKategori <= 0) {
            $error = 'ID kategori tidak valid.';
        } else {
            /* Periksa nama kategori duplikat */
            if ($aksi === 'tambah') {
                $stmt = mysqli_prepare(
                    $koneksi,
                    'SELECT id
                     FROM kategori
                     WHERE nama_kategori = ?'
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    's',
                    $namaKategori
                );
            } else {
                $stmt = mysqli_prepare(
                    $koneksi,
                    'SELECT id
                     FROM kategori
                     WHERE nama_kategori = ?
                       AND id != ?'
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    'si',
                    $namaKategori,
                    $idKategori
                );
            }

            mysqli_stmt_execute($stmt);
            $hasilCek = mysqli_stmt_get_result($stmt);
            $duplikat = mysqli_num_rows($hasilCek) > 0;
            mysqli_stmt_close($stmt);

            if ($duplikat) {
                $error = 'Nama kategori tersebut sudah digunakan.';
            } elseif ($aksi === 'tambah') {
                $stmt = mysqli_prepare(
                    $koneksi,
                    'INSERT INTO kategori (nama_kategori)
                     VALUES (?)'
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    's',
                    $namaKategori
                );

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);

                    $_SESSION['pesan_kategori'] =
                        'Kategori berhasil ditambahkan.';

                    kembaliKategori();
                } else {
                    $error = 'Kategori gagal ditambahkan.';
                    mysqli_stmt_close($stmt);
                }
            } else {
                /* Pastikan kategori yang diedit tersedia */
                $stmt = mysqli_prepare(
                    $koneksi,
                    'SELECT id
                     FROM kategori
                     WHERE id = ?'
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    'i',
                    $idKategori
                );

                mysqli_stmt_execute($stmt);
                $hasilKategori = mysqli_stmt_get_result($stmt);
                $kategoriAda = mysqli_num_rows($hasilKategori) > 0;
                mysqli_stmt_close($stmt);

                if (!$kategoriAda) {
                    $error = 'Kategori yang ingin diedit tidak ditemukan.';
                } else {
                    $stmt = mysqli_prepare(
                        $koneksi,
                        'UPDATE kategori
                         SET nama_kategori = ?
                         WHERE id = ?'
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        'si',
                        $namaKategori,
                        $idKategori
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);

                        $_SESSION['pesan_kategori'] =
                            'Kategori berhasil diperbarui.';

                        kembaliKategori();
                    } else {
                        $error = 'Kategori gagal diperbarui.';
                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }
    } elseif ($aksi === 'hapus') {
        $idKategori = (int)($_POST['id_kategori'] ?? 0);

        if ($idKategori <= 0) {
            $error = 'ID kategori tidak valid.';
        } else {
            /* Pastikan kategori tersedia */
            $stmt = mysqli_prepare(
                $koneksi,
                'SELECT id
                 FROM kategori
                 WHERE id = ?'
            );

            mysqli_stmt_bind_param(
                $stmt,
                'i',
                $idKategori
            );

            mysqli_stmt_execute($stmt);
            $hasilKategori = mysqli_stmt_get_result($stmt);
            $kategoriAda = mysqli_num_rows($hasilKategori) > 0;
            mysqli_stmt_close($stmt);

            if (!$kategoriAda) {
                $error = 'Kategori tidak ditemukan.';
            } else {
                /* Cek apakah kategori masih digunakan produk */
                $stmt = mysqli_prepare(
                    $koneksi,
                    'SELECT COUNT(*) AS jumlah
                     FROM produk
                     WHERE kategori_id = ?'
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    'i',
                    $idKategori
                );

                mysqli_stmt_execute($stmt);
                $hasilProduk = mysqli_stmt_get_result($stmt);
                $dataProduk = mysqli_fetch_assoc($hasilProduk);
                $jumlahProduk = (int)($dataProduk['jumlah'] ?? 0);
                mysqli_stmt_close($stmt);

                if ($jumlahProduk > 0) {
                    $error =
                        'Kategori tidak dapat dihapus karena masih digunakan oleh produk.';
                } else {
                    $stmt = mysqli_prepare(
                        $koneksi,
                        'DELETE FROM kategori
                         WHERE id = ?'
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        'i',
                        $idKategori
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);

                        $_SESSION['pesan_kategori'] =
                            'Kategori berhasil dihapus.';

                        kembaliKategori();
                    } else {
                        $error = 'Kategori gagal dihapus.';
                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }
    }
}

/* =========================
   PENCARIAN
========================= */
$cari = trim($_GET['cari'] ?? '');

/* =========================
   DATA KATEGORI UNTUK EDIT
========================= */
$dataEdit = null;
$isEdit = false;

if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];

    if ($idEdit > 0) {
        $stmt = mysqli_prepare(
            $koneksi,
            'SELECT id, nama_kategori
             FROM kategori
             WHERE id = ?'
        );

        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $idEdit
        );

        mysqli_stmt_execute($stmt);
        $hasilEdit = mysqli_stmt_get_result($stmt);
        $dataEdit = mysqli_fetch_assoc($hasilEdit);
        mysqli_stmt_close($stmt);
    }

    if ($dataEdit) {
        $isEdit = true;
    } else {
        $error = 'Kategori yang ingin diedit tidak ditemukan.';
    }
}

/* =========================
   DAFTAR KATEGORI
========================= */
$daftarKategori = [];

if ($cari !== '') {
    $keyword = '%' . $cari . '%';

    $stmt = mysqli_prepare(
        $koneksi,
        'SELECT
            k.id,
            k.nama_kategori,
            (
                SELECT COUNT(*)
                FROM produk p
                WHERE p.kategori_id = k.id
            ) AS jumlah_produk
         FROM kategori k
         WHERE k.nama_kategori LIKE ?
         ORDER BY k.id DESC'
    );

    mysqli_stmt_bind_param(
        $stmt,
        's',
        $keyword
    );
} else {
    $stmt = mysqli_prepare(
        $koneksi,
        'SELECT
            k.id,
            k.nama_kategori,
            (
                SELECT COUNT(*)
                FROM produk p
                WHERE p.kategori_id = k.id
            ) AS jumlah_produk
         FROM kategori k
         ORDER BY k.id DESC'
    );
}

mysqli_stmt_execute($stmt);
$hasilDaftar = mysqli_stmt_get_result($stmt);

while ($baris = mysqli_fetch_assoc($hasilDaftar)) {
    $daftarKategori[] = $baris;
}

mysqli_stmt_close($stmt);

/* =========================
   STATISTIK KATEGORI
========================= */
$totalKategori = 0;
$totalProduk = 0;
$totalKategoriTerisi = 0;
$totalKategoriKosong = 0;

$queryStatistik = mysqli_query(
    $koneksi,
    'SELECT
        COUNT(*) AS total_kategori,
        COALESCE(SUM(jumlah_produk), 0) AS total_produk,
        COALESCE(SUM(
            CASE WHEN jumlah_produk > 0 THEN 1 ELSE 0 END
        ), 0) AS kategori_terisi,
        COALESCE(SUM(
            CASE WHEN jumlah_produk = 0 THEN 1 ELSE 0 END
        ), 0) AS kategori_kosong
     FROM (
        SELECT
            k.id,
            (
                SELECT COUNT(*)
                FROM produk p
                WHERE p.kategori_id = k.id
            ) AS jumlah_produk
        FROM kategori k
     ) AS statistik'
);

if ($queryStatistik) {
    $statistik = mysqli_fetch_assoc($queryStatistik);

    $totalKategori = (int)($statistik['total_kategori'] ?? 0);
    $totalProduk = (int)($statistik['total_produk'] ?? 0);
    $totalKategoriTerisi = (int)($statistik['kategori_terisi'] ?? 0);
    $totalKategoriKosong = (int)($statistik['kategori_kosong'] ?? 0);
}

$totalDitampilkan = count($daftarKategori);
$halamanAktif = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kelola Kategori - PetShop Admin</title>

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

        /* SIDEBAR — SAMA PERSIS DENGAN produk.php */
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

        /* BAGIAN DAN STATISTIK */
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

        .icon-orange { background: #fff0df; }
        .icon-pink { background: #fff0ed; }
        .icon-cream { background: #f8f0df; }
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

        /* PANEL DAN FORM */
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
            gap: 7px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            color: var(--brown);
            font-size: 11px;
            font-weight: 900;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            min-height: 43px;
            padding: 11px 13px;
            border: 1px solid #e8dbce;
            border-radius: 10px;
            outline: none;
            background: #fffdfb;
            color: var(--text);
            font-size: 12px;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px rgba(233,126,53,.12);
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 40px;
            padding: 10px 16px;
            border: 1px solid transparent;
            border-radius: 10px;
            cursor: pointer;
            font-size: 11px;
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

        .btn-secondary {
            border-color: var(--line);
            background: var(--cream);
            color: var(--brown);
        }

        .btn-secondary:hover {
            background: var(--cream-dark);
        }

        /* PESAN */
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

        .alert-success {
            border-color: #d5ead9;
            background: #edf8ef;
            color: #32734e;
        }

        .alert-error {
            border-color: #f2d7d4;
            background: #fff0ee;
            color: #a63e3e;
        }

        /* PENCARIAN */
        .produk-count {
            flex-shrink: 0;
            padding: 7px 11px;
            border-radius: 20px;
            background: var(--cream-dark);
            color: var(--brown);
            font-size: 10px;
            font-weight: 900;
        }

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
            max-width: 410px;
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
            font-size: 11px;
        }

        .search-form input:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px rgba(233,126,53,.12);
        }

        .search-button {
            min-height: 40px;
            padding: 10px 13px;
            border: 0;
            border-radius: 10px;
            background: var(--brown);
            color: var(--white);
            cursor: pointer;
            font-size: 11px;
            font-weight: 900;
        }

        .search-button:hover {
            background: var(--brown-dark);
        }

        .search-clear {
            color: var(--muted);
            font-size: 11px;
            font-weight: 900;
        }

        .search-clear:hover {
            color: var(--orange);
        }

        /* TABEL KATEGORI */
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

        .category-name {
            color: var(--brown);
            font-weight: 900;
        }

        .category-id {
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

        .product-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 20px;
            background: #e8f6ec;
            color: #34784b;
            font-size: 10px;
            font-weight: 900;
        }

        .product-badge.empty {
            background: #f8f0e8;
            color: #9b7961;
        }

        .category-actions {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .category-inline-form {
            display: inline-flex;
            margin: 0;
        }

        .btn-edit,
        .btn-delete {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 7px 10px;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 10px;
            font-weight: 900;
            transition: .2s ease;
        }

        .btn-edit {
            background: #eaf2ff;
            color: #3e6fb0;
        }

        .btn-edit:hover {
            background: #dbe9ff;
        }

        .btn-delete {
            background: #ffeded;
            color: #bf4848;
        }

        .btn-delete:hover {
            background: #ffdddd;
        }

        .produk-empty {
            padding: 40px 15px;
            text-align: center;
            white-space: normal;
        }

        .produk-empty-icon {
            margin-bottom: 10px;
            font-size: 36px;
        }

        .produk-empty strong {
            display: block;
            color: var(--brown);
            font-size: 14px;
            font-weight: 900;
        }

        .produk-empty p {
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

        /* RESPONSIVE — SAMA DENGAN produk.php */
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .section-heading {
                align-items: flex-start;
            }

            .search-form {
                max-width: none;
            }
        }

        @media (max-width: 380px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .welcome-art {
                display: none;
            }

            .form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR — HTML SAMA DENGAN produk.php -->
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
            <h1>Kelola Kategori</h1>
            <p class="header-subtitle">
                Atur kategori produk agar katalog PetShop lebih rapi.
            </p>
        </div>

        <a href="../index.php" class="website-button">
            🌐 Lihat Website
        </a>
    </header>

    <!-- BANNER -->
    <section class="welcome-panel">
        <div class="welcome-content">
            <span class="welcome-tag">🐾 MANAJEMEN KATEGORI</span>
            <h2>Kelola Kategori Produk 🏷️</h2>
            <p>
                Tambahkan dan atur kategori produk agar pelanggan
                lebih mudah menemukan produk yang mereka butuhkan.
            </p>
        </div>

        <div class="welcome-art">🐶</div>
    </section>

    <!-- PESAN SUKSES -->
    <?php if ($sukses !== ''): ?>
        <div class="alert alert-success">
            <span>✅</span>
            <span><?= e($sukses) ?></span>
        </div>
    <?php endif; ?>

    <!-- PESAN ERROR -->
    <?php if ($error !== ''): ?>
        <div class="alert alert-error">
            <span>⚠️</span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- STATISTIK -->
    <section class="section-block">
        <div class="section-heading">
            <div>
                <h2>Ringkasan Kategori</h2>
                <p>Statistik kategori dan produk yang tersimpan.</p>
            </div>
        </div>

        <div class="stats-grid">
            <article class="stat-card">
                <div class="stat-icon icon-orange">🏷️</div>
                <div>
                    <p>Total Kategori</p>
                    <strong><?= number_format($totalKategori, 0, ',', '.') ?></strong>
                    <small>Kategori terdaftar</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-pink">📋</div>
                <div>
                    <p>Ditampilkan</p>
                    <strong><?= number_format($totalDitampilkan, 0, ',', '.') ?></strong>
                    <small>Hasil daftar kategori</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-cream">📦</div>
                <div>
                    <p>Total Produk</p>
                    <strong><?= number_format($totalProduk, 0, ',', '.') ?></strong>
                    <small>Produk yang terhubung ke kategori</small>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-icon icon-red">⚠️</div>
                <div>
                    <p>Kategori Kosong</p>
                    <strong><?= number_format($totalKategoriKosong, 0, ',', '.') ?></strong>
                    <small>Belum memiliki produk</small>
                </div>
            </article>
        </div>
    </section>

    <!-- FORM TAMBAH / EDIT -->
    <section class="card" id="form-kategori">
        <div class="panel-heading">
            <div>
                <h2>
                    <?= $isEdit ? '✏️ Edit Kategori' : '＋ Tambah Kategori Baru' ?>
                </h2>

                <p>
                    <?= $isEdit
                        ? 'Ubah nama kategori yang dipilih.'
                        : 'Masukkan nama kategori yang ingin ditambahkan.' ?>
                </p>
            </div>
        </div>

        <form
            action="kategori.php<?= $isEdit ? '?edit=' . (int)$dataEdit['id'] . ($cari !== '' ? '&cari=' . urlencode($cari) : '') : '' ?>#form-kategori"
            method="POST"
        >
            <input
                type="hidden"
                name="aksi"
                value="<?= $isEdit ? 'edit' : 'tambah' ?>"
            >

            <?php if ($isEdit): ?>
                <input
                    type="hidden"
                    name="id_kategori"
                    value="<?= (int)$dataEdit['id'] ?>"
                >
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group full">
                    <label for="nama_kategori">Nama Kategori *</label>

                    <input
                        type="text"
                        id="nama_kategori"
                        name="nama_kategori"
                        placeholder="Contoh: Makanan Kucing"
                        maxlength="100"
                        value="<?= $isEdit
                            ? e($dataEdit['nama_kategori'])
                            : e($_POST['nama_kategori'] ?? '') ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? '💾 Simpan Perubahan' : '＋ Tambah Kategori' ?>
                </button>

                <?php if ($isEdit): ?>
                    <a href="kategori.php<?= $cari !== '' ? '?cari=' . urlencode($cari) : '' ?>"
                       class="btn btn-secondary">
                        ✕ Batal Edit
                    </a>
                <?php else: ?>
                    <button type="reset" class="btn btn-secondary">
                        ↺ Reset Form
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- DAFTAR KATEGORI -->
    <section class="section-block">
        <div class="section-heading">
            <div>
                <h2>Daftar Kategori</h2>
                <p>Lihat dan kelola kategori produk PetShop.</p>
            </div>

            <span class="produk-count">
                <?= number_format($totalDitampilkan, 0, ',', '.') ?> Kategori
            </span>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px 20px 0;">
                <div class="table-toolbar">
                    <div>
                        <h2 style="margin:0;color:var(--brown);font-size:14px;font-weight:900;">
                            Data Kategori
                        </h2>

                        <p style="margin:4px 0 0;color:var(--muted);font-size:11px;">
                            Kelola nama kategori dan periksa jumlah produknya.
                        </p>
                    </div>

                    <form action="kategori.php" method="GET" class="search-form">
                        <input
                            type="text"
                            name="cari"
                            placeholder="Cari kategori..."
                            value="<?= e($cari) ?>"
                        >

                        <button type="submit" class="search-button">
                            🔍 Cari
                        </button>

                        <?php if ($cari !== ''): ?>
                            <a href="kategori.php" class="search-clear">
                                Reset
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Kategori</th>
                            <th>Jumlah Produk</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($totalDitampilkan > 0): ?>
                            <?php $no = 1; ?>

                            <?php foreach ($daftarKategori as $kategori): ?>
                                <tr>
                                    <td>
                                        <span class="category-id">
                                            <?= $no++ ?>
                                        </span>
                                    </td>

                                    <td>
                                        <strong class="category-name">
                                            <?= e($kategori['nama_kategori']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?php if ((int)$kategori['jumlah_produk'] > 0): ?>
                                            <span class="product-badge">
                                                <?= (int)$kategori['jumlah_produk'] ?> Produk
                                            </span>
                                        <?php else: ?>
                                            <span class="product-badge empty">
                                                0 Produk
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div class="category-actions">
                                            <a
                                                href="kategori.php?edit=<?= (int)$kategori['id'] ?><?= $cari !== '' ? '&cari=' . urlencode($cari) : '' ?>#form-kategori"
                                                class="btn-edit"
                                            >
                                                ✏️ Edit
                                            </a>

                                            <form
                                                method="POST"
                                                action="kategori.php<?= $cari !== '' ? '?cari=' . urlencode($cari) : '' ?>"
                                                class="category-inline-form"
                                                onsubmit="return confirm('Yakin ingin menghapus kategori ini? Kategori yang masih digunakan produk tidak dapat dihapus.');"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="aksi"
                                                    value="hapus"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id_kategori"
                                                    value="<?= (int)$kategori['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn-delete"
                                                >
                                                    🗑️ Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="produk-empty">
                                    <div class="produk-empty-icon">🏷️</div>

                                    <strong>
                                        <?= $cari !== ''
                                            ? 'Kategori Tidak Ditemukan'
                                            : 'Belum Ada Kategori' ?>
                                    </strong>

                                    <p>
                                        <?= $cari !== ''
                                            ? 'Coba gunakan kata kunci pencarian yang berbeda.'
                                            : 'Tambahkan kategori baru melalui form di atas.' ?>
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
```