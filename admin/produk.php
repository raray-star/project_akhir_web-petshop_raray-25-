<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

/* =========================
   CEK AKSES ADMIN
========================= */
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: ../login.php');
    exit;
}

$db = $conn ?? $koneksi ?? null;

if (!$db) {
    die('Koneksi database tidak ditemukan. Periksa config/koneksi.php');
}

mysqli_set_charset($db, 'utf8mb4');

/* =========================
   FUNGSI BANTU
========================= */
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiah($angka)
{
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}

function statusStok($stok)
{
    if ((int)$stok <= 0) {
        return 'stok-habis';
    }

    if ((int)$stok <= 5) {
        return 'stok-rendah';
    }

    return 'stok-tersedia';
}

/* =========================
   FOLDER GAMBAR
========================= */
$folderGambar = __DIR__ . '/../assets/img/produk/';
$urlGambar = '../assets/img/produk/';

if (!is_dir($folderGambar)) {
    if (!mkdir($folderGambar, 0775, true) && !is_dir($folderGambar)) {
        die('Folder gambar produk gagal dibuat.');
    }
}

/* =========================
   UPLOAD GAMBAR
========================= */
function uploadGambar($field, $folder, &$error)
{
    if (
        !isset($_FILES[$field]) ||
        $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return '';
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $error = 'Gagal mengunggah gambar.';
        return '';
    }

    if ($_FILES[$field]['size'] > 2 * 1024 * 1024) {
        $error = 'Ukuran gambar maksimal 2 MB.';
        return '';
    }

    $tmp = $_FILES[$field]['tmp_name'];
    $info = getimagesize($tmp);

    if (!$info) {
        $error = 'File harus berupa gambar yang valid.';
        return '';
    }

    $mime = $info['mime'] ?? '';

    $ekstensi = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($ekstensi[$mime])) {
        $error = 'Format gambar harus JPG, PNG, atau WEBP.';
        return '';
    }

    $namaFile = bin2hex(random_bytes(12)) . '.' . $ekstensi[$mime];

    if (!move_uploaded_file($tmp, $folder . $namaFile)) {
        $error = 'Gambar gagal disimpan.';
        return '';
    }

    return $namaFile;
}

/* =========================
   HAPUS GAMBAR LAMA
========================= */
function hapusGambarLama($nama, $folder)
{
    $nama = basename((string)$nama);

    if ($nama !== '' && is_file($folder . $nama)) {
        unlink($folder . $nama);
    }
}

/* =========================
   PESAN NOTIFIKASI
========================= */
$pesan = '';
$error = '';

