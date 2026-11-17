
<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectWithMessage($message, $type = 'success')
{
    $_SESSION['offer_message'] = $message;
    $_SESSION['offer_message_type'] = $type;

    header('Location: manage-offers.php');
    exit;
}

function validHttpUrl($url)
{
    if ($url === '') {
        return true;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    return in_array($scheme, ['http', 'https'], true);
}

// CSRF token
if (empty($_SESSION['offers_csrf'])) {
    $_SESSION['offers_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['offers_csrf'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($csrfToken, $_POST['csrf_token'])
    ) {
        redirectWithMessage('Invalid security token. Please try again.', 'error');
    }

    $action = $_POST['action'] ?? '';

    // Delete offer
    if ($action === 'delete') {

        $offerId = filter_var($_POST['offer_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$offerId || $offerId < 1) {
            redirectWithMessage('Invalid offer selected.', 'error');
        }

        $stmt = $pdo->prepare(
            "DELETE FROM product_offers WHERE id = ?"
        );

        $stmt->execute([$offerId]);

        redirectWithMessage(
            $stmt->rowCount() ? 'Price offer deleted successfully.' : 'Offer not found.',
            $stmt->rowCount() ? 'success' : 'error'
        );
    }

    // Add or update offer
    if (in_array($action, ['add', 'update'], true)) {

        $offerId = filter_var($_POST['offer_id'] ?? null, FILTER_VALIDATE_INT);
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $storeId = filter_var($_POST['store_id'] ?? null, FILTER_VALIDATE_INT);

        $priceInput = trim($_POST['price'] ?? '');
        $productUrl = trim($_POST['product_url'] ?? '');
        $availability = $_POST['availability'] ?? '';

        if (
            !$productId || $productId < 1 ||
            !$storeId || $storeId < 1
        ) {
            redirectWithMessage('Please select a valid product and store.', 'error');
        }

        if (
            $priceInput === '' ||
            !is_numeric($priceInput) ||
            (float)$priceInput < 0
        ) {
            redirectWithMessage('Please enter a valid price.', 'error');
        }

        if (!validHttpUrl($productUrl)) {
            redirectWithMessage('Please enter a valid HTTP or HTTPS product URL.', 'error');
        }

        if (!in_array($availability, ['available', 'unavailable'], true)) {
            redirectWithMessage('Invalid availability status.', 'error');
        }

        // Check product exists
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$productId]);

        if (!$stmt->fetch()) {
            redirectWithMessage('Selected product does not exist.', 'error');
        }

        // Check store exists
        $stmt = $pdo->prepare("SELECT id FROM stores WHERE id = ?");
        $stmt->execute([$storeId]);

        if (!$stmt->fetch()) {
            redirectWithMessage('Selected store does not exist.', 'error');
        }

        // Prevent duplicate product/store combinations
        if ($action === 'add') {

            $stmt = $pdo->prepare(
                "SELECT id FROM product_offers
                 WHERE product_id = ? AND store_id = ?
                 LIMIT 1"
            );

            $stmt->execute([$productId, $storeId]);

        } else {

            if (!$offerId || $offerId < 1) {
                redirectWithMessage('Invalid offer selected.', 'error');
            }

            $stmt = $pdo->prepare(
                "SELECT id FROM product_offers
                 WHERE product_id = ?
                 AND store_id = ?
                 AND id != ?
                 LIMIT 1"
            );

            $stmt->execute([$productId, $storeId, $offerId]);
        }

        if ($stmt->fetch()) {
            redirectWithMessage(
                'This store already has an offer for the selected product.',
                'error'
            );
        }

        $urlValue = $productUrl !== '' ? $productUrl : null;

        if ($action === 'add') {

            $stmt = $pdo->prepare(
                "INSERT INTO product_offers
                (product_id, store_id, price, product_url, availability)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $productId,
                $storeId,
                $priceInput,
                $urlValue,
                $availability
            ]);

            redirectWithMessage('Price offer added successfully.');

        } else {

            $stmt = $pdo->prepare(
                "UPDATE product_offers
                 SET product_id = ?,
                     store_id = ?,
                     price = ?,
                     product_url = ?,
                     availability = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $productId,
                $storeId,
                $priceInput,
                $urlValue,
                $availability,
                $offerId
            ]);

            redirectWithMessage('Price offer updated successfully.');
        }
    }

    redirectWithMessage('Invalid request.', 'error');
}

// Flash message
$message = $_SESSION['offer_message'] ?? '';
$messageType = $_SESSION['offer_message_type'] ?? 'success';

unset($_SESSION['offer_message'], $_SESSION['offer_message_type']);

