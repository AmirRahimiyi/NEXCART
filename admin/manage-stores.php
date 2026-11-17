
<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function validateWebsite($website) {

    if ($website === '') {
        return null;
    }

    if (!filter_var($website, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Please enter a valid website URL.');
    }

    $scheme = strtolower(parse_url($website, PHP_URL_SCHEME) ?? '');

    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new RuntimeException('Website must use HTTP or HTTPS.');
    }

    return $website;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Invalid request. Please refresh the page.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'save') {

            $id = (int) ($_POST['store_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $website = trim($_POST['website'] ?? '');

            if ($name === '') {
                throw new RuntimeException('Store name is required.');
            }

            if (mb_strlen($name) > 150) {
                throw new RuntimeException('Store name is too long.');
            }

            $website = validateWebsite($website);

            if ($id > 0) {

                $check = $pdo->prepare(
                    "SELECT id FROM stores WHERE id = ?"
                );

                $check->execute([$id]);

                if (!$check->fetch()) {
                    throw new RuntimeException('Store not found.');
                }

                $stmt = $pdo->prepare(
                    "UPDATE stores
                     SET name = ?, website = ?
                     WHERE id = ?"
                );

                $stmt->execute([$name, $website, $id]);

                $success = 'Store updated successfully.';

            } else {

                $stmt = $pdo->prepare(
                    "INSERT INTO stores (name, website)
                     VALUES (?, ?)"
                );

                $stmt->execute([$name, $website]);

                $success = 'Store added successfully.';
            }

        } elseif ($action === 'delete') {

            $id = (int) ($_POST['store_id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('Invalid store ID.');
            }

            // Prevent deleting stores that have price offers.
            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM product_offers WHERE store_id = ?"
            );

            $check->execute([$id]);

            if ((int) $check->fetchColumn() > 0) {
                throw new RuntimeException(
                    'This store has price offers. Remove or reassign those offers first.'
                );
            }

            $stmt = $pdo->prepare(
                "DELETE FROM stores WHERE id = ?"
            );

            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Store not found.');
            }

            $success = 'Store deleted successfully.';

        } else {
            throw new RuntimeException('Invalid action.');
        }

    } catch (PDOException $e) {

        error_log($e->getMessage());

        if ($e->getCode() === '23000') {
            $error = 'This store information conflicts with an existing record.';
        } else {
            $error = 'A database error occurred.';
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// Edit store
$editStore = null;

if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {

    $stmt = $pdo->prepare(
        "SELECT * FROM stores WHERE id = ?"
    );

    $stmt->execute([(int) $_GET['edit']]);
    $editStore = $stmt->fetch();
}

// Load stores
$stores = $pdo->query(
    "SELECT
        s.id,
        s.name,
        s.website,
        s.created_at,
        COUNT(po.id) AS offer_count
     FROM stores s
     LEFT JOIN product_offers po ON po.store_id = s.id
     GROUP BY s.id, s.name, s.website, s.created_at
     ORDER BY s.id DESC"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Stores | NEXCART</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6fa;
            color: #172033;
        }

        .page {
            max-width: 1150px;
            margin: auto;
            padding: 35px 22px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 27px;
            margin-bottom: 8px;
        }

        .header p {
            color: #788397;
            font-size: 14px;
        }

        .back-link {
            color: #2563eb;
            text-decoration: none;
            font-size: 14px;
        }

        .panel {
            background: white;
            padding: 25px;
            border-radius: 13px;
            margin-bottom: 25px;
            box-shadow: 0 4px 18px rgba(0,0,0,.035);
        }

        .panel h2 {
            font-size: 19px;
            margin-bottom: 22px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 13px;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #dce2eb;
            border-radius: 8px;
            font: inherit;
        }

        .btn {
            border: none;
            border-radius: 8px;
            padding: 12px 18px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-edit {
            background: #e8efff;
            color: #2563eb;
        }

        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }

        .message {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #edf0f5;
            font-size: 13px;
        }

        th {
            color: #788397;
        }

        td a.website {
            color: #2563eb;
            text-decoration: none;
        }

        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .actions form {
            margin: 0;
        }

        @media (max-width: 650px) {

            .header {
                align-items: flex-start;
                flex-direction: column;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }
        }

    </style>

</head>

<body>

<main class="page">

    <header class="header">

        <div>
            <h1>Manage Stores</h1>
            <p>Manage stores and their product listings.</p>
        </div>

        <a href="dashboard.php" class="back-link">
            ← Back to Dashboard
        </a>

    </header>

    <?php if ($success): ?>
        <div class="message success">
            <?= escape($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="message error">
            <?= escape($error) ?>
        </div>
    <?php endif; ?>

    <section class="panel">

        <h2>
            <?= $editStore ? 'Edit Store' : 'Add New Store' ?>
        </h2>

        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">

            <input type="hidden" name="action" value="save">

            <input
                type="hidden"
                name="store_id"
                value="<?= (int) ($editStore['id'] ?? 0) ?>"
            >

            <div class="form-grid">

                <div class="form-group">

                    <label>Store Name</label>

                    <input
                        type="text"
                        name="name"
                        maxlength="150"
                        required
                        placeholder="Example: Amazon"
                        value="<?= escape($editStore['name'] ?? '') ?>"
                    >

                </div>

                <div class="form-group">

                    <label>Website URL</label>

                    <input
                        type="url"
                        name="website"
                        placeholder="https://example.com"
                        value="<?= escape($editStore['website'] ?? '') ?>"
                    >

                </div>

                <div class="form-group full">

                    <div class="actions">

                        <button type="submit" class="btn btn-primary">
                            <?= $editStore ? 'Update Store' : 'Add Store' ?>
                        </button>

                        <?php if ($editStore): ?>

                            <a
                                href="manage-stores.php"
                                class="btn btn-edit"
                            >
                                Cancel
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </form>

    </section>

    <section class="panel">

        <h2>All Stores (<?= count($stores) ?>)</h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>ID</th>
                        <th>Store Name</th>
                        <th>Website</th>
                        <th>Price Offers</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if (empty($stores)): ?>

                        <tr>
                            <td colspan="6">No stores found.</td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($stores as $store): ?>

                            <tr>

                                <td>
                                    #<?= (int) $store['id'] ?>
                                </td>

                                <td>
                                    <?= escape($store['name']) ?>
                                </td>

                                <td>

                                    <?php if (!empty($store['website'])): ?>

                                        <a
                                            class="website"
                                            href="<?= escape($store['website']) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Visit Website
                                        </a>

                                    <?php else: ?>

                                        Not provided

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= (int) $store['offer_count'] ?>
                                </td>

                                <td>
                                    <?= escape($store['created_at']) ?>
                                </td>

                                <td>

                                    <div class="actions">

                                        <a
                                            href="manage-stores.php?edit=<?= (int) $store['id'] ?>"
                                            class="btn btn-edit"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Delete this store?');"
                                        >

                                            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">

                                            <input type="hidden" name="action" value="delete">

                                            <input
                                                type="hidden"
                                                name="store_id"
                                                value="<?= (int) $store['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-delete"
                                            >
                                                Delete
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</body>
</html>
