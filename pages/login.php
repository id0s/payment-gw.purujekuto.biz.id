<?php
/**
 * RFID Payment Gateway - Login Page
 */
require_once __DIR__ . '/../config.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_nama'] = $admin['nama'];
                $_SESSION['admin_role'] = $admin['role'];

                // Update last login
                $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
                logActivity("Admin '{$admin['username']}' berhasil login");

                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Username atau password salah';
                logActivity("Login gagal untuk username '$username'");
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <meta name="description" content="Login ke admin panel RFID Payment Gateway">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <div class="logo-icon">💳</div>
                <h1><?= APP_NAME ?></h1>
                <p>Self-Hosted Payment Gateway</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <span>❌</span> <?= sanitize($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="login-form">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username" 
                           value="<?= sanitize($_POST['username'] ?? '') ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:0.5rem">
                    🔐 Masuk
                </button>
            </form>

            <p style="text-align:center;margin-top:1.5rem;font-size:0.75rem;color:var(--text-muted)">
                <?= APP_NAME ?> v<?= APP_VERSION ?>
            </p>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
</body>
</html>
