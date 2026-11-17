
<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

$uploadDirectory = __DIR__ . '/../assests/images/';

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function createSlug($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'product';
}

function uploadProductImage($file, $directory) {

    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }

    if ($file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('Image must be smaller than 3 MB.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($file['tmp_name']);

    if (!isset($allowedTypes[$mimeType])) {
        throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
    }

    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('Image directory could not be created.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    $destination = $directory . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }

    return $fileName;
}

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

// Load categories
$categories = $pdo->query(
    "SELECT id, name FROM categories ORDER BY name ASC"
)->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Invalid request. Please refresh the page.');
        }

        $action = $_POST['action'] ?? '';

        // Add or update product
        if ($action === 'save') {

            $id = (int) ($_POST['product_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = $_POST['price'] ?? '';
            $categoryId = $_POST['category_id'] ?? '';
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

            if ($name === '') {
                throw new RuntimeException('Product name is required.');
            }

            if (!is_numeric($price) || (float) $price < 0) {
                throw new RuntimeException('Please enter a valid price.');
            }

            $categoryId = $categoryId === '' ? null : (int) $categoryId;

            if ($categoryId !== null) {
                $checkCategory = $pdo->prepare(
                    "SELECT id FROM categories WHERE id = ?"
                );
                $checkCategory->execute([$categoryId]);

                if (!$checkCategory->fetch()) {
                    throw new RuntimeException('Selected category does not exist.');
                }
            }

            $image = uploadProductImage(
                $_FILES['image'] ?? null,
                $uploadDirectory
            );

            if ($id > 0) {

                $checkProduct = $pdo->prepare(
                    "SELECT id, image FROM products WHERE id = ?"
                );
                $checkProduct->execute([$id]);
                $existingProduct = $checkProduct->fetch();

                if (!$existingProduct) {
                    throw new RuntimeException('Product not found.');
                }

                $slug = createSlug($name);

                $checkSlug = $pdo->prepare(
                    "SELECT id FROM products WHERE slug = ? AND id != ?"
                );
                $checkSlug->execute([$slug, $id]);

                if ($checkSlug->fetch()) {
                    $slug .= '-' . $id;
                }

                $image = $image ?? $existingProduct['image'];

                $stmt = $pdo->prepare(
                    "UPDATE products
                     SET category_id = ?,
                         name = ?,
                         slug = ?,
                         description = ?,
                         price = ?,
                         image = ?,
                         is_featured = ?
                     WHERE id = ?"
                );

                $stmt->execute([
                    $categoryId,
                    $name,
                    $slug,
                    $description,
                    $price,
                    $image,
                    $isFeatured,
                    $id
                ]);

                $success = 'Product updated successfully.';

            } else {

                $slug = createSlug($name);

                $checkSlug = $pdo->prepare(
                    "SELECT id FROM products WHERE slug = ?"
                );
                $checkSlug->execute([$slug]);

                if ($checkSlug->fetch()) {
                    $slug .= '-' . bin2hex(random_bytes(3));
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO products
                    (category_id, name, slug, description, price, image, is_featured)
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $categoryId,
                    $name,
                    $slug,
                    $description,
                    $price,
                    $image,
                    $isFeatured
                ]);

                $success = 'Product added successfully.';
            }
        }

        // Delete product and its associated offers
        elseif ($action === 'delete') {

            $id = (int) ($_POST['product_id'] ?? 0);

            if ($id <= 0) {
                throw new RuntimeException('Invalid product ID.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "DELETE FROM product_offers WHERE product_id = ?"
            );
            $stmt->execute([$id]);

            $stmt = $pdo->prepare(
                "DELETE FROM products WHERE id = ?"
            );
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Product not found.');
            }

            $pdo->commit();

            $success = 'Product and its price offers deleted successfully.';

        } else {
            throw new RuntimeException('Invalid action.');
        }

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());
        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Something went wrong. Please check the server error log.';
    }
}

// Load product for editing
$editProduct = null;

if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {

    $stmt = $pdo->prepare(
        "SELECT * FROM products WHERE id = ?"
    );

    $stmt->execute([(int) $_GET['edit']]);
    $editProduct = $stmt->fetch();
}

