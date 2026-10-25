<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: keranjang.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$pesananId = filter_input(INPUT_POST, 'pesanan_id', FILTER_VALIDATE_INT);

if (!$pesananId || $pesananId < 1) {
    exit('ID pesanan tidak valid.');
}

/* Validasi token CSRF */
if (
    empty($_SESSION['csrf_pembayaran']) ||
    !isset($_POST['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_pembayaran'],
        (string) $_POST['csrf_token']
    )
) {
    exit('Formulir tidak valid. Muat ulang halaman pembayaran.');
}

$transaksiAktif = false;

try {
    mysqli_begin_transaction($koneksi);
    $transaksiAktif = true;

    /* Ambil pesanan milik pengguna dan kunci datanya */
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id, total, metode_pembayaran,
                status, status_pembayaran
         FROM pesanan
         WHERE id = ? AND user_id = ?
         LIMIT 1
         FOR UPDATE"
    );

    mysqli_stmt_bind_param($stmt, 'ii', $pesananId, $userId);
    mysqli_stmt_execute($stmt);

    $pesanan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$pesanan) {
        throw new Exception('Pesanan tidak ditemukan.');
    }

    if ($pesanan['metode_pembayaran'] !== 'QRIS') {
        throw new Exception(
            'Simulasi ini hanya untuk pesanan dengan metode QRIS.'
        );
    }

    /* Jika sudah lunas, jangan kurangi stok lagi */
    if (
        strtolower(trim($pesanan['status_pembayaran'] ?? '')) === 'lunas'
    ) {
        $stmt = mysqli_prepare(
            $koneksi,
            "DELETE keranjang
             FROM keranjang
             INNER JOIN detail_pesanan
                ON detail_pesanan.produk_id = keranjang.produk_id
             WHERE keranjang.user_id = ?
               AND detail_pesanan.pesanan_id = ?"
        );

        mysqli_stmt_bind_param($stmt, 'ii', $userId, $pesananId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($koneksi);
        $transaksiAktif = false;

        unset($_SESSION['csrf_pembayaran']);

        $_SESSION['pesan_pembayaran'] =
            'Pesanan sudah lunas. Stok tidak dikurangi lagi.';

        header('Location: pembayaran.php?id=' . $pesananId);
        exit;
    }

    if ($pesanan['status'] !== 'Menunggu Pembayaran') {
        throw new Exception(
            'Status pesanan tidak memungkinkan pembayaran.'
        );
    }

    /*
     * Gabungkan jumlah jika produk yang sama tercatat
     * lebih dari sekali dalam detail pesanan.
     */
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT produk_id, SUM(jumlah) AS jumlah
         FROM detail_pesanan
         WHERE pesanan_id = ?
         GROUP BY produk_id
         ORDER BY produk_id
         FOR UPDATE"
    );

    mysqli_stmt_bind_param($stmt, 'i', $pesananId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $details = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $details[] = [
            'produk_id' => (int) $row['produk_id'],
            'jumlah' => (int) $row['jumlah']
        ];
    }

    mysqli_stmt_close($stmt);

    if (empty($details)) {
        throw new Exception('Detail produk pesanan tidak ditemukan.');
    }

    /* Periksa seluruh stok sebelum melakukan pengurangan */
    foreach ($details as $item) {
        $produkId = $item['produk_id'];
        $jumlah = $item['jumlah'];

        if ($produkId < 1 || $jumlah < 1) {
            throw new Exception('Data jumlah produk tidak valid.');
        }

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT stok
             FROM produk
             WHERE id = ?
             FOR UPDATE"
        );

        mysqli_stmt_bind_param($stmt, 'i', $produkId);
        mysqli_stmt_execute($stmt);

        $produk = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$produk) {
            throw new Exception(
                'Salah satu produk dalam pesanan tidak ditemukan.'
            );
        }

        if ((int) $produk['stok'] < $jumlah) {
            throw new Exception(
                'Stok produk tidak mencukupi. Silakan hubungi admin.'
            );
        }
    }

    /* Kurangi stok setelah seluruh produk lolos pengecekan */
    foreach ($details as $item) {
        $produkId = $item['produk_id'];
        $jumlah = $item['jumlah'];

        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE produk
             SET stok = stok - ?
             WHERE id = ? AND stok >= ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            'iii',
            $jumlah,
            $produkId,
            $jumlah
        );

        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) !== 1) {
            mysqli_stmt_close($stmt);
            throw new Exception(
                'Stok berubah saat diproses. Silakan coba kembali.'
            );
        }

        mysqli_stmt_close($stmt);
    }

    /* Perbarui status pesanan dan pembayaran */
    $statusBayar = 'Lunas';
    $statusPesanan = 'Diproses';

    $stmt = mysqli_prepare(
        $koneksi,
        "UPDATE pesanan
         SET status_pembayaran = ?,
             status = ?
         WHERE id = ?
           AND user_id = ?
           AND status = 'Menunggu Pembayaran'
           AND status_pembayaran <> 'Lunas'"
    );

    mysqli_stmt_bind_param(
        $stmt,
        'ssii',
        $statusBayar,
        $statusPesanan,
        $pesananId,
        $userId
    );

    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) !== 1) {
        mysqli_stmt_close($stmt);
        throw new Exception(
            'Status pesanan berubah. Silakan periksa kembali pesanan.'
        );
    }

    mysqli_stmt_close($stmt);

    /* Hapus produk yang telah dibeli dari keranjang */
    $stmt = mysqli_prepare(
        $koneksi,
        "DELETE keranjang
         FROM keranjang
         INNER JOIN detail_pesanan
            ON detail_pesanan.produk_id = keranjang.produk_id
         WHERE keranjang.user_id = ?
           AND detail_pesanan.pesanan_id = ?"
    );

    mysqli_stmt_bind_param($stmt, 'ii', $userId, $pesananId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    /* Simpan seluruh perubahan sekaligus */
    mysqli_commit($koneksi);
    $transaksiAktif = false;

    unset($_SESSION['csrf_pembayaran']);

    $_SESSION['pesan_pembayaran'] =
        'Simulasi pembayaran berhasil! Pesanan sedang diproses.';

    header('Location: pembayaran.php?id=' . $pesananId);
    exit;

} catch (Throwable $e) {
    if ($transaksiAktif) {
        mysqli_rollback($koneksi);
    }

    error_log(
        'Simulasi pembayaran PetShop: ' . $e->getMessage()
    );

    $_SESSION['pesan_pembayaran'] =
        'Simulasi pembayaran gagal. ' . $e->getMessage();

    header('Location: pembayaran.php?id=' . $pesananId);
    exit;
}