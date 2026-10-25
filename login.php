<?php
session_start();
require_once 'config/koneksi.php';

$error = '';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi!';
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT id, nama, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = 'Email atau password salah!';
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pet Shop</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background: #fff8ef;
            color: #49372c;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 24px;
            padding: 36px;
            box-shadow: 0 10px 35px rgba(90, 61, 43, 0.12);
        }

        .logo {
            text-align: center;
            font-size: 52px;
            margin-bottom: 8px;
        }

        h1 {
            text-align: center;
            font-size: 30px;
            margin: 0 0 10px;
            color: #70452e;
        }

        .subtitle {
            text-align: center;
            font-size: 16px;
            color: #857469;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 9px;
        }

        input {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid #e4d4c6;
            border-radius: 12px;
            font-size: 16px;
            outline: none;
            background: #fffdf9;
        }

        input:focus {
            border-color: #c77b45;
            box-shadow: 0 0 0 3px rgba(199, 123, 69, 0.12);
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: #a9663e;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 17px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 5px;
        }

        .btn-login:hover {
            background: #87502f;
        }

        .error {
            background: #fff0ed;
            color: #b33b2e;
            border: 1px solid #f2c6bd;
            border-radius: 10px;
            padding: 13px;
            font-size: 15px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .register-link {
            text-align: center;
            font-size: 15px;
            margin-top: 24px;
            line-height: 1.8;
        }

        .register-link a {
            color: #a9663e;
            font-weight: bold;
            text-decoration: none;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #79685c;
            font-size: 15px;
            text-decoration: none;
        }

        .back-link:hover,
        .register-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 26px 22px;
            }

            h1 {
                font-size: 27px;
            }
        }
    </style>
</head>

<body>

    <div class="login-container">
        <div class="logo">🐾</div>

        <h1>Selamat Datang!</h1>

        <p class="subtitle">
            Login ke akun Pet Shop untuk mulai belanja
            kebutuhan hewan kesayanganmu.
        </p>

        <?php if ($error !== ''): ?>
            <div class="error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Masukkan email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Masukkan password"
                    required
                >
            </div>

            <button type="submit" class="btn-login">
                Login
            </button>
        </form>

        <div class="register-link">
            Belum punya akun?
            <a href="register.php">Daftar sekarang</a>
        </div>

        <a href="index.php" class="back-link">
            ← Kembali ke Beranda
        </a>
    </div>

</body>
</html>