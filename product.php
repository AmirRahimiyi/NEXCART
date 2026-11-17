
<?php

require_once __DIR__ . '/../config/database.php';

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    http_response_code(404);
    exit('Product not found.');
}

$productId = (int) $_GET['id'];

// Fetch product details
$productStmt = $pdo->prepare("
    SELECT
        p.*,
        c.name AS category_name,
        c.icon AS category_icon
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = :id
    LIMIT 1
");

$productStmt->execute(['id' => $productId]);
$product = $productStmt->fetch();

if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}

// Fetch available offers
$offersStmt = $pdo->prepare("
    SELECT
        po.price,
        po.product_url,
        po.availability,
        s.name AS store_name,
        s.website AS store_website
    FROM product_offers po
    INNER JOIN stores s ON po.store_id = s.id
    WHERE po.product_id = :id
      AND po.availability = 'available'
    ORDER BY po.price ASC
");

$offersStmt->execute(['id' => $productId]);
$offers = $offersStmt->fetchAll();

$bestPrice = !empty($offers)
    ? (float) $offers[0]['price']
    : (float) $product['price'];

$pageTitle = $product['name'] . ' | NEXCART';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle) ?></title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<header class="main-header">
    <div class="container header-content">

        <a href="../index.php" class="logo">
            NEX<span>CART</span>
        </a>

        <nav class="main-nav">
            <a href="../index.php">Home</a>
            <a href="products.php">Products</a>
            <a href="../index.php#categories">Categories</a>
        </nav>

        <div class="header-actions">
            <a href="products.php" class="header-btn">
                Explore Products
            </a>
        </div>

    </div>
</header>


<main class="product-detail-page">

    <div class="container">

        <div class="breadcrumb">
            <a href="../index.php">Home</a>
            <span>/</span>
            <a href="products.php">Products</a>
            <span>/</span>
            <span><?= htmlspecialchars($product['name']) ?></span>
        </div>


        <section class="product-detail">

            <div class="product-detail-image">

                <?php if (!empty($product['image'])): ?>

                    <img
                        src="../assets/images/<?= htmlspecialchars($product['image']) ?>"
                        alt="<?= htmlspecialchars($product['name']) ?>"
                    >

                <?php else: ?>

                    <div class="product-placeholder">
                        <?= htmlspecialchars($product['category_icon'] ?? '📦') ?>
                    </div>

                <?php endif; ?>

            </div>


            <div class="product-detail-info">

                <span class="section-label">
                    <?= htmlspecialchars($product['category_name'] ?? 'GENERAL') ?>
                </span>

                <h1>
                    <?= htmlspecialchars($product['name']) ?>
                </h1>

                <p class="product-detail-description">
                    <?= nl2br(htmlspecialchars($product['description'] ?? 'No description available.')) ?>
                </p>

                <div class="detail-price-box">

                    <span>Best available price</span>

                    <strong>
                        $<?= number_format($bestPrice, 2) ?>
                    </strong>

                    <?php if (!empty($offers)): ?>
                        <small>
                            Found across <?= count($offers) ?> available offers
                        </small>
                    <?php else: ?>
                        <small>
                            No store offers available yet
                        </small>
                    <?php endif; ?>

                </div>

            </div>

        </section>


        <section class="comparison-section">

            <div class="section-heading">

                <div>
                    <span class="section-label">
                        PRICE COMPARISON
                    </span>

                    <h2>Compare Store Prices</h2>
                </div>

            </div>


            <?php if (!empty($offers)): ?>

                <div class="comparison-table-wrapper">

                    <table class="comparison-table">

                        <thead>
                            <tr>
                                <th>Store</th>
                                <th>Price</th>
                                <th>Availability</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($offers as $index => $offer): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($offer['store_name']) ?>
                                        </strong>

                                        <?php if ($index === 0): ?>
                                            <span class="best-offer-badge">
                                                BEST PRICE
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <strong class="offer-price">
                                            $<?= number_format((float) $offer['price'], 2) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <span class="availability-status">
                                            Available
                                        </span>
                                    </td>

                                    <td>

                                        <?php
                                        $offerUrl = $offer['product_url'];
                                        $validOfferUrl = filter_var(
                                            $offerUrl,
                                            FILTER_VALIDATE_URL
                                        ) && in_array(
                                            strtolower((string) parse_url($offerUrl, PHP_URL_SCHEME)),
                                            ['http', 'https'],
                                            true
                                        );
                                        ?>

                                        <?php if ($validOfferUrl): ?>

                                            <a
                                                href="<?= htmlspecialchars($offerUrl) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="compare-btn"
                                            >
                                                Visit Store
                                            </a>

                                        <?php else: ?>

                                            <span class="store-link-disabled">
                                                Link unavailable
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-offers">
                    <h3>No store offers yet</h3>
                    <p>
                        There are currently no available store prices
                        for this product.
                    </p>
                </div>

            <?php endif; ?>

        </section>

    </div>

</main>


<footer class="main-footer">

    <div class="container footer-content">

        <a href="../index.php" class="logo">
            NEX<span>CART</span>
        </a>

        <p>Smart Shopping. Better Choices.</p>

        <span>
            © <?= date('Y') ?> NEXCART. All rights reserved.
        </span>

    </div>

</footer>

</body>
</html>
