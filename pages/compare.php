
<?php

require_once __DIR__ . '/../config/database.php';

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Read selected product IDs
$rawIds = $_GET['ids'] ?? [];

if (!is_array($rawIds)) {
    $rawIds = explode(',', $rawIds);
}

$selectedIds = [];

foreach ($rawIds as $id) {
    $id = filter_var($id, FILTER_VALIDATE_INT);

    if ($id && $id > 0) {
        $selectedIds[] = $id;
    }
}

$selectedIds = array_values(array_unique($selectedIds));

// Maximum three products
$selectedIds = array_slice($selectedIds, 0, 3);

// Fetch all products for the selector
$allProducts = $pdo->query(
    "SELECT id, name FROM products ORDER BY name ASC"
)->fetchAll();

$products = [];
$offers = [];

if (!empty($selectedIds)) {

    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));

    // Fetch selected products and their price statistics
    $stmt = $pdo->prepare(
        "SELECT
            p.*,
            c.name AS category_name,

            (
                SELECT MIN(po.price)
                FROM product_offers po
                WHERE po.product_id = p.id
                AND po.availability = 'available'
            ) AS best_price,

            (
                SELECT COUNT(*)
                FROM product_offers po
                WHERE po.product_id = p.id
                AND po.availability = 'available'
            ) AS offer_count

         FROM products p

         LEFT JOIN categories c
            ON c.id = p.category_id

         WHERE p.id IN ($placeholders)
         ORDER BY FIELD(p.id, $placeholders)"
    );

    $stmt->execute(array_merge($selectedIds, $selectedIds));
    $products = $stmt->fetchAll();

    // Fetch available offers for selected products
    $stmt = $pdo->prepare(
        "SELECT
            po.product_id,
            po.price,
            po.product_url,
            s.name AS store_name

         FROM product_offers po

         INNER JOIN stores s
            ON s.id = po.store_id

         WHERE po.product_id IN ($placeholders)
         AND po.availability = 'available'

         ORDER BY po.price ASC"
    );

    $stmt->execute($selectedIds);
    $allOffers = $stmt->fetchAll();

    foreach ($allOffers as $offer) {
        $offers[$offer['product_id']][] = $offer;
    }
}