// Fetch products
$products = $pdo->query(
    "SELECT id, name FROM products ORDER BY name ASC"
)->fetchAll();

// Fetch stores
$stores = $pdo->query(
    "SELECT id, name FROM stores ORDER BY name ASC"
)->fetchAll();

// Edit mode
$editOffer = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

if ($editId && $editId > 0) {

    $stmt = $pdo->prepare(
        "SELECT * FROM product_offers WHERE id = ?"
    );

    $stmt->execute([$editId]);
    $editOffer = $stmt->fetch();

    if (!$editOffer) {
        $message = 'The selected offer was not found.';
        $messageType = 'error';
    }
}

// Fetch all offers
$offers = $pdo->query(
    "SELECT
        po.*,
        p.name AS product_name,
        s.name AS store_name
     FROM product_offers po
     INNER JOIN products p ON p.id = po.product_id
     INNER JOIN stores s ON s.id = po.store_id
     ORDER BY p.name ASC, po.price ASC"
)->fetchAll();

$totalOffers = count($offers);
$availableOffers = 0;

foreach ($offers as $offer) {
    if ($offer['availability'] === 'available') {
        $availableOffers++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Offers | NEXCART Admin</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f3f5f9;
            color: #202938;
        }

        .admin-container {
            max-width: 1450px;
            margin: auto;
            padding: 35px 25px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #7b8494;
            font-size: 14px;
        }

        .back-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: white;
            padding: 22px;
            border-radius: 13px;
            border: 1px solid #e8ebf1;
        }

        .stat-card span {
            display: block;
            color: #7b8494;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .stat-card strong {
            font-size: 27px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 25px;
            align-items: start;
        }

        .panel {
            background: white;
            border: 1px solid #e8ebf1;
            border-radius: 14px;
            padding: 25px;
        }

        .panel h2 {
            font-size: 19px;
            margin-bottom: 22px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 9px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #dce1e9;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #2563eb;
        }

        .form-hint {
            display: block;
            color: #8992a2;
            font-size: 11px;
            margin-top: 7px;
        }

        .btn {
            display: inline-block;
            border: none;
            border-radius: 8px;
            padding: 12px 17px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
            width: 100%;
        }

        .btn-secondary {
            background: #edf0f5;
            color: #374151;
            margin-top: 10px;
            width: 100%;
        }

        .message {
            padding: 14px 17px;
            border-radius: 9px;
            margin-bottom: 22px;
            font-size: 14px;
        }

        .message.success {
            background: #e8f8ee;
            color: #187343;
        }

        .message.error {
            background: #feecec;
            color: #b42318;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 720px;
        }

        th {
            background: #f8f9fc;
            color: #778195;
            font-size: 12px;
            text-align: left;
            padding: 14px 12px;
            white-space: nowrap;
        }

        td {
            padding: 15px 12px;
            border-bottom: 1px solid #edf0f4;
            font-size: 13px;
        }

        .price {
            font-weight: bold;
            color: #172b4d;
            white-space: nowrap;
        }

        .status {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .available {
            background: #e6f8ed;
            color: #16834a;
        }

        .unavailable {
            background: #fff0e8;
            color: #c45a20;
        }

        .actions {
            display: flex;
            gap: 7px;
            align-items: center;
        }

        .btn-edit {
            background: #eaf1ff;
            color: #2563eb;
            padding: 8px 11px;
        }

        .btn-delete {
            background: #feecec;
            color: #dc2626;
            padding: 8px 11px;
        }

        .product-url {
            color: #2563eb;
            text-decoration: none;
        }

        .product-url:hover {
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: 40px 15px;
            color: #8992a2;
        }

        @media (max-width: 1000px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .admin-container {
                padding: 20px 14px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .panel {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<div class="admin-container">

    <header class="page-header">
        <div>
            <h1>Price Offers</h1>
            <p>Manage product prices and store offers.</p>
        </div>

        <a href="dashboard.php" class="back-link">
            ← Back to Dashboard
        </a>
    </header>

    <?php if ($message): ?>
        <div class="message <?= e($messageType) ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <section class="stats">

        <div class="stat-card">
            <span>Total Offers</span>
            <strong><?= $totalOffers ?></strong>
        </div>

        <div class="stat-card">
            <span>Available Offers</span>
            <strong><?= $availableOffers ?></strong>
        </div>

        <div class="stat-card">
            <span>Unavailable Offers</span>
            <strong><?= $totalOffers - $availableOffers ?></strong>
        </div>

    </section>

    <div class="content-grid">

        <!-- Add / Edit Form -->
        <section class="panel">

            <h2>
                <?= $editOffer ? 'Edit Price Offer' : 'Add New Offer' ?>
            </h2>

            <?php if (empty($products) || empty($stores)): ?>

                <p class="empty-state">
                    You need at least one product and one store before creating an offer.
                </p>

            <?php else: ?>

                <form method="POST" action="manage-offers.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="<?= $editOffer ? 'update' : 'add' ?>"
                    >

                    <?php if ($editOffer): ?>
                        <input
                            type="hidden"
                            name="offer_id"
                            value="<?= (int)$editOffer['id'] ?>"
                        >
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="product_id">Product</label>

                        <select name="product_id" id="product_id" required>
                            <option value="">Select a product</option>

                            <?php foreach ($products as $product): ?>
                                <option
                                    value="<?= (int)$product['id'] ?>"
                                    <?= (
                                        $editOffer &&
                                        (int)$editOffer['product_id'] === (int)$product['id']
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= e($product['name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="form-group">
                        <label for="store_id">Store</label>

                        <select name="store_id" id="store_id" required>
                            <option value="">Select a store</option>

                            <?php foreach ($stores as $store): ?>
                                <option
                                    value="<?= (int)$store['id'] ?>"
                                    <?= (
                                        $editOffer &&
                                        (int)$editOffer['store_id'] === (int)$store['id']
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= e($store['name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="form-group">
                        <label for="price">Price ($)</label>

                        <input
                            type="number"
                            name="price"
                            id="price"
                            min="0"
                            step="0.01"
                            placeholder="Example: 899.99"
                            value="<?= $editOffer ? e($editOffer['price']) : '' ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="product_url">Product URL</label>

                        <input
                            type="url"
                            name="product_url"
                            id="product_url"
                            placeholder="https://example.com/product"
                            value="<?= $editOffer ? e($editOffer['product_url'] ?? '') : '' ?>"
                        >

                        <span class="form-hint">
                            Optional. Must start with HTTP or HTTPS.
                        </span>
                    </div>

                    <div class="form-group">
                        <label for="availability">Availability</label>

                        <select name="availability" id="availability" required>

                            <option
                                value="available"
                                <?= (
                                    $editOffer &&
                                    $editOffer['availability'] === 'available'
                                ) ? 'selected' : '' ?>
                            >
                                Available
                            </option>

                            <option
                                value="unavailable"
                                <?= (
                                    $editOffer &&
                                    $editOffer['availability'] === 'unavailable'
                                ) ? 'selected' : '' ?>
                            >
                                Unavailable
                            </option>

                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?= $editOffer ? 'Update Offer' : 'Add Offer' ?>
                    </button>

                    <?php if ($editOffer): ?>
                        <a href="manage-offers.php" class="btn btn-secondary">
                            Cancel Editing
                        </a>
                    <?php endif; ?>

                </form>

            <?php endif; ?>

        </section>

        <!-- Offers Table -->
        <section class="panel">

            <h2>All Price Offers</h2>

            <?php if (empty($offers)): ?>

                <div class="empty-state">
                    No price offers found.
                </div>

            <?php else: ?>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Store</th>
                                <th>Price</th>
                                <th>Product Link</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($offers as $offer): ?>

                                <tr>

                                    <td>
                                        <strong><?= e($offer['product_name']) ?></strong>
                                    </td>

                                    <td><?= e($offer['store_name']) ?></td>

                                    <td class="price">
                                        $<?= number_format((float)$offer['price'], 2) ?>
                                    </td>

                                    <td>
                                        <?php
                                        $offerUrl = $offer['product_url'] ?? '';
                                        $offerScheme = strtolower(
                                            (string) parse_url($offerUrl, PHP_URL_SCHEME)
                                        );
                                        $safeOfferUrl = (
                                            filter_var($offerUrl, FILTER_VALIDATE_URL) &&
                                            in_array($offerScheme, ['http', 'https'], true)
                                        );
                                        ?>

                                        <?php if ($safeOfferUrl): ?>
                                            <a
                                                href="<?= e($offerUrl) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="product-url"
                                            >
                                                View Link
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#9ca3af;">No link</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="status <?= e($offer['availability']) ?>">
                                            <?= ucfirst(e($offer['availability'])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= e(date('M d, Y', strtotime($offer['created_at']))) ?>
                                    </td>

                                    <td>

                                        <div class="actions">

                                            <a
                                                href="manage-offers.php?edit=<?= (int)$offer['id'] ?>"
                                                class="btn btn-edit"
                                            >
                                                Edit
                                            </a>

                                            <form
                                                method="POST"
                                                action="manage-offers.php"
                                                onsubmit="return confirm('Are you sure you want to delete this offer?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($csrfToken) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="delete"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="offer_id"
                                                    value="<?= (int)$offer['id'] ?>"
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

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    </div>

</div>

</body>
</html>
