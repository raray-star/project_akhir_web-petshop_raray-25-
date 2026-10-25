<?php
session_start();
require_once 'config/koneksi.php';

$error = '';
$nama = '';
$email = '';
$alamat = '';
$no_hp = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi'] ?? '';
    $alamat = trim($_POST['alamat'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');

    if (
        $nama === '' ||
        $email === '' ||
        $password === '' ||
        $konfirmasi === '' ||
        $alamat === '' ||
        $no_hp === ''
    ) {
        $error = 'Semua kolom wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        // Cek apakah email sudah digunakan
        $cek = mysqli_prepare(
            $koneksi,
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        mysqli_stmt_bind_param($cek, "s", $email);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            $error = 'Email sudah terdaftar. Gunakan email lain.';
        } else {
            mysqli_stmt_close($cek);

            // Password disimpan dalam bentuk hash
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'pelanggan';

            $stmt = mysqli_prepare(
                $koneksi,
                "INSERT INTO users
                (nama, email, password, role, alamat, no_hp)
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssssss",
                $nama,
                $email,
                $hash,
                $role,
                $alamat,
                $no_hp
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header('Location: login.php?daftar=berhasil');
                exit;
            } else {
                $error = 'Registrasi gagal. Silakan coba lagi.';
                mysqli_stmt_close($stmt);
            }
        }

        if (isset($cek) && $error !== '') {
            mysqli_stmt_close($cek);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun | PetShop</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --brown: #70432e;
            --orange: #e97e35;
            --cream: #fff8ee;
            --text: #403129;
            --muted: #827267;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            padding: 30px 16px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Nunito', sans-serif;
            color: var(--text);
            background: linear-gradient(135deg, #fff0dc, #fffaf5, #ffeded);
        }

        .register-wrapper {
            width: 100%;
            max-width: 520px;
        }

        .brand {
            margin-bottom: 22px;
            text-align: center;
        }

        .brand .paw {
            font-size: 43px;
        }

        .brand h1 {
            color: var(--brown);
            font-size: 32px;
            font-weight: 900;
        }

        .brand h1 span {
            color: var(--orange);
        }

        .brand p {
            color: var(--muted);
            font-size: 14px;
        }

        .register-card {
            padding: 32px;
            border: 1px solid #f0e5d9;
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 15px 45px #70432e12;
        }

        .register-card h2 {
            margin-bottom: 5px;
            color: var(--brown);
            font-size: 27px;
            font-weight: 900;
        }

        .subtitle {
            margin-bottom: 24px;
            color: var(--muted);
            font-size: 15px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 15px;
            font-weight: 800;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e9ddd2;
            border-radius: 12px;
            outline: none;
            background: #fffdfa;
            color: var(--text);
            font: inherit;
            font-size: 15px;
            transition: .2s;
        }

        input:focus,
        textarea:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px #e97e3518;
        }

        textarea {
            min-height: 85px;
            resize: vertical;
        }

        .error {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #fff0ef;
            color: #a83232;
            font-size: 14px;
            font-weight: 700;
        }

        .btn-register {
            width: 100%;
            margin-top: 5px;
            padding: 14px;
            border: none;
            border-radius: 30px;
            background: var(--brown);
            color: #fff;
            font: inherit;
            font-size: 16px;
            font-weight: 900;
            cursor: pointer;
            transition: .2s;
        }

        .btn-register:hover {
            background: var(--orange);
            transform: translateY(-1px);
        }

        .login-text {
            margin-top: 20px;
            text-align: center;
            color: var(--muted);
            font-size: 14px;
        }

        .login-text a {
            color: var(--orange);
            font-weight: 900;
            text-decoration: none;
        }

        .login-text a:hover {
            text-decoration: underline;
        }

        .back-home {
            display: block;
            margin-top: 18px;
            text-align: center;
            color: var(--brown);
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
        }

        @media (max-width: 480px) {
            .register-card {
                padding: 23px 19px;
            }

            .brand h1 {
                font-size: 28px;
            }

            .register-card h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<div class="register-wrapper">

    <div class="brand">
        <div class="paw">🐾</div>
        <h1>Pet<span>Shop</span></h1>
        <p>Happy Pets, Happy Life</p>
    </div>

    <div class="register-card">
        <h2>Buat Akun Baru 🐱</h2>
        <p class="subtitle">
            Daftar dulu agar bisa belanja dan booking grooming.
        </p>

        <?php if ($error !== ''): ?>
            <div class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">

            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input
                    type="text"
                    id="nama"
                    name="nama"
                    placeholder="Masukkan nama lengkap"
                    value="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>"
                    maxlength="100"
                    autocomplete="name"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="contoh@email.com"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                    maxlength="100"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Minimal 6 karakter"
                    minlength="6"
                    autocomplete="new-password"
                    required
                >
            </div>

            <div class="form-group">
                <label for="konfirmasi">Konfirmasi Password</label>
                <input
                    type="password"
                    id="konfirmasi"
                    name="konfirmasi"
                    placeholder="Ulangi password"
                    minlength="6"
                    autocomplete="new-password"
                    required
                >
            </div>

            <div class="form-group">
                <label for="no_hp">Nomor HP</label>
                <input
                    type="tel"
                    id="no_hp"
                    name="no_hp"
                    placeholder="Contoh: 081234567890"
                    value="<?= htmlspecialchars($no_hp, ENT_QUOTES, 'UTF-8') ?>"
                    maxlength="20"
                    autocomplete="tel"
                    required
                >
            </div>

            <div class="form-group">
                <label for="alamat">Alamat Lengkap</label>
                <textarea
                    id="alamat"
                    name="alamat"
                    placeholder="Masukkan alamat lengkap"
                    autocomplete="street-address"
                    required
                ><?= htmlspecialchars($alamat, ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <button type="submit" class="btn-register">
                Daftar Sekarang →
            </button>

        </form>

        <p class="login-text">
            Sudah punya akun?
            <a href="login.php">Login di sini</a>
        </p>

        <a href="index.php" class="back-home">
            ← Kembali ke Beranda
        </a>
    </div>

</div>

</body>
</html>