/* =========================
   PROSES TAMBAH, EDIT, HAPUS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    /* TAMBAH DAN EDIT PRODUK */
    if ($aksi === 'tambah' || $aksi === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_produk'] ?? '');
        $kategoriId = (int)($_POST['kategori_id'] ?? 0);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $harga = (float)($_POST['harga'] ?? 0);
        $stok = filter_var(
            $_POST['stok'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $nama === '' ||
            $kategoriId <= 0 ||
            $harga < 0 ||
            $stok === false ||
            $stok === null ||
            $stok < 0 ||
            ($aksi === 'edit' && $id <= 0)
        ) {
            $error = 'Nama, kategori, harga, dan stok harus diisi dengan benar.';
        } else {
            $cek = $db->prepare(
                'SELECT id FROM kategori WHERE id = ?'
            );

            $cek->bind_param('i', $kategoriId);
            $cek->execute();

            $kategoriAda = $cek->get_result()->num_rows > 0;

            $cek->close();

            if (!$kategoriAda) {
                $error = 'Kategori yang dipilih tidak ditemukan.';
            }
        }

        $gambarBaru = '';

        if ($error === '') {
            $gambarBaru = uploadGambar(
                'gambar',
                $folderGambar,
                $error
            );
        }

        /* TAMBAH PRODUK */
        if ($error === '' && $aksi === 'tambah') {
            $stmt = $db->prepare(
                'INSERT INTO produk
                (nama_produk, kategori_id, deskripsi, harga, stok, gambar)
                VALUES (?, ?, ?, ?, ?, ?)'
            );

            $stmt->bind_param(
                'sisdis',
                $nama,
                $kategoriId,
                $deskripsi,
                $harga,
                $stok,
                $gambarBaru
            );

            if ($stmt->execute()) {
                $stmt->close();

                header('Location: produk.php?status=tambah');
                exit;
            }

            error_log('Gagal menambah produk: ' . $stmt->error);

            $error = 'Produk gagal ditambahkan.';
            $stmt->close();

            if ($gambarBaru !== '') {
                hapusGambarLama($gambarBaru, $folderGambar);
            }
        }

        /* EDIT PRODUK */
        if ($error === '' && $aksi === 'edit') {
            $ambil = $db->prepare(
                'SELECT gambar FROM produk WHERE id = ?'
            );

            $ambil->bind_param('i', $id);
            $ambil->execute();

            $produkLama = $ambil->get_result()->fetch_assoc();

            $ambil->close();

            if (!$produkLama) {
                $error = 'Produk tidak ditemukan.';

                if ($gambarBaru !== '') {
                    hapusGambarLama($gambarBaru, $folderGambar);
                }
            } else {
                $gambarFinal = $gambarBaru !== ''
                    ? $gambarBaru
                    : ($produkLama['gambar'] ?? '');

                $stmt = $db->prepare(
                    'UPDATE produk
                     SET nama_produk = ?,
                         kategori_id = ?,
                         deskripsi = ?,
                         harga = ?,
                         stok = ?,
                         gambar = ?
                     WHERE id = ?'
                );

                $stmt->bind_param(
                    'sisdisi',
                    $nama,
                    $kategoriId,
                    $deskripsi,
                    $harga,
                    $stok,
                    $gambarFinal,
                    $id
                );

                if ($stmt->execute()) {
                    $stmt->close();

                    if (
                        $gambarBaru !== '' &&
                        !empty($produkLama['gambar'])
                    ) {
                        hapusGambarLama(
                            $produkLama['gambar'],
                            $folderGambar
                        );
                    }

                    header('Location: produk.php?status=edit');
                    exit;
                }

                error_log('Gagal mengedit produk: ' . $stmt->error);

                $error = 'Produk gagal diperbarui.';
                $stmt->close();

                if ($gambarBaru !== '') {
                    hapusGambarLama($gambarBaru, $folderGambar);
                }
            }
        }
    }

    /* HAPUS PRODUK */
    if ($aksi === 'hapus') {
        $id = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            $error = 'ID produk tidak valid.';
        } else {
            $ambil = $db->prepare(
                'SELECT gambar FROM produk WHERE id = ?'
            );

            $ambil->bind_param('i', $id);
            $ambil->execute();

            $produkLama = $ambil->get_result()->fetch_assoc();

            $ambil->close();

            if (!$produkLama) {
                $error = 'Produk tidak ditemukan.';
            } else {
                $stmt = $db->prepare(
                    'DELETE FROM produk WHERE id = ?'
                );

                $stmt->bind_param('i', $id);

                if ($stmt->execute()) {
                    $stmt->close();

                    if (!empty($produkLama['gambar'])) {
                        hapusGambarLama(
                            $produkLama['gambar'],
                            $folderGambar
                        );
                    }

                    header('Location: produk.php?status=hapus');
                    exit;
                }

                error_log('Gagal menghapus produk: ' . $stmt->error);

                $error = 'Produk gagal dihapus. Produk mungkin masih digunakan pada detail pesanan.';
                $stmt->close();
            }
        }
    }
}

/* =========================
   NOTIFIKASI BERHASIL
========================= */
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'tambah':
            $pesan = 'Produk berhasil ditambahkan.';
            break;

        case 'edit':
            $pesan = 'Produk berhasil diperbarui.';
            break;

        case 'hapus':
            $pesan = 'Produk berhasil dihapus.';
            break;
    }
}

/* =========================
   DATA KATEGORI
========================= */
$kategoriList = [];

$hasilKategori = $db->query(
    'SELECT id, nama_kategori
     FROM kategori
     ORDER BY nama_kategori ASC'
);

if ($hasilKategori) {
    while ($row = $hasilKategori->fetch_assoc()) {
        $kategoriList[] = $row;
    }
}

