
<?php

require_once __DIR__ . '/../config/database.php';

/* =========================================
   01. VALIDATE PRODUCT ID
========================================= */

if (
    !isset($_GET['id']) ||
    !ctype_digit((string) $_GET['id']) ||
    (int) $_GET['id'] < 1
) {
    http_response_code(404);
    exit('Product not found.');
}

$productId = (int) $_GET['id'];

/* =========================================
   02. PROJECT URL
========================================= */

$baseUrl = rtrim(
    dirname(dirname($_SERVER['SCRIPT_NAME'])),
    '/\\'
);

if ($baseUrl === '.' || $baseUrl === '/') {
    $baseUrl = '';
}

/* =========================================
   03. HELPER FUNCTIONS
========================================= */

function productEscape($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function isValidProductUrl($url)
{
    if (
        !is_string($url) ||
        trim($url) === '' ||
        !filter_var($url, FILTER_VALIDATE_URL)
    ) {
        return false;
    }

    $scheme = strtolower(
        (string) parse_url($url, PHP_URL_SCHEME)
    );

    return in_array($scheme, ['http', 'https'], true);
}

function getDetailProductImage($image, $baseUrl)
{
    if (!is_string($image) || trim($image) === '') {
        return null;
    }

    $image = trim($image);

    // External image
    if (filter_var($image, FILTER_VALIDATE_URL)) {
        return isValidProductUrl($image) ? $image : null;
    }

    // Local image
    $localImage = str_replace('\\', '/', $image);
    $localImage = ltrim($localImage, '/');

    if (strpos($localImage, 'nexcart/') === 0) {
        $localImage = substr($localImage, 8);
    }

    if (
        strpos($localImage, '..') !== false ||
        strpos($localImage, '://') !== false
    ) {
        return null;
    }

    $projectRoot = realpath(__DIR__ . '/..');

    if (!$projectRoot) {
        return null;
    }

    $filePath = realpath(
        $projectRoot . DIRECTORY_SEPARATOR .
        str_replace('/', DIRECTORY_SEPARATOR, $localImage)
    );

    if (
        !$filePath ||
        !is_file($filePath) ||
        strpos(
            $filePath,
            $projectRoot . DIRECTORY_SEPARATOR
        ) !== 0
    ) {
        return null;
    }

    $encodedPath = implode(
        '/',
        array_map('rawurlencode', explode('/', $localImage))
    );

    return $baseUrl . '/' . $encodedPath;
}

/* =========================================
   04. FETCH PRODUCT
========================================= */

$productStmt = $pdo->prepare("
    SELECT
        p.*,
        c.name AS category_name,
        c.icon AS category_icon

    FROM products p

    LEFT JOIN categories c
        ON c.id = p.category_id

    WHERE p.id = ?

    LIMIT 1
");

$productStmt->execute([$productId]);

$product = $productStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}

/* =========================================
   05. FETCH STORE OFFERS
========================================= */

$offersStmt = $pdo->prepare("
    SELECT
        po.id AS offer_id,
        po.price,
        po.product_url,
        po.availability,
        po.created_at,

        s.id AS store_id,
        s.name AS store_name,
        s.website AS store_website

    FROM product_offers po

    INNER JOIN stores s
        ON s.id = po.store_id

    WHERE po.product_id = ?
      AND po.availability = 'available'

    ORDER BY
        po.price ASC,
        s.name ASC
");

$offersStmt->execute([$productId]);

$offers = $offersStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   06. PRICE CALCULATIONS
========================================= */

$hasOffers = !empty($offers);

$bestPrice = null;
$highestPrice = null;
$potentialSavings = 0;
$savingsPercentage = 0;

if ($hasOffers) {

    $prices = array_map(
        fn($offer) => (float) $offer['price'],
        $offers
    );

    $bestPrice = min($prices);
    $highestPrice = max($prices);

    $potentialSavings = max(
        0,
        $highestPrice - $bestPrice
    );

    $savingsPercentage = $highestPrice > 0
        ? ($potentialSavings / $highestPrice) * 100
        : 0;
}

/* =========================================
   07. PRODUCT IMAGE
========================================= */

$productImage = getDetailProductImage(
    $product['image'] ?? null,
    $baseUrl
);

/* =========================================
   08. PAGE SETTINGS
========================================= */

$pageTitle = productEscape($product['name']) . ' | NEXCART';

require_once __DIR__ . '/../includes/header.php';

?>

<!-- =========================================
     PRODUCT DETAIL PAGE
========================================= -->

<section class="product-detail-page">

    <div class="container">

        <!-- BREADCRUMB -->

        <nav class="breadcrumb" aria-label="Breadcrumb">

            <a href="<?= productEscape($baseUrl) ?>/index.php">
                Home
            </a>

            <span>/</span>

            <a href="<?= productEscape($baseUrl) ?>/pages/products.php">
                Products
            </a>

            <span>/</span>

            <span>
                <?= productEscape($product['name']) ?>
            </span>

        </nav>


        <!-- PRODUCT INFORMATION -->

        <section class="product-detail">

            <div class="product-detail-image">

                <?php if ($productImage): ?>

                    <img
                        src="<?= productEscape($productImage) ?>"
                        alt="<?= productEscape($product['name']) ?>"
                        onerror="this.onerror=null;this.style.display='none';this.parentElement.classList.add('image-error');"
                    >

                <?php else: ?>

                    <div class="product-placeholder">
                        <?= productEscape($product['category_icon'] ?? '📦') ?>
                    </div>

                <?php endif; ?>

            </div>


            <div class="product-detail-info">

                <span class="section-label">
                    <?= productEscape(
                        $product['category_name'] ?? 'GENERAL'
                    ) ?>
                </span>


                <h1>
                    <?= productEscape($product['name']) ?>
                </h1>


                <p class="product-detail-description">
                    <?= nl2br(productEscape(
                        trim($product['description'] ?? '') !== ''
                            ? $product['description']
                            : 'No description available.'
                    )) ?>
                </p>


                <!-- PRICE SUMMARY -->

                <div class="detail-price-box">

                    <?php if ($hasOffers): ?>

                        <span class="detail-price-label">
                            Best available price
                        </span>

                        <strong>
                            $<?= number_format($bestPrice, 2) ?>
                        </strong>

                        <small>
                            <?= count($offers) ?>
                            <?= count($offers) === 1
                                ? 'available store offer'
                                : 'available store offers' ?>
                        </small>

                    <?php else: ?>

                        <span class="detail-price-label">
                            Store prices
                        </span>

                        <strong class="no-price-value">
                            Not available
                        </strong>

                        <small>
                            No active store offers for this product yet.
                        </small>

                    <?php endif; ?>

                </div>


                <!-- SAVINGS -->

                <?php if ($hasOffers && $highestPrice > $bestPrice): ?>

                    <div class="potential-savings-card">

                        <div class="savings-icon">
                            ↓
                        </div>

                        <div class="savings-content">

                            <span class="savings-label">
                                Potential Savings
                            </span>

                            <strong>
                                $<?= number_format($potentialSavings, 2) ?>
                            </strong>

                            <small>
                                <?= number_format($savingsPercentage, 1) ?>%
                                lower than the highest listed price
                            </small>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- COMPARE BUTTON -->

                <a
                    href="#price-comparison"
                    class="primary-btn detail-compare-link"
                >
                    Compare Store Prices
                    <span>↓</span>
                </a>

            </div>

        </section>


        <!-- =====================================
             PRICE COMPARISON
        ====================================== -->

        <section
            class="comparison-section"
            id="price-comparison"
        >

            <div class="section-heading">

                <div>

                    <span class="section-label">
                        PRICE COMPARISON
                    </span>

                    <h2>
                        Compare Store Prices
                    </h2>

                    <p>
                        Find the best available deal from our listed stores.
                    </p>

                </div>

                <?php if ($hasOffers): ?>

                    <span class="comparison-count">
                        <?= count($offers) ?>
                        <?= count($offers) === 1 ? 'Offer' : 'Offers' ?>
                    </span>

                <?php endif; ?>

            </div>


            <?php if ($hasOffers): ?>

                <div class="comparison-table-wrapper">

                    <table class="comparison-table">

                        <thead>

                            <tr>
                                <th>Store</th>
                                <th>Price</th>
                                <th>Price Difference</th>
                                <th>Availability</th>
                                <th>Action</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($offers as $index => $offer): ?>

                                <?php

                                $offerPrice = (float) $offer['price'];

                                $priceDifference = max(
                                    0,
                                    $offerPrice - $bestPrice
                                );

                                $isBestOffer = ($index === 0);

                                $storeUrl = isValidProductUrl(
                                    $offer['product_url']
                                )
                                    ? $offer['product_url']
                                    : (
                                        isValidProductUrl(
                                            $offer['store_website']
                                        )
                                            ? $offer['store_website']
                                            : null
                                    );

                                ?>

                                <tr class="<?= $isBestOffer ? 'best-offer-row' : '' ?>">

                                    <!-- STORE -->

                                    <td>

                                        <div class="store-cell">

                                            <strong class="store-name">
                                                <?= productEscape(
                                                    $offer['store_name']
                                                ) ?>
                                            </strong>

                                            <?php if ($isBestOffer): ?>

                                                <span class="lowest-price-label">
                                                    Lowest Price
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    </td>


                                    <!-- PRICE -->

                                    <td>

                                        <strong class="offer-price">
                                            $<?= number_format($offerPrice, 2) ?>
                                        </strong>

                                    </td>


                                    <!-- DIFFERENCE -->

                                    <td>

                                        <?php if ($isBestOffer): ?>

                                            <span class="price-difference-best">
                                                Best deal
                                            </span>

                                        <?php else: ?>

                                            <span class="price-difference">
                                                +$<?= number_format(
                                                    $priceDifference,
                                                    2
                                                ) ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- AVAILABILITY -->

                                    <td>

                                        <span class="availability-status">
                                            <span class="status-dot"></span>
                                            Available
                                        </span>

                                    </td>


                                    <!-- ACTION -->

                                    <td>

                                        <?php if ($storeUrl): ?>

                                            <a
                                                href="<?= productEscape($storeUrl) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="compare-btn"
                                            >
                                                Visit Store
                                                <span>↗</span>
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


                <!-- PRICE DISCLAIMER -->

                <p class="comparison-disclaimer">
                    Prices and availability are based on the offers currently
                    listed on NEXCART. Please confirm the final price with
                    the store before purchasing.
                </p>


            <?php else: ?>

                <!-- EMPTY STATE -->

                <div class="empty-offers">

                    <div class="empty-offers-icon">
                        ↗
                    </div>

                    <h3>
                        No store offers yet
                    </h3>

                    <p>
                        There are currently no available store prices
                        for this product. Check back later for new offers.
                    </p>

                    <a
                        href="<?= productEscape($baseUrl) ?>/pages/products.php"
                        class="primary-btn"
                    >
                        Explore Other Products
                    </a>

                </div>

            <?php endif; ?>

        </section>


        <!-- BACK TO PRODUCTS -->

        <div class="product-back-link">

            <a
                href="<?= productEscape($baseUrl) ?>/pages/products.php"
                class="text-link"
            >
                ← Back to Products
            </a>

        </div>

    </div>

</section>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
