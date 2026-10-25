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
    header('Location: riwayat_pesanan.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$pesananId = (int) ($_POST['pesanan_id'] ?? 0);

if ($pesananId <= 0) {
    $_SESSION['pesan_riwayat'] = 'ID pesanan tidak valid.';
    header('Location: riwayat_pesanan.php');
    exit;
}

try {
    mysqli_begin_transaction($koneksi);

    /* Pastikan pesanan milik pengguna yang login */
    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT id FROM pesanan
         WHERE id = ? AND user_id = ?
         LIMIT 1 FOR UPDATE"
    );

    mysqli_stmt_bind_param($stmt, 'ii', $pesananId, $userId);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan atau bukan milik akunmu.');
    }

    /* Hapus detail pesanan dahulu */
    $stmt = mysqli_prepare(
        $koneksi,
        "DELETE FROM detail_pesanan WHERE pesanan_id = ?"
    );

    mysqli_stmt_bind_param($stmt, 'i', $pesananId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    /* Hapus pesanan milik pengguna */
    $stmt = mysqli_prepare(
        $koneksi,
        "DELETE FROM pesanan WHERE id = ? AND user_id = ?"
    );

    mysqli_stmt_bind_param($stmt, 'ii', $pesananId, $userId);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) !== 1) {
        mysqli_stmt_close($stmt);
        throw new Exception('Riwayat pesanan gagal dihapus.');
    }

    mysqli_stmt_close($stmt);

    mysqli_commit($koneksi);

    $_SESSION['pesan_riwayat'] = 'Riwayat pesanan berhasil dihapus.';

} catch (Throwable $e) {
    mysqli_rollback($koneksi);
    $_SESSION['pesan_riwayat'] = $e->getMessage();
}

header('Location: riwayat_pesanan.php');
exit;
?>