/* =========================
   DATA EDIT PRODUK
========================= */
$editProduk = null;

if (isset($_GET['edit'])) {
    $idEdit = (int)$_GET['edit'];

    if ($idEdit > 0) {
        $stmtEdit = $db->prepare(
            'SELECT * FROM produk WHERE id = ?'
        );

        $stmtEdit->bind_param('i', $idEdit);
        $stmtEdit->execute();

        $editProduk = $stmtEdit->get_result()->fetch_assoc();

        $stmtEdit->close();
    }
}

/* =========================
   PENCARIAN PRODUK
========================= */
$cari = trim($_GET['cari'] ?? '');

if ($cari !== '') {
    $keyword = '%' . $cari . '%';

    $stmtProduk = $db->prepare(
        'SELECT p.*, k.nama_kategori
         FROM produk p
         LEFT JOIN kategori k ON p.kategori_id = k.id
         WHERE p.nama_produk LIKE ?
         ORDER BY p.id DESC'
    );

    $stmtProduk->bind_param('s', $keyword);
    $stmtProduk->execute();

    $hasilProduk = $stmtProduk->get_result();
} else {
    $stmtProduk = null;

    $hasilProduk = $db->query(
        'SELECT p.*, k.nama_kategori
         FROM produk p
         LEFT JOIN kategori k ON p.kategori_id = k.id
         ORDER BY p.id DESC'
    );
}

/* =========================
   STATISTIK
========================= */
$totalProduk = (int)$db->query(
    'SELECT COUNT(*) AS total FROM produk'
)->fetch_assoc()['total'];

$totalStok = (int)$db->query(
    'SELECT COALESCE(SUM(stok), 0) AS total FROM produk'
)->fetch_assoc()['total'];

$totalKategori = (int)$db->query(
    'SELECT COUNT(*) AS total FROM kategori'
)->fetch_assoc()['total'];

$produkStokHabis = (int)$db->query(
    'SELECT COUNT(*) AS total FROM produk WHERE stok <= 0'
)->fetch_assoc()['total'];

$produkStokRendah = (int)$db->query(
    'SELECT COUNT(*) AS total FROM produk WHERE stok > 0 AND stok <= 5'
)->fetch_assoc()['total'];