// Load products
$products = $pdo->query(
    "SELECT
        p.id,
        p.name,
        p.price,
        p.image,
        p.is_featured,
        c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.id DESC"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Products | NEXCART</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6fa;
            color: #172033;
        }

        .page {
            max-width: 1250px;
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
            grid-template-columns: repeat(2, 1fr);
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

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dce2eb;
            border-radius: 8px;
            font: inherit;
            font-size: 14px;
            background: white;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .featured {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 14px;
        }

        .featured input {
            width: auto;
        }

        .btn {
            border: none;
            border-radius: 8px;
            padding: 12px 19px;
            cursor: pointer;
            font-size: 14px;
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

        .product-image {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 7px;
            background: #f1f3f7;
        }

        .actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .actions form {
            margin: 0;
        }

        .featured-label {
            color: #2563eb;
            font-weight: 600;
        }

        @media (max-width: 650px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<main class="page">

    <header class="header">
        <div>
            <h1>Manage Products</h1>
            <p>Add, edit and manage your NEXCART products.</p>
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
            <?= $editProduct ? 'Edit Product' : 'Add New Product' ?>
        </h2>

        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
            <input type="hidden" name="action" value="save">

            <input
                type="hidden"
                name="product_id"
                value="<?= (int) ($editProduct['id'] ?? 0) ?>"
            >

            <div class="form-grid">

                <div class="form-group">
                    <label>Product Name</label>

                    <input
                        type="text"
                        name="name"
                        required
                        value="<?= escape($editProduct['name'] ?? '') ?>"
                        placeholder="Example: Premium Smartphone"
                    >
                </div>

                <div class="form-group">
                    <label>Category</label>

                    <select name="category_id">

                        <option value="">Select Category</option>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= (int) $category['id'] ?>"
                                <?= isset($editProduct['category_id']) &&
                                    (int) $editProduct['category_id'] === (int) $category['id']
                                    ? 'selected' : '' ?>
                            >
                                <?= escape($category['name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Base Price ($)</label>

                    <input
                        type="number"
                        name="price"
                        min="0"
                        step="0.01"
                        required
                        value="<?= escape($editProduct['price'] ?? '0.00') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Product Image</label>

                    <input
                        type="file"
                        name="image"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <small>JPG, PNG or WEBP — maximum 3 MB.</small>

                    <?php if (!empty($editProduct['image'])): ?>
                        <small>
                            Current image: <?= escape($editProduct['image']) ?>
                        </small>
                    <?php endif; ?>
                </div>

                <div class="form-group full">
                    <label>Description</label>

                    <textarea
                        name="description"
                        placeholder="Write product description..."
                    ><?= escape($editProduct['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group full">
                    <label class="featured">
                        <input
                            type="checkbox"
                            name="is_featured"
                            value="1"
                            <?= !empty($editProduct['is_featured']) ? 'checked' : '' ?>
                        >
                        Featured Product
                    </label>
                </div>

                <div class="form-group full">

                    <div class="actions">

                        <button type="submit" class="btn btn-primary">
                            <?= $editProduct ? 'Update Product' : 'Add Product' ?>
                        </button>

                        <?php if ($editProduct): ?>
                            <a href="manage-products.php" class="btn btn-edit">
                                Cancel
                            </a>
                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </form>

    </section>

    <section class="panel">

        <h2>All Products (<?= count($products) ?>)</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Base Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($products)): ?>

                        <tr>
                            <td colspan="6">No products found.</td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($products as $product): ?>

                            <tr>

                                <td>
                                    <?php if (!empty($product['image'])): ?>
                                        <img
                                            class="product-image"
                                            src="../assests/images/<?= rawurlencode(basename($product['image'])) ?>"
                                            alt="<?= escape($product['name']) ?>"
                                        >
                                    <?php else: ?>
                                        <div class="product-image"></div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= escape($product['name']) ?>
                                </td>

                                <td>
                                    <?= escape($product['category_name'] ?? 'Uncategorized') ?>
                                </td>

                                <td>
                                    $<?= number_format((float) $product['price'], 2) ?>
                                </td>

                                <td>
                                    <?= !empty($product['is_featured'])
                                        ? '<span class="featured-label">Featured</span>'
                                        : 'Regular' ?>
                                </td>

                                <td>

                                    <div class="actions">

                                        <a
                                            class="btn btn-edit"
                                            href="manage-products.php?edit=<?= (int) $product['id'] ?>"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Delete this product and all its price offers?');"
                                        >

                                            <input type="hidden" name="csrf_token" value="<?= escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

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
