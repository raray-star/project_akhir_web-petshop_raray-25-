<?php
session_start();
require_once 'config/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$userId = (int)$_SESSION['user_id'];

if (!$id) {
    exit('ID pesanan tidak valid.');
}

$stmt = $koneksi->prepare(
    "SELECT id, total, status_pembayaran
     FROM pesanan
     WHERE id = ? AND user_id = ?
     LIMIT 1"
);
$stmt->bind_param('ii', $id, $userId);
$stmt->execute();
$pesanan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pesanan) {
    exit('Pesanan tidak ditemukan.');
}

$nominal = number_format((float)$pesanan['total'], 0, ',', '.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulasi saja. Tidak ada uang yang ditransfer.
    $stmt = $koneksi->prepare(
        "UPDATE pesanan
         SET status_pembayaran = 'Lunas',
             status_pesanan = 'Diproses'
         WHERE id = ? AND user_id = ?
           AND status_pembayaran <> 'Lunas'"
    );
    $stmt->bind_param('ii', $id, $userId);
    $stmt->execute();
    $stmt->close();

    header('Location: qris_demo.php?id=' . $id);
    exit;
}

$lunas = ($pesanan['status_pembayaran'] === 'Lunas');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pembayaran PetShop</title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    padding: 24px 14px;
    font-family: Arial, sans-serif;
    background: #fff5ed;
    color: #65442f;
}
.card {
    max-width: 440px;
    margin: 20px auto;
    padding: 26px 22px;
    background: white;
    border-radius: 22px;
    text-align: center;
    box-shadow: 0 6px 25px #75482f15;
}
h2 { color: #a66c47; }
.amount { font-size: 27px; font-weight: bold; }
.qr {
    display: inline-grid;
    grid-template-columns: repeat(21, 8px);
    grid-template-rows: repeat(21, 8px);
    gap: 1px;
    padding: 10px;
    margin: 20px auto;
    background: white;
    border: 1px solid #ddd;
}
.qr span { width: 8px; height: 8px; background: white; }
.qr span.dark { background: #38291f; }
.notice {
    background: #fff0d8;
    padding: 12px;
    border-radius: 10px;
    font-size: 13px;
    line-height: 1.5;
}
button, .back {
    display: block;
    width: 100%;
    margin-top: 14px;
    padding: 13px;
    border: 0;
    border-radius: 12px;
    background: #a66c47;
    color: white;
    text-decoration: none;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
}
.back { background: #f0e3d8; color: #65442f; }
</style>
</head>
<body>
<div class="card">
    <h2>🐾 PetShop</h2>
    <p>Pembayaran Pesanan #<?= (int)$pesanan['id'] ?></p>
    <p>Total pembayaran</p>
    <div class="amount">Rp<?= e($nominal) ?></div>

    <?php if ($lunas): ?>
        <h3>✅ Pembayaran Demo Berhasil!</h3>
        <p>Status pesanan: Diproses</p>
    <?php else: ?>

        <div class="qr" aria-label="Ilustrasi QR demo">
        <?php
        // Pola kotak dekoratif, bukan QR yang dapat dipindai.
        $seed = crc32('PETSHOP-' . $id);
        for ($y = 0; $y < 21; $y++) {
            for ($x = 0; $x < 21; $x++) {
                $finder = false;
                foreach ([[0,0], [14,0], [0,14]] as [$fx,$fy]) {
                    if ($x >= $fx && $x < $fx + 7 &&
                        $y >= $fy && $y < $fy + 7) {
                        $dx = $x - $fx;
                        $dy = $y - $fy;
                        $finder = true;
                        $dark = ($dx == 0 || $dx == 6 ||
                                 $dy == 0 || $dy == 6 ||
                                 ($dx >= 2 && $dx <= 4 &&
                                  $dy >= 2 && $dy <= 4));
                        break;
                    }
                }
                if (!$finder) {
                    $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
                    $dark = ($seed % 2) === 0;
                }
                echo '<span class="' .
                     ($dark ? 'dark' : '') . '"></span>';
            }
        }
        ?>
        </div>

        <div class="notice">
            <strong>QR DEMO — BUKAN QRIS ASLI</strong><br>
            Barcode ini hanya ilustrasi untuk presentasi.
            Jangan dipindai untuk pembayaran.
        </div>

        <form method="post"
              onsubmit="return confirm('Jalankan simulasi pembayaran? Tidak ada uang yang ditransfer.');">
            <button type="submit">Simulasikan Pembayaran</button>
        </form>
    <?php endif; ?>

    <a class="back" href="index.php">Kembali ke Beranda</a>
</div>
</body>
</html>