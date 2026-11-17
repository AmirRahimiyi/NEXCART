
<?php

session_start();

require_once __DIR__ . '/../config/database.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {

        $stmt = $pdo->prepare(
            "SELECT id, name, email, password
             FROM admins
             WHERE email = :email
             AND is_active = 1
             LIMIT 1"
        );

        $stmt->execute([
            'email' => $email
        ]);

        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {

            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];

            header('Location: dashboard.php');
            exit;

        } else {
            $error = 'Invalid email or password.';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | NEXCART</title>

    <link rel="stylesheet" href="../assests/css/style.css">
</head>

<body>

<main class="admin-auth-page">

    <div class="admin-auth-card">

        <h1>NEXCART</h1>

        <p>Administrator Login</p>

        <?php if ($error): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="form-group">

                <label>Email Address</label>

                <input
                    type="email"
                    name="email"
                    required
                    autocomplete="username"
                >

            </div>

            <div class="form-group">

                <label>Password</label>

                <input
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                >

            </div>

            <button type="submit" class="btn-primary">
                Sign In
            </button>

        </form>

        <a href="../index.php" class="admin-back-link">
            Back to Store
        </a>

    </div>

</main>

</body>
</html>
