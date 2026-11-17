
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

function createSlug($text) {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-') ?: 'category';
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

            $id = (int) ($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $icon = trim($_POST['icon'] ?? '');

            if ($name === '') {
                throw new RuntimeException('Category name is required.');
            }

            if (mb_strlen($name) > 100) {
                throw new RuntimeException('Category name is too long.');
            }

            if (mb_strlen($icon) > 20) {
                throw new RuntimeException('Icon must not exceed 20 characters.');
            }

            $slug = createSlug($name);

            if ($id > 0) {

                $check = $pdo->prepare(
                    "SELECT id FROM categories WHERE id = ?"
                );
                $check->execute([$id]);

                if (!$check->fetch()) {
                    throw new RuntimeException('Category not found.');
                }

                $checkSlug = $pdo->prepare(
                    "SELECT id FROM categories WHERE slug = ? AND id != ?"
                );
                $checkSlug->execute([$slug, $id]);

                if ($checkSlug->fetch()) {
                    $slug .= '-' . $id;
                }

                $stmt = $pdo->prepare(
                    "UPDATE categories
                     SET name = ?, slug = ?, icon = ?
                     WHERE id = ?"
                );

                $stmt->execute([$name, $slug, $icon, $id]);

                $success = 'Category updated successfully.';

            } else {

                $checkSlug = $pdo->prepare(
                    "SELECT id FROM categories WHERE slug = ?"
                );
                $checkSlug->execute([$slug]);

                if ($checkSlug->fetch()) {
                    $slug .= '-' . bin2hex(random_bytes(3));
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO categories (name, slug, icon)
                     VALUES (?, ?, ?)"
                );

                $stmt->execute([$name, $slug, $icon]);

                $success = 'Category added successfully.';
            }

        } elseif ($action === 'delete') {

            $id = (int) ($_POST['category_id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('Invalid category ID.');
            }

            // Prevent deleting categories that still contain products.
            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM products WHERE category_id = ?"
            );
            $check->execute([$id]);

            if ((int) $check->fetchColumn() > 0) {
                throw new RuntimeException(
                    'This category contains products. Move those products to another category first.'
                );
            }

            $stmt = $pdo->prepare(
                "DELETE FROM categories WHERE id = ?"
            );
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Category not found.');
            }

            $success = 'Category deleted successfully.';

        } else {
            throw new RuntimeException('Invalid action.');
        }

    } catch (PDOException $e) {

        error_log($e->getMessage());

        if ($e->getCode() === '23000') {
            $error = 'A category with this information already exists.';
        } else {
            $error = 'A database error occurred.';
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// Edit category
$editCategory = null;

if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {

    $stmt = $pdo->prepare(
        "SELECT * FROM categories WHERE id = ?"
    );

    $stmt->execute([(int) $_GET['edit']]);
    $editCategory = $stmt->fetch();
}

// Categories list
$categories = $pdo->query(
    "SELECT
        c.id,
        c.name,
        c.slug,
        c.icon,
        COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name, c.slug, c.icon
     ORDER BY c.id DESC"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Categories | NEXCART</title>

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
            grid-template-columns: 2fr 1fr;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 9px;
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
            min-width: 550px;
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

        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .actions form {
            margin: 0;
        }

        .category-icon {
            font-size: 20px;
        }

        @media (max-width: 650px) {
            .header {
                align-items: flex-start;
                flex-direction: column;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<main class="page">

    <header class="header">
        <div>
            <h1>Manage Categories</h1>
            <p>Organize your product categories.</p>
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
            <?= $editCategory ? 'Edit Category' : 'Add New Category' ?>
        </h2>

        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
            <input type="hidden" name="action" value="save">

            <input
                type="hidden"
                name="category_id"
                value="<?= (int) ($editCategory['id'] ?? 0) ?>"
            >

            <div class="form-grid">

                <div class="form-group">
                    <label>Category Name</label>

                    <input
                        type="text"
                        name="name"
                        required
                        maxlength="100"
                        placeholder="Example: Smartphones"
                        value="<?= escape($editCategory['name'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Category Icon (Emoji)</label>

                    <input
                        type="text"
                        name="icon"
                        maxlength="20"
                        placeholder="📱"
                        value="<?= escape($editCategory['icon'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <div class="actions">

                        <button type="submit" class="btn btn-primary">
                            <?= $editCategory ? 'Update Category' : 'Add Category' ?>
                        </button>

                        <?php if ($editCategory): ?>
                            <a href="manage-categories.php" class="btn btn-edit">
                                Cancel
                            </a>
                        <?php endif; ?>

                    </div>
                </div>

            </div>

        </form>

    </section>

    <section class="panel">

        <h2>All Categories (<?= count($categories) ?>)</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Icon</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Products</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($categories)): ?>

                        <tr>
                            <td colspan="5">No categories found.</td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($categories as $category): ?>

                            <tr>

                                <td class="category-icon">
                                    <?= escape($category['icon'] ?? '') ?>
                                </td>

                                <td>
                                    <?= escape($category['name']) ?>
                                </td>

                                <td>
                                    <?= escape($category['slug']) ?>
                                </td>

                                <td>
                                    <?= (int) $category['product_count'] ?>
                                </td>

                                <td>

                                    <div class="actions">

                                        <a
                                            href="manage-categories.php?edit=<?= (int) $category['id'] ?>"
                                            class="btn btn-edit"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Delete this category?');"
                                        >

                                            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">

                                            <button type="submit" class="btn btn-delete">
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