// Check selected IDs that were not found
$foundIds = array_map('intval', array_column($products, 'id'));

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Compare Products | NEXCART</title>

    <link rel="stylesheet" href="../assests/css/style.css">

    <style>
        .compare-page {
            max-width: 1250px;
            margin: 55px auto;
            padding: 0 22px;
        }

        .compare-header {
            margin-bottom: 35px;
        }

        .compare-header h1 {
            font-size: 34px;
            margin-bottom: 10px;
        }

        .compare-header p {
            color: #778195;
            line-height: 1.7;
        }

        .compare-selector {
            background: var(--card-bg, #ffffff);
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .compare-selector h2 {
            font-size: 19px;
            margin-bottom: 18px;
        }

        .product-selector-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }

        .selector-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px;
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 9px;
            cursor: pointer;
        }

        .selector-item input {
            accent-color: #2563eb;
            width: 16px;
            height: 16px;
        }

        .selector-item span {
            font-size: 14px;
        }

        .compare-submit {
            margin-top: 20px;
            border: none;
            background: #2563eb;
            color: white;
            padding: 13px 22px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        .compare-submit:hover {
            background: #1d4ed8;
        }

        .compare-table-wrapper {
            overflow-x: auto;
            background: var(--card-bg, #ffffff);
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 15px;
            margin-bottom: 35px;
        }

        .compare-table {
            width: 100%;
            min-width: 650px;
            border-collapse: collapse;
        }

        .compare-table th,
        .compare-table td {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid var(--border-color, #e5e7eb);
            vertical-align: middle;
        }

        .compare-table th:first-child,
        .compare-table td:first-child {
            text-align: left;
            font-weight: bold;
            min-width: 160px;
        }

        .compare-table th {
            font-size: 14px;
            background: var(--soft-bg, #f8fafc);
        }

        .compare-table td {
            font-size: 14px;
        }

        .compare-product-name {
            font-size: 16px;
            line-height: 1.5;
        }

        .compare-product-image {
            width: 115px;
            height: 115px;
            object-fit: contain;
            margin: 12px auto;
            display: block;
        }

        .compare-price {
            color: #2563eb;
            font-size: 20px;
            font-weight: bold;
        }

        .compare-no-price {
            color: #9ca3af;
        }

        .compare-best {
            color: #16834a;
            font-weight: bold;
        }

        .compare-link {
            display: inline-block;
            margin-top: 12px;
            color: #2563eb;
            text-decoration: none;
            font-size: 13px;
        }

        .compare-link:hover {
            text-decoration: underline;
        }

        .offers-section {
            margin-top: 35px;
        }

        .offers-section h2 {
            font-size: 22px;
            margin-bottom: 20px;
        }

        .offers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
        }

        .offer-card {
            background: var(--card-bg, #ffffff);
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 13px;
            padding: 20px;
        }

        .offer-card h3 {
            font-size: 16px;
            margin-bottom: 15px;
        }

        .offer-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color, #e5e7eb);
            font-size: 13px;
        }

        .offer-row:last-child {
            border-bottom: none;
        }

        .offer-row strong {
            color: #2563eb;
            white-space: nowrap;
        }

        .empty-compare {
            text-align: center;
            padding: 55px 20px;
            background: var(--card-bg, #ffffff);
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 15px;
        }

        .empty-compare h2 {
            margin-bottom: 12px;
        }

        .empty-compare p {
            color: #778195;
        }

        .demo-notice {
            background: #fff8e6;
            color: #91620b;
            border: 1px solid #f4dfaa;
            padding: 13px 16px;
            border-radius: 9px;
            font-size: 13px;
            margin-bottom: 25px;
        }

        @media (max-width: 600px) {
            .compare-page {
                margin: 35px auto;
                padding: 0 14px;
            }

            .compare-header h1 {
                font-size: 27px;
            }

            .compare-selector {
                padding: 18px;
            }
        }
    </style>

</head>

<body>

<?php
// Use the existing site header if it is stored in a shared file.
?>

<main class="compare-page">

    <header class="compare-header">

        <h1>Compare Products</h1>

        <p>
            Compare product details, available prices and stores
            before making your shopping decision.
        </p>

    </header>

    <div class="demo-notice">
        Demo notice: Product prices and store offers displayed on this page
        are sample data for demonstration purposes.
    </div>

    <section class="compare-selector">

        <h2>Select Products</h2>

        <form method="GET" action="compare.php">

            <div class="product-selector-grid">

                <?php foreach ($allProducts as $item): ?>

                    <label class="selector-item">

                        <input
                            type="checkbox"
                            name="ids[]"
                            value="<?= (int)$item['id'] ?>"
                            <?= in_array((int)$item['id'], $selectedIds, true) ? 'checked' : '' ?>
                        >

                        <span><?= e($item['name']) ?></span>

                    </label>

                <?php endforeach; ?>

            </div>

            <button type="submit" class="compare-submit">
                Compare Selected Products
            </button>

        </form>

        <p style="font-size:12px;color:#8992a2;margin-top:12px;">
            You can compare up to 3 products at a time.
        </p>

    </section>

    <?php if (empty($selectedIds)): ?>

        <section class="empty-compare">

            <h2>Start Comparing</h2>

            <p>
                Select at least one product above to view its details
                and compare available offers.
            </p>

        </section>

    <?php elseif (empty($products)): ?>

        <section class="empty-compare">

            <h2>No Products Found</h2>

            <p>
                The selected products could not be found.
            </p>

        </section>

    <?php else: ?>

        <div class="compare-table-wrapper">

            <table class="compare-table">

                <thead>

                    <tr>

                        <th>Product Details</th>

                        <?php foreach ($products as $product): ?>

                            <th>
                                <div class="compare-product-name">
                                    <?= e($product['name']) ?>
                                </div>

                                <?php if (!empty($product['image'])): ?>

                                    <img
                                        src="../assests/images/<?= e(basename($product['image'])) ?>"
                                        alt="<?= e($product['name']) ?>"
                                        class="compare-product-image"
                                    >

                                <?php endif; ?>

                                <a
                                    href="product.php?id=<?= (int)$product['id'] ?>"
                                    class="compare-link"
                                >
                                    View Product
                                </a>

                            </th>

                        <?php endforeach; ?>

                    </tr>

                </thead>

                <tbody>

                    <tr>

                        <td>Category</td>

                        <?php foreach ($products as $product): ?>

                            <td>
                                <?= e($product['category_name'] ?? 'Uncategorized') ?>
                            </td>

                        <?php endforeach; ?>

                    </tr>

                    <tr>

                        <td>Description</td>

                        <?php foreach ($products as $product): ?>

                            <td>
                                <?= e($product['description'] ?: 'No description available.') ?>
                            </td>

                        <?php endforeach; ?>

                    </tr>

                    <tr>

                        <td>Best Available Price</td>

                        <?php foreach ($products as $product): ?>

                            <td>

                                <?php if ($product['best_price'] !== null): ?>

                                    <span class="compare-price">
                                        $<?= number_format((float)$product['best_price'], 2) ?>
                                    </span>

                                <?php else: ?>

                                    <span class="compare-no-price">
                                        No offers
                                    </span>

                                <?php endif; ?>

                            </td>

                        <?php endforeach; ?>

                    </tr>

                    <tr>

                        <td>Available Offers</td>

                        <?php foreach ($products as $product): ?>

                            <td>
                                <?= (int)$product['offer_count'] ?>
                            </td>

                        <?php endforeach; ?>

                    </tr>

                </tbody>

            </table>

        </div>

        <section class="offers-section">

            <h2>Store Price Comparison</h2>

            <div class="offers-grid">

                <?php foreach ($products as $product): ?>

                    <article class="offer-card">

                        <h3><?= e($product['name']) ?></h3>

                        <?php
                        $productOffers = $offers[$product['id']] ?? [];
                        ?>

                        <?php if (empty($productOffers)): ?>

                            <p style="color:#8992a2;font-size:13px;">
                                No available store offers.
                            </p>

                        <?php else: ?>

                            <?php foreach ($productOffers as $index => $offer): ?>

                                <div class="offer-row">

                                    <span>
                                        <?= e($offer['store_name']) ?>

                                        <?php if ($index === 0): ?>
                                            <span class="compare-best">(Best)</span>
                                        <?php endif; ?>
                                    </span>

                                    <strong>
                                        $<?= number_format((float)$offer['price'], 2) ?>
                                    </strong>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>

</main>

</body>
</html>
