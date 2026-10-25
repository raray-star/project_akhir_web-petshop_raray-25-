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

function gambarProduk($gambar) {
    $gambar = trim(str_replace('\\', '/', (string)($gambar ?? '')));

    if ($gambar === '') return '';
    if (preg_match('~^https?://~i', $gambar)) return $gambar;
    if (strpos($gambar, 'assets/') === 0) return $gambar;

    return 'assets/img/produk/' . basename($gambar);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

/* Proses tambah, kurang, dan hapus produk */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];
    $cartId = filter_input(INPUT_POST, 'cart_id', FILTER_VALIDATE_INT);

    if ($cartId && $cartId > 0) {
        $stmt = mysqli_prepare($koneksi, "
            SELECT k.id, k.jumlah, p.stok
            FROM keranjang k
            JOIN produk p ON p.id = k.produk_id
            WHERE k.id = ? AND k.user_id = ?
            LIMIT 1
        ");

        mysqli_stmt_bind_param($stmt, 'ii', $cartId, $userId);
        mysqli_stmt_execute($stmt);
        $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($item) {
            $jumlah = (int)$item['jumlah'];
            $stok = (int)$item['stok'];

            if ($aksi === 'tambah') {
                if ($jumlah < $stok) {
                    $jumlahBaru = $jumlah + 1;

                    $stmt = mysqli_prepare($koneksi, "
                        UPDATE keranjang SET jumlah = ?
                        WHERE id = ? AND user_id = ?
                    ");
                    mysqli_stmt_bind_param($stmt, 'iii', $jumlahBaru, $cartId, $userId);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                } else {
                    $_SESSION['pesan_keranjang'] = 'Jumlah barang sudah mencapai batas stok.';
                }
            } elseif ($aksi === 'kurang' && $jumlah > 1) {
                $jumlahBaru = $jumlah - 1;

                $stmt = mysqli_prepare($koneksi, "
                    UPDATE keranjang SET jumlah = ?
                    WHERE id = ? AND user_id = ?
                ");
                mysqli_stmt_bind_param($stmt, 'iii', $jumlahBaru, $cartId, $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            } elseif ($aksi === 'hapus') {
                $stmt = mysqli_prepare($koneksi, "
                    DELETE FROM keranjang WHERE id = ? AND user_id = ?
                ");
                mysqli_stmt_bind_param($stmt, 'ii', $cartId, $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $_SESSION['pesan_keranjang'] = 'Produk berhasil dihapus dari keranjang.';
            }
        }
    }

    header('Location: keranjang.php');
    exit;
}

$pesan = $_SESSION['pesan_keranjang'] ?? '';
unset($_SESSION['pesan_keranjang']);

$namaPengguna = $_SESSION['nama'] ?? '';

/* Ambil produk dalam keranjang */
$stmt = mysqli_prepare($koneksi, "
    SELECT
        k.id AS cart_id,
        k.jumlah,
        p.id AS produk_id,
        p.nama_produk,
        p.harga,
        p.stok,
        p.gambar,
        kat.nama_kategori
    FROM keranjang k
    JOIN produk p ON p.id = k.produk_id
    LEFT JOIN kategori kat ON kat.id = p.kategori_id
    WHERE k.user_id = ?
    ORDER BY k.id DESC
");

mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$items = [];

while ($row = mysqli_fetch_assoc($result)) {
    $row['jumlah'] = (int)$row['jumlah'];
    $row['harga'] = (float)$row['harga'];
    $row['stok'] = (int)$row['stok'];
    $row['subtotal'] = $row['harga'] * $row['jumlah'];
    $items[] = $row;
}

mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Keranjang | PetShop</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">

<style>
.cart-page{padding:42px 6% 70px;min-height:65vh;background:#fffdf9}
.cart-banner{padding:30px 32px;margin-bottom:30px;border-radius:22px;background:linear-gradient(120deg,#fff0dc,#fff8ee)}
.cart-banner h1{margin:10px 0;color:#70432e;font-size:34px;font-weight:900}
.cart-banner p{margin:0;color:#827267;font-size:16px;line-height:1.8}
.cart-message{margin-bottom:20px;padding:14px 18px;border-radius:12px;background:#fff1d7;color:#86551d;font-weight:700}
.cart-layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:25px;align-items:start}
.cart-items{display:flex;flex-direction:column;gap:16px}
.cart-item{display:grid;grid-template-columns:30px 125px minmax(0,1fr);gap:15px;padding:20px;border:1px solid #f0e4d8;border-radius:20px;background:white;box-shadow:0 5px 18px rgba(112,67,46,.05)}
.select-product{width:21px;height:21px;accent-color:#70432e;cursor:pointer;margin-top:8px}
.cart-image{width:125px;height:125px;display:flex;align-items:center;justify-content:center;overflow:hidden;border-radius:15px;background:#fff8ee}
.cart-image img{width:100%;height:100%;object-fit:contain;padding:8px}
.image-fallback{font-size:45px}
.cart-category{color:#b47b4d;font-size:13px;font-weight:800}
.cart-item-info h2{margin:5px 0 8px;color:#70432e;font-size:20px;font-weight:900;overflow-wrap:anywhere}
.cart-price{color:#f28b35;font-size:17px;font-weight:900}
.cart-stock{margin-top:5px;color:#827267;font-size:13px}
.cart-item-bottom{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-top:15px}
.quantity-control{display:inline-flex;align-items:center;gap:12px;padding:5px;border:1px solid #eaded2;border-radius:30px;background:#fffaf4}
.quantity-control button{width:32px;height:32px;border:none;border-radius:50%;background:#70432e;color:white;font-size:20px;font-weight:800;cursor:pointer}
.quantity-control button:disabled{opacity:.4;cursor:not-allowed}
.quantity-control span{min-width:18px;text-align:center;color:#403129;font-weight:900}
.cart-subtotal{color:#70432e;font-weight:900}
.cart-remove{margin-top:10px;padding:0;border:none;background:transparent;color:#c75c4b;font:inherit;font-size:14px;font-weight:800;cursor:pointer}
.cart-summary{position:sticky;top:20px;padding:24px;border:1px solid #f0e4d8;border-radius:20px;background:white;box-shadow:0 5px 18px rgba(112,67,46,.06)}
.cart-summary h2{margin:0 0 20px;color:#70432e;font-size:23px;font-weight:900}
.summary-row{display:flex;justify-content:space-between;gap:12px;margin:14px 0;color:#827267;font-size:15px}
.summary-row strong{color:#403129;text-align:right}
.summary-total{padding-top:16px;margin-top:18px;border-top:1px dashed #e5d5c7}
.summary-total span,.summary-total strong{color:#70432e;font-size:20px;font-weight:900}
.btn-checkout,.btn-continue{display:block;width:100%;padding:14px 16px;border-radius:30px;text-align:center;font:inherit;font-weight:900;text-decoration:none;cursor:pointer;box-sizing:border-box}
.btn-checkout{margin-top:22px;border:none;background:#70432e;color:white}
.btn-checkout:hover{background:#f28b35}
.btn-checkout:disabled{opacity:.5;cursor:not-allowed}
.btn-continue{margin-top:12px;border:1px solid #eaded2;background:white;color:#70432e}
.cart-empty{padding:55px 20px;border-radius:22px;background:#fff8ee;text-align:center}
.empty-icon{font-size:70px}
.cart-empty h2{margin:15px 0 8px;color:#70432e;font-size:27px;font-weight:900}
.cart-empty p{max-width:450px;margin:0 auto 20px;color:#827267;line-height:1.8}
.cart-empty .btn-continue{display:inline-block;width:auto;padding:12px 25px;background:#70432e;color:white}
.select-all-row{display:flex;align-items:center;gap:9px;margin-bottom:3px;color:#70432e;font-weight:800}
.select-all-row input{width:19px;height:19px;accent-color:#70432e}
@media(max-width:900px){.cart-layout{grid-template-columns:1fr}.cart-summary{position:static}}
@media(max-width:600px){
.cart-page{padding:28px 5% 45px}.cart-banner{padding:24px 20px}
.cart-banner h1{font-size:28px}.cart-banner p{font-size:15px}
.cart-item{grid-template-columns:23px 78px minmax(0,1fr);gap:9px;padding:12px}
.cart-image{width:78px;height:90px}.cart-item-info h2{font-size:16px}
.cart-price{font-size:15px}.cart-item-bottom{align-items:flex-start;flex-direction:column}
.cart-summary{padding:20px}
}
</style>
</head>

<body>
<header class="navbar">
    <a href="index.php" class="brand">
        <span class="brand-paw">🐾</span>
        <span><strong>Pet<span class="brand-orange">Shop</span></strong><small>Happy Pets, Happy Life</small></span>
    </a>

    <button class="menu-toggle" id="menuToggle" aria-label="Buka menu" aria-expanded="false">☰</button>

    <nav class="nav-menu" id="navMenu">
        <a href="index.php" class="nav-link">Beranda</a>
        <a href="produk.php" class="nav-link">Produk</a>
        <a href="index.php#kategori" class="nav-link">Kategori</a>
        <a href="grooming.php" class="nav-link">Grooming</a>
        <a href="index.php#tentang" class="nav-link">Tentang Kami</a>
    </nav>

    <div class="nav-actions">
        <a href="keranjang.php" class="cart-link" aria-label="Keranjang">🛒</a>
        <?php if ($namaPengguna !== ''): ?>
            <span class="welcome-user">Hai, <?= e($namaPengguna) ?>!</span>
            <a href="logout.php" class="btn-small">Keluar</a>
        <?php else: ?>
            <a href="login.php" class="login-link">Login</a>
            <a href="register.php" class="btn-small">Daftar</a>
        <?php endif; ?>
    </div>
</header>

<main class="cart-page">
    <section class="cart-banner">
        <div class="eyebrow">🐾 BELANJA KEBUTUHAN ANABUL</div>
        <h1>Keranjang Belanja ♡</h1>
        <p>Pilih produk yang ingin dibeli, lalu lanjutkan ke checkout.</p>
    </section>

    <?php if ($pesan !== ''): ?>
        <div class="cart-message"><?= e($pesan) ?></div>
    <?php endif; ?>

    <?php if (count($items) > 0): ?>
        <div class="cart-layout">
            <section class="cart-items">

                <label class="select-all-row">
                    <input type="checkbox" id="selectAll">
                    Pilih semua produk
                </label>

                <?php foreach ($items as $item): ?>
                    <?php $pathGambar = gambarProduk($item['gambar']); ?>

                    <article class="cart-item">
                        <div>
                            <input
                                type="checkbox"
                                class="select-product"
                                name="selected_items[]"
                                value="<?= (int)$item['cart_id'] ?>"
                                form="checkoutForm"
                                data-price="<?= e($item['harga']) ?>"
                                data-quantity="<?= (int)$item['jumlah'] ?>"
                                aria-label="Pilih <?= e($item['nama_produk']) ?>"
                            >
                        </div>

                        <div class="cart-image">
                            <?php if ($pathGambar !== ''): ?>
                                <img src="<?= e($pathGambar) ?>"
                                     alt="<?= e($item['nama_produk']) ?>"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
                            <?php endif; ?>
                            <span class="image-fallback" style="<?= $pathGambar !== '' ? 'display:none' : '' ?>">🐾</span>
                        </div>

                        <div class="cart-item-info">
                            <span class="cart-category"><?= e($item['nama_kategori'] ?? 'Produk') ?></span>
                            <h2><?= e($item['nama_produk']) ?></h2>
                            <div class="cart-price"><?= rupiah($item['harga']) ?></div>
                            <div class="cart-stock">Stok tersedia: <?= $item['stok'] ?></div>

                            <div class="cart-item-bottom">
                                <form method="POST" action="keranjang.php" class="quantity-control">
                                    <input type="hidden" name="cart_id" value="<?= (int)$item['cart_id'] ?>">
                                    <button type="submit" name="aksi" value="kurang"
                                        aria-label="Kurangi jumlah"
                                        <?= $item['jumlah'] <= 1 ? 'disabled' : '' ?>>−</button>
                                    <span><?= $item['jumlah'] ?></span>
                                    <button type="submit" name="aksi" value="tambah"
                                        aria-label="Tambah jumlah"
                                        <?= $item['jumlah'] >= $item['stok'] ? 'disabled' : '' ?>>+</button>
                                </form>

                                <div class="cart-subtotal">
                                    Subtotal: <?= rupiah($item['subtotal']) ?>
                                </div>
                            </div>

                            <form method="POST" action="keranjang.php"
                                  onsubmit="return confirm('Yakin ingin menghapus produk ini dari keranjang?');">
                                <input type="hidden" name="cart_id" value="<?= (int)$item['cart_id'] ?>">
                                <button type="submit" name="aksi" value="hapus" class="cart-remove">
                                    🗑 Hapus produk
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <aside class="cart-summary">
                <h2>Ringkasan Belanja</h2>

                <div class="summary-row">
                    <span>Produk dipilih</span>
                    <strong id="selectedCount">0 barang</strong>
                </div>

                <div class="summary-row">
                    <span>Subtotal produk</span>
                    <strong id="selectedSubtotal">Rp0</strong>
                </div>

                <div class="summary-row">
                    <span>Pengiriman</span>
                    <strong>Dihitung saat checkout</strong>
                </div>

                <div class="summary-row summary-total">
                    <span>Total</span>
                    <strong id="selectedTotal">Rp0</strong>
                </div>

                <form method="POST" action="checkout.php" id="checkoutForm">
                    <button type="submit" class="btn-checkout" id="checkoutButton" disabled>
                        Checkout produk dipilih →
                    </button>
                </form>

                <a href="produk.php" class="btn-continue">♡ Lanjut Belanja</a>
            </aside>
        </div>
    <?php else: ?>
        <section class="cart-empty">
            <div class="empty-icon">🛒</div>
            <h2>Keranjangmu masih kosong</h2>
            <p>Belum ada produk yang dipilih. Yuk, cari makanan, snack, vitamin, atau perlengkapan untuk hewan kesayanganmu!</p>
            <a href="produk.php" class="btn-continue">♡ Mulai Belanja</a>
        </section>
    <?php endif; ?>
</main>

<footer class="footer">
    <div class="footer-main">
        <div class="footer-brand">
            <a href="index.php" class="brand footer-logo">
                <span class="brand-paw">🐾</span>
                <span><strong>Pet<span class="brand-orange">Shop</span></strong><small>Happy Pets, Happy Life</small></span>
            </a>
            <p>Karena mereka juga bagian dari keluarga. ♡</p>
        </div>
        <div class="footer-column">
            <h3>Menu Utama</h3>
            <a href="index.php">Beranda</a>
            <a href="produk.php">Produk</a>
            <a href="grooming.php">Grooming</a>
        </div>
        <div class="footer-column">
            <h3>Akun Saya</h3>
            <a href="login.php">Login</a>
            <a href="register.php">Buat Akun</a>
            <a href="keranjang.php">Keranjang</a>
        </div>
        <div class="footer-illustration">
            <span>🐶</span><span>🐱</span>
            <p>Happy Pets,<br>Happy Life! ♡</p>
        </div>
    </div>
    <div class="footer-bottom">
        <span>© <?= date('Y') ?> PetShop. All rights reserved.</span>
        <span>Dibuat dengan ♡ untuk sahabat berbulu.</span>
    </div>
</footer>

<script>
const menuToggle = document.getElementById('menuToggle');
const navMenu = document.getElementById('navMenu');

menuToggle.addEventListener('click', function () {
    const isOpen = navMenu.classList.toggle('show');
    menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
});

navMenu.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () {
        navMenu.classList.remove('show');
        menuToggle.setAttribute('aria-expanded', 'false');
    });
});

const checkboxes = Array.from(document.querySelectorAll('.select-product'));
const selectAll = document.getElementById('selectAll');
const selectedCount = document.getElementById('selectedCount');
const selectedSubtotal = document.getElementById('selectedSubtotal');
const selectedTotal = document.getElementById('selectedTotal');
const checkoutButton = document.getElementById('checkoutButton');
const checkoutForm = document.getElementById('checkoutForm');

function formatRupiah(angka) {
    return 'Rp' + Math.round(angka).toLocaleString('id-ID');
}

function updateSelection() {
    let jumlahBarang = 0;
    let subtotal = 0;
    let terpilih = 0;

    checkboxes.forEach(function (checkbox) {
        if (checkbox.checked) {
            terpilih++;
            jumlahBarang += Number(checkbox.dataset.quantity);
            subtotal += Number(checkbox.dataset.price) * Number(checkbox.dataset.quantity);
        }
    });

    selectedCount.textContent = jumlahBarang + ' barang';
    selectedSubtotal.textContent = formatRupiah(subtotal);
    selectedTotal.textContent = formatRupiah(subtotal);
    checkoutButton.disabled = terpilih === 0;
    selectAll.checked = checkboxes.length > 0 && terpilih === checkboxes.length;
    selectAll.indeterminate = terpilih > 0 && terpilih < checkboxes.length;
}

checkboxes.forEach(function (checkbox) {
    checkbox.addEventListener('change', updateSelection);
});

selectAll.addEventListener('change', function () {
    checkboxes.forEach(function (checkbox) {
        checkbox.checked = selectAll.checked;
    });
    updateSelection();
});

checkoutForm.addEventListener('submit', function (event) {
    const terpilih = checkboxes.some(function (checkbox) {
        return checkbox.checked;
    });

    if (!terpilih) {
        event.preventDefault();
        alert('Pilih minimal satu produk yang ingin di-checkout.');
    }
});

updateSelection();
</script>
</body>
</html>