/* =========================
   HALAMAN AKTIF
========================= */
$halamanAktif = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kelola Produk - PetShop Admin</title>

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
            scroll-behavior: smooth;
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

        button {
            cursor: pointer;
        }

        /* ======================
           SIDEBAR
        ====================== */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            width: 225px;
            height: 100vh;
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

        /* ======================
           KONTEN UTAMA
        ====================== */

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

        /* ======================
           BANNER
        ====================== */

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

        /* ======================
           NOTIFIKASI
        ====================== */

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
            line-height: 1.7;
        }

        .alert-success {
            border-color: #cfe9d5;
            background: #edf8ef;
            color: #34784b;
        }

        .alert-danger {
            border-color: #f4d0cc;
            background: #fff0ed;
            color: #bf4848;
        }

        /* ======================
           STATISTIK
        ====================== */

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
            line-height: 1.7;
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

        .icon-pink {
            background: #fff0ed;
        }

        .icon-cream {
            background: #f8f0df;
        }

        .icon-green {
            background: #e8f6ec;
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
            line-height: 1.5;
        }

        /* ======================
           KARTU
        ====================== */

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

        .panel-icon {
            display: inline-flex;
            width: 39px;
            height: 39px;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: var(--cream-dark);
            font-size: 19px;
        }

        .heading-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .heading-copy h2 {
            margin: 0;
            color: var(--brown);
            font-size: 16px;
            font-weight: 900;
        }

        .heading-copy p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 11px;
        }

        .section-tag {
            flex-shrink: 0;
            padding: 7px 11px;
            border-radius: 20px;
            background: var(--cream-dark);
            color: var(--brown);
            font-size: 10px;
            font-weight: 900;
        }

        /* ======================
           FORM PRODUK
        ====================== */

        .admin-form {
            display: flex;
            flex-direction: column;
            gap: 17px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
        }

        .form-group {
            display: flex;
            min-width: 0;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            color: var(--brown);
            font-size: 11px;
            font-weight: 900;
        }

        .required-mark {
            color: var(--orange);
        }

        .form-group input,
        .form-group select,
        .form-group textarea,
        .search-input {
            width: 100%;
            min-width: 0;
            padding: 12px 13px;
            border: 1px solid #e8dbce;
            border-radius: 10px;
            outline: none;
            background: #fffdfb;
            color: var(--text);
            font-size: 12px;
            transition: .2s ease;
        }

        .form-group input,
        .form-group select {
            min-height: 43px;
        }

        .form-group textarea {
            min-height: 105px;
            resize: vertical;
            line-height: 1.7;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus,
        .search-input:focus {
            border-color: var(--orange);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(233, 126, 53, .12);
        }

        .form-group input[type="file"] {
            padding: 9px;
            background: var(--white);
        }

        .form-group input[type="file"]::file-selector-button {
            margin-right: 10px;
            padding: 8px 11px;
            border: none;
            border-radius: 7px;
            background: var(--cream-dark);
            color: var(--brown);
            font-family: inherit;
            font-size: 10px;
            font-weight: 900;
            cursor: pointer;
        }

        .form-group small {
            color: #a09287;
            font-size: 10px;
            line-height: 1.6;
        }

        .current-image-box {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 5px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fffaf4;
        }

        .current-image {
            width: 85px;
            height: 85px;
            flex-shrink: 0;
            border: 1px solid var(--line);
            border-radius: 11px;
            background: var(--white);
            object-fit: cover;
        }

        .current-image-info strong {
            display: block;
            margin-bottom: 5px;
            color: var(--brown);
            font-size: 11px;
        }

        .current-image-info span {
            color: var(--muted);
            font-size: 10px;
            line-height: 1.6;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            padding-top: 5px;
        }

        /* ======================
           TOMBOL
        ====================== */

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 37px;
            padding: 9px 13px;
            border: 1px solid transparent;
            border-radius: 9px;
            cursor: pointer;
            font-size: 10px;
            font-weight: 900;
            transition: .2s ease;
            white-space: nowrap;
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
            border-color: #ead7c5;
            background: var(--white);
            color: var(--brown);
        }

        .btn-secondary:hover {
            background: var(--cream);
        }

        .btn-warning {
            background: #fff0df;
            color: #a86624;
        }

        .btn-warning:hover {
            background: #ffe3c1;
        }

        .btn-danger {
            background: #ffeded;
            color: #bf4848;
        }

        .btn-danger:hover {
            background: #ffdbd8;
        }

        .btn-success {
            background: #e8f6ec;
            color: #34784b;
        }

        .btn-success:hover {
            background: #d7efdd;
        }

        /* ======================
           PENCARIAN
        ====================== */

        .search-form {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 0;
        }

        .search-icon {
            position: absolute;
            top: 50%;
            left: 13px;
            color: #a09287;
            font-size: 15px;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .search-input {
            min-height: 42px;
            padding-left: 39px;
        }

        .search-count {
            flex-shrink: 0;
            padding: 7px 11px;
            border-radius: 20px;
            background: var(--cream-dark);
            color: var(--brown);
            font-size: 10px;
            font-weight: 900;
        }

        /* ======================
           TABEL PRODUK
        ====================== */

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .product-table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        .product-table thead {
            background: var(--cream);
        }

        .product-table th {
            padding: 15px 13px;
            border-bottom: 1px solid var(--line);
            color: var(--brown);
            text-align: left;
            font-size: 10px;
            font-weight: 900;
        }

        .product-table td {
            padding: 13px;
            border-bottom: 1px solid #f5eee7;
            color: var(--muted);
            font-size: 11px;
            vertical-align: middle;
        }

        .product-table tbody tr {
            transition: background .2s ease;
        }

        .product-table tbody tr:hover {
            background: #fffaf4;
        }

        .product-table tbody tr:last-child td {
            border-bottom: none;
        }

        .number-cell {
            color: #a09287;
            font-weight: 800;
        }

        .product-image {
            display: block;
            width: 62px;
            height: 62px;
            border: 1px solid var(--line);
            border-radius: 11px;
            background: var(--cream);
            object-fit: cover;
        }

        .no-image {
            display: flex;
            width: 62px;
            height: 62px;
            align-items: center;
            justify-content: center;
            border: 1px dashed #e3d3c3;
            border-radius: 11px;
            background: var(--cream);
            color: #b09b88;
            font-size: 23px;
        }

        .product-name {
            display: block;
            max-width: 220px;
            overflow: hidden;
            color: var(--brown);
            font-size: 12px;
            font-weight: 900;
            text-overflow: ellipsis;
        }

        .product-description {
            display: block;
            max-width: 220px;
            overflow: hidden;
            margin-top: 4px;
            color: #a09287;
            font-size: 10px;
            text-overflow: ellipsis;
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 20px;
            background: #fff0df;
            color: #a86624;
            font-size: 10px;
            font-weight: 900;
        }

        .product-price {
            color: var(--brown);
            font-size: 11px;
            font-weight: 900;
        }

        .stock-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 900;
        }

        .stock-tersedia {
            background: #e8f6ec;
            color: #34784b;
        }

        .stock-rendah {
            background: #fff0df;
            color: #a86624;
        }

        .stock-habis {
            background: #ffeded;
            color: #bf4848;
        }

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .action-buttons form {
            margin: 0;
        }

        .empty-state {
            padding: 45px 15px !important;
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

        /* ======================
           FOOTER
        ====================== */

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

        /* ======================
           RESPONSIVE
        ====================== */

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

            .form-grid {
                grid-template-columns: 1fr;
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

            .search-form {
                flex-wrap: wrap;
            }

            .search-box {
                flex-basis: 100%;
            }

            .panel-heading {
                align-items: flex-start;
            }

            .section-tag {
                font-size: 9px;
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
                flex: 1;
            }
        }
    </style>
</head>

<body>

<!-- ======================
     SIDEBAR
====================== -->

<aside class="sidebar">

    <a href="dashboard.php" class="brand">
        <span class="brand-icon">🐾</span>

        <span class="brand-name">
            Pet<span>Shop</span>
        </span>

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

<!-- ======================
     KONTEN UTAMA
====================== -->

<main class="dashboard-main">

    <!-- HEADER -->

    <header class="topbar">

        <div>
            <p class="eyebrow">PETSHOP ADMIN PANEL</p>

            <h1>Kelola Produk</h1>

            <p class="header-subtitle">
                Kelola katalog produk, harga, kategori, dan stok PetShop.
            </p>
        </div>

        <a href="../index.php" class="website-button">
            🌐 Lihat Website
        </a>

    </header>

    <!-- BANNER -->

    <section class="welcome-panel">

        <div class="welcome-content">

            <span class="welcome-tag">
                🐾 MANAJEMEN PRODUK
            </span>

            <h2>
                Kelola Produk Kesayangan 🐶
            </h2>

            <p>
                Tambahkan produk baru, perbarui informasi barang,
                atur stok, dan pastikan semua kebutuhan hewan
                peliharaan tersedia di PetShop.
            </p>

        </div>

        <div class="welcome-art">📦</div>

    </section>

    <!-- NOTIFIKASI -->

    <?php if ($pesan !== ''): ?>

        <div class="alert alert-success">
            <span>✅</span>
            <span><?= e($pesan) ?></span>
        </div>

    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">
            <span>⚠️</span>
            <span><?= e($error) ?></span>
        </div>

    <?php endif; ?>

    <!-- STATISTIK -->

    <section class="section-block">

        <div class="section-heading">

            <div>
                <h2>Ringkasan Produk</h2>

                <p>
                    Informasi jumlah produk dan kondisi persediaan.
                </p>
            </div>

        </div>

        <div class="stats-grid">

            <article class="stat-card">

                <div class="stat-icon icon-orange">
                    📦
                </div>

                <div>
                    <p>Total Produk</p>

                    <strong>
                        <?= number_format($totalProduk, 0, ',', '.') ?>
                    </strong>

                    <small>Semua produk terdaftar</small>
                </div>

            </article>

            <article class="stat-card">

                <div class="stat-icon icon-pink">
                    🏷️
                </div>

                <div>
                    <p>Total Kategori</p>

                    <strong>
                        <?= number_format($totalKategori, 0, ',', '.') ?>
                    </strong>

                    <small>Kategori produk</small>
                </div>

            </article>

            <article class="stat-card">

                <div class="stat-icon icon-cream">
                    📊
                </div>

                <div>
                    <p>Total Stok</p>

                    <strong>
                        <?= number_format($totalStok, 0, ',', '.') ?>
                    </strong>

                    <small>Jumlah seluruh persediaan</small>
                </div>

            </article>

            <article class="stat-card">

                <div class="stat-icon icon-red">
                    ⚠️
                </div>

                <div>
                    <p>Stok Perlu Dicek</p>

                    <strong>
                        <?= number_format(
                            $produkStokHabis + $produkStokRendah,
                            0,
                            ',',
                            '.'
                        ) ?>
                    </strong>

                    <small>
                        <?= $produkStokHabis ?> habis ·
                        <?= $produkStokRendah ?> stok rendah
                    </small>
                </div>

            </article>

        </div>

    </section>

    <!-- FORM TAMBAH / EDIT -->

    <section class="section-block">

        <div class="section-heading">

            <div>
                <h2>Manajemen Katalog</h2>

                <p>
                    Isi informasi produk pada formulir di bawah ini.
                </p>
            </div>

        </div>

        <div class="card">

            <div class="panel-heading">

                <div class="heading-left">

                    <div class="panel-icon">
                        <?= $editProduk ? '✏️' : '➕' ?>
                    </div>

                    <div class="heading-copy">

                        <h2>
                            <?= $editProduk ? 'Edit Produk' : 'Tambah Produk Baru' ?>
                        </h2>

                        <p>
                            <?= $editProduk
                                ? 'Perbarui informasi produk yang dipilih.'
                                : 'Lengkapi detail produk untuk menambahkannya ke katalog.' ?>
                        </p>

                    </div>

                </div>

                <?php if ($editProduk): ?>

                    <span class="section-tag">
                        MODE EDIT
                    </span>

                <?php else: ?>

                    <span class="section-tag">
                        PRODUK BARU
                    </span>

                <?php endif; ?>

            </div>

            <form
                action="produk.php<?= $editProduk ? '?edit=' . (int)$editProduk['id'] : '' ?>"
                method="POST"
                enctype="multipart/form-data"
                class="admin-form"
            >

                <input
                    type="hidden"
                    name="aksi"
                    value="<?= $editProduk ? 'edit' : 'tambah' ?>"
                >

                <?php if ($editProduk): ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int)$editProduk['id'] ?>"
                    >

                <?php endif; ?>

                <div class="form-grid">

                    <!-- NAMA PRODUK -->

                    <div class="form-group">

                        <label for="nama_produk">
                            Nama Produk <span class="required-mark">*</span>
                        </label>

                        <input
                            type="text"
                            id="nama_produk"
                            name="nama_produk"
                            placeholder="Contoh: Makanan Kucing Premium"
                            required
                            value="<?= e($editProduk['nama_produk'] ?? '') ?>"
                        >

                    </div>

                    <!-- KATEGORI -->

                    <div class="form-group">

                        <label for="kategori_id">
                            Kategori <span class="required-mark">*</span>
                        </label>

                        <select
                            id="kategori_id"
                            name="kategori_id"
                            required
                        >

                            <option value="">
                                -- Pilih Kategori --
                            </option>

                            <?php foreach ($kategoriList as $kat): ?>

                                <option
                                    value="<?= (int)$kat['id'] ?>"
                                    <?= (
                                        (int)($editProduk['kategori_id'] ?? 0)
                                        === (int)$kat['id']
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= e($kat['nama_kategori']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <?php if (empty($kategoriList)): ?>

                            <small>
                                Belum ada kategori. Tambahkan kategori terlebih dahulu.
                            </small>

                        <?php endif; ?>

                    </div>

                    <!-- HARGA -->

                    <div class="form-group">

                        <label for="harga">
                            Harga Produk (Rp)
                            <span class="required-mark">*</span>
                        </label>

                        <input
                            type="number"
                            id="harga"
                            name="harga"
                            placeholder="Contoh: 25000"
                            min="0"
                            step="1"
                            required
                            value="<?= e($editProduk['harga'] ?? '') ?>"
                        >

                    </div>

                    <!-- STOK -->

                    <div class="form-group">

                        <label for="stok">
                            Stok Produk <span class="required-mark">*</span>
                        </label>

                        <input
                            type="number"
                            id="stok"
                            name="stok"
                            placeholder="Contoh: 50"
                            min="0"
                            step="1"
                            required
                            value="<?= e($editProduk['stok'] ?? '') ?>"
                        >

                    </div>

                </div>

                <!-- DESKRIPSI -->

                <div class="form-group">

                    <label for="deskripsi">
                        Deskripsi Produk
                    </label>

                    <textarea
                        id="deskripsi"
                        name="deskripsi"
                        rows="4"
                        placeholder="Tuliskan deskripsi, manfaat, ukuran, atau detail produk..."
                    ><?= e($editProduk['deskripsi'] ?? '') ?></textarea>

                    <small>
                        Jelaskan informasi produk agar pelanggan lebih mudah memilih.
                    </small>

                </div>

                <!-- GAMBAR -->

                <div class="form-group">

                    <label for="gambar">
                        Gambar Produk
                    </label>

                    <input
                        type="file"
                        id="gambar"
                        name="gambar"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <small>
                        Format JPG, PNG, atau WEBP. Ukuran maksimal 2 MB.
                        <?= $editProduk
                            ? 'Kosongkan jika tidak ingin mengganti gambar.'
                            : '' ?>
                    </small>

                    <?php if (!empty($editProduk['gambar'])): ?>

                        <div class="current-image-box">

                            <img
                                src="<?= e($urlGambar . basename($editProduk['gambar'])) ?>"
                                alt="<?= e($editProduk['nama_produk']) ?>"
                                class="current-image"
                            >

                            <div class="current-image-info">

                                <strong>Gambar Saat Ini</strong>

                                <span>
                                    <?= e($editProduk['nama_produk']) ?>
                                    <br>
                                    Unggah gambar baru jika ingin menggantinya.
                                </span>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

                <!-- TOMBOL FORM -->

                <div class="form-actions">

                    <button type="submit" class="btn btn-primary">

                        <?= $editProduk
                            ? '💾 Simpan Perubahan'
                            : '➕ Tambah Produk' ?>

                    </button>

                    <?php if ($editProduk): ?>

                        <a href="produk.php" class="btn btn-secondary">
                            ✖ Batal Edit
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </div>

    </section>

    <!-- DAFTAR PRODUK -->

    <section class="section-block">

        <div class="section-heading">

            <div>
                <h2>Daftar Produk</h2>

                <p>
                    Lihat, cari, edit, dan hapus produk yang tersimpan.
                </p>
            </div>

            <span class="search-count">
                <?= number_format($totalProduk, 0, ',', '.') ?> Produk
            </span>

        </div>

        <div class="card">

            <div class="panel-heading">

                <div class="heading-left">

                    <div class="panel-icon">
                        📋
                    </div>

                    <div class="heading-copy">

                        <h2>Katalog Produk</h2>

                        <p>
                            Informasi produk, kategori, harga, dan persediaan.
                        </p>

                    </div>

                </div>

            </div>

            <!-- PENCARIAN -->

            <form method="GET" action="produk.php" class="search-form">

                <div class="search-box">

                    <span class="search-icon">🔎</span>

                    <input
                        type="search"
                        name="cari"
                        class="search-input"
                        placeholder="Cari nama produk..."
                        value="<?= e($cari) ?>"
                    >

                </div>

                <button type="submit" class="btn btn-primary">
                    Cari Produk
                </button>

                <?php if ($cari !== ''): ?>

                    <a href="produk.php" class="btn btn-secondary">
                        Reset
                    </a>

                <?php endif; ?>

            </form>

            <!-- TABEL -->

            <div class="table-responsive">

                <table class="product-table">

                    <thead>

                        <tr>
                            <th>No.</th>
                            <th>Gambar</th>
                            <th>Informasi Produk</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Stok</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($hasilProduk && $hasilProduk->num_rows > 0): ?>

                            <?php $no = 1; ?>

                            <?php while ($produk = $hasilProduk->fetch_assoc()): ?>

                                <?php
                                $stokProduk = (int)$produk['stok'];
                                $kelasStok = statusStok($stokProduk);

                                if ($stokProduk <= 0) {
                                    $labelStok = 'Stok Habis';
                                } elseif ($stokProduk <= 5) {
                                    $labelStok = 'Stok Rendah';
                                } else {
                                    $labelStok = 'Tersedia';
                                }

                                $deskripsiProduk = trim(
                                    (string)($produk['deskripsi'] ?? '')
                                );
                                ?>

                                <tr>

                                    <!-- NOMOR -->

                                    <td>
                                        <span class="number-cell">
                                            <?= $no++ ?>
                                        </span>
                                    </td>

                                    <!-- GAMBAR -->

                                    <td>

                                        <?php if (!empty($produk['gambar'])): ?>

                                            <img
                                                src="<?= e($urlGambar . basename($produk['gambar'])) ?>"
                                                alt="<?= e($produk['nama_produk']) ?>"
                                                class="product-image"
                                                loading="lazy"
                                            >

                                        <?php else: ?>

                                            <div class="no-image">
                                                🐾
                                            </div>

                                        <?php endif; ?>

                                    </td>

                                    <!-- INFORMASI PRODUK -->

                                    <td>

                                        <span class="product-name">
                                            <?= e($produk['nama_produk']) ?>
                                        </span>

                                        <span class="product-description">
                                            <?= $deskripsiProduk !== ''
                                                ? e($deskripsiProduk)
                                                : 'Tidak ada deskripsi' ?>
                                        </span>

                                    </td>

                                    <!-- KATEGORI -->

                                    <td>

                                        <span class="category-badge">
                                            🏷️
                                            <?= e($produk['nama_kategori'] ?? '-') ?>
                                        </span>

                                    </td>

                                    <!-- HARGA -->

                                    <td>

                                        <span class="product-price">
                                            <?= rupiah($produk['harga']) ?>
                                        </span>

                                    </td>

                                    <!-- STOK -->

                                    <td>

                                        <span class="stock-badge <?= e($kelasStok) ?>">
                                            <?= $stokProduk <= 0
                                                ? '●'
                                                : ($stokProduk <= 5 ? '●' : '●') ?>

                                            <?= number_format(
                                                $stokProduk,
                                                0,
                                                ',',
                                                '.'
                                            ) ?>
                                        </span>

                                        <div style="margin-top:5px;font-size:9px;color:#a09287;">
                                            <?= e($labelStok) ?>
                                        </div>

                                    </td>

                                    <!-- AKSI -->

                                    <td>

                                        <div class="action-buttons">

                                            <a
                                                href="produk.php?edit=<?= (int)$produk['id'] ?>"
                                                class="btn btn-warning"
                                                title="Edit produk"
                                            >
                                                ✏️ Edit
                                            </a>

                                            <form
                                                action="produk.php"
                                                method="POST"
                                                onsubmit="return confirm('Yakin ingin menghapus produk <?= e($produk['nama_produk']) ?>? Tindakan ini tidak dapat dibatalkan.');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="aksi"
                                                    value="hapus"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int)$produk['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-danger"
                                                    title="Hapus produk"
                                                >
                                                    🗑️ Hapus
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="7" class="empty-state">

                                    <div class="empty-icon">
                                        🐾
                                    </div>

                                    <strong>
                                        <?= $cari !== ''
                                            ? 'Produk Tidak Ditemukan'
                                            : 'Belum Ada Produk' ?>
                                    </strong>

                                    <p>
                                        <?= $cari !== ''
                                            ? 'Coba gunakan kata kunci pencarian yang lain.'
                                            : 'Tambahkan produk pertama kamu melalui formulir di atas.' ?>
                                    </p>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </section>

    <!-- FOOTER -->

    <footer class="dashboard-footer">

        © <?= date('Y') ?>

        <strong>PetShop</strong>

        — Happy Pets, Happy Life ♡

    </footer>

</main>

</body>
</html>

<?php
/* Tutup statement pencarian jika menggunakan prepared statement */
if ($stmtProduk instanceof mysqli_stmt) {
    $stmtProduk->close();
}
?>