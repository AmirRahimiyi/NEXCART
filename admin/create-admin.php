
<?php

require_once __DIR__ . '/../config/database.php';

$message = '';
$error = '';

$adminCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM admins"
)->fetchColumn();

if ($adminCount > 0) {
    die('An admin account already exists. Account creation is disabled.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 10) {
        $error = 'Password must be at least 10 characters.';
    } else {

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO admins (name, email, password)
                 VALUES (:name, :email, :password)"
            );

            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword
            ]);

            $message = 'Admin account created successfully.';

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Could not create the admin account.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin | NEXCART</title>

    <link rel="stylesheet" href="../assests/css/style.css">
</head>

<body>

<main class="admin-auth-page">

    <div class="admin-auth-card">

        <h1>NEXCART</h1>
        <p>Create your administrator account</p>

        <?php if ($message): ?>
            <div class="success-message">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($adminCount === 0 && !$message): ?>

            <form method="POST">

                <div class="form-group">
                    <label>Full Name</label>
                    <input
                        type="text"
                        name="name"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input
                        type="email"
                        name="email"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input
                        type="password"
                        name="password"
                        minlength="10"
                        required
                    >
                </div>

                <button type="submit" class="btn-primary">
                    Create Admin Account
                </button>

            </form>

        <?php endif; ?>

    </div>

</main>

</body>
</html>
