
<?php

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'NEXCART | Stores';

/* =========================================
   01. HELPER FUNCTIONS
========================================= */

if (!function_exists('nexcartStoreEscape')) {
    function nexcartStoreEscape($value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

if (!function_exists('nexcartStoreSafeWebsite')) {
    function nexcartStoreSafeWebsite($url): bool
    {
        if (!is_string($url) || trim($url) === '') {
            return false;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower(
            (string) parse_url($url, PHP_URL_SCHEME)
        );

        return in_array($scheme, ['http', 'https'], true);
    }
}

if (!function_exists('nexcartStoreInitial')) {
    function nexcartStoreInitial($name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'N';
        }

        if (function_exists('mb_substr')) {
            return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
        }

        return strtoupper(substr($name, 0, 1));
    }
}

/* =========================================
   02. FETCH STORES
========================================= */

$sql = "
    SELECT
        s.id,
        s.name,
        s.website,

        COUNT(po.id) AS total_offers,

        COUNT(
            CASE
                WHEN po.availability = 'available' THEN 1
            END
        ) AS available_offers,

        COUNT(
            DISTINCT CASE
                WHEN po.availability = 'available'
                THEN po.product_id
            END
        ) AS product_count,

        MIN(
            CASE
                WHEN po.availability = 'available'
                THEN po.price
                ELSE NULL
            END
        ) AS lowest_price

    FROM stores s

    LEFT JOIN product_offers po
        ON po.store_id = s.id

    GROUP BY
        s.id,
        s.name,
        s.website

    ORDER BY
        available_offers DESC,
        s.name ASC
";

$stmt = $pdo->query($sql);

$stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   03. GLOBAL STATISTICS
========================================= */

$totalStores = count($stores);
$totalOffers = 0;
$totalAvailable = 0;
$totalProducts = 0;

foreach ($stores as $store) {
    $totalOffers += (int) $store['total_offers'];
    $totalAvailable += (int) $store['available_offers'];
    $totalProducts += (int) $store['product_count'];
}

/* =========================================
   04. HEADER
========================================= */

require_once __DIR__ . '/../includes/header.php';

?>

<div class="stores-page">

    <!-- HERO -->

    <section class="stores-hero">

        <div class="stores-hero-content">

            <span class="stores-eyebrow">
                <span class="stores-eyebrow-dot"></span>
                NEXCART MARKETPLACE
            </span>

            <h1>
                Discover Our <span>Stores.</span>
            </h1>

            <p>
                Explore trusted stores, discover their product offers,
                and compare prices to find smarter shopping opportunities.
            </p>

            <div class="stores-hero-meta">

                <span>
                    <strong><?= number_format($totalStores) ?></strong>
                    Registered stores
                </span>

                <span class="stores-meta-divider"></span>

                <span>
                    <strong><?= number_format($totalAvailable) ?></strong>
                    Active offers
                </span>

            </div>

        </div>

        <div class="stores-hero-decoration" aria-hidden="true">

            <div class="stores-decoration-circle circle-one"></div>
            <div class="stores-decoration-circle circle-two"></div>

            <div class="stores-decoration-card">

                <span class="decoration-icon">↗</span>

                <span class="decoration-caption">
                    SMARTER SHOPPING
                </span>

                <strong>
                    Compare.<br>
                    Discover.<br>
                    Save.
                </strong>

                <div class="decoration-line"></div>

                <span class="decoration-footer">
                    Powered by NEXCART
                </span>

            </div>

        </div>

    </section>

    <!-- STATISTICS -->

    <section class="stores-stats">

        <article class="store-stat">

            <div class="store-stat-icon">⌂</div>

            <div class="store-stat-info">

                <span>Registered Stores</span>

                <strong>
                    <?= number_format($totalStores) ?>
                </strong>

                <small>Stores on NEXCART</small>

            </div>

        </article>

        <article class="store-stat">

            <div class="store-stat-icon">▤</div>

            <div class="store-stat-info">

                <span>Total Price Offers</span>

                <strong>
                    <?= number_format($totalOffers) ?>
                </strong>

                <small>Offers listed across stores</small>

            </div>

        </article>

        <article class="store-stat">

            <div class="store-stat-icon">✓</div>

            <div class="store-stat-info">

                <span>Available Offers</span>

                <strong>
                    <?= number_format($totalAvailable) ?>
                </strong>

                <small>Currently marked available</small>

            </div>

        </article>

    </section>

    <!-- STORES LIST -->

    <section class="stores-list-section">

        <div class="stores-heading">

            <div>

                <span class="stores-section-kicker">
                    EXPLORE MARKETPLACE
                </span>

                <h2>Our Stores</h2>

                <p>
                    Find a store and explore its available offers.
                </p>

            </div>

            <div class="stores-result-count">

                <strong>
                    <?= number_format($totalStores) ?>
                </strong>

                <span>stores found</span>

            </div>

        </div>

        <?php if (empty($stores)): ?>

            <div class="stores-empty">

                <div class="stores-empty-icon">⌂</div>

                <h3>No stores available yet</h3>

                <p>
                    Stores will appear here once they have been added to NEXCART.
                </p>

            </div>

        <?php else: ?>

            <div class="stores-grid">

                <?php foreach ($stores as $store): ?>

                    <?php

                    $hasOffers = (int) $store['available_offers'] > 0;

                    $storeName = trim((string) $store['name']);

                    if ($storeName === '') {
                        $storeName = 'NEXCART Store';
                    }

                    $initial = nexcartStoreInitial($storeName);

                    $websiteAvailable = nexcartStoreSafeWebsite(
                        $store['website']
                    );

                    ?>

                    <article class="store-card">

                        <!-- CARD HEADER -->

                        <div class="store-card-header">

                            <div class="store-identity">

                                <div class="store-icon">
                                    <?= nexcartStoreEscape($initial) ?>
                                </div>

                                <div class="store-identity-text">

                                    <h3>
                                        <?= nexcartStoreEscape($storeName) ?>
                                    </h3>

                                    <span>
                                        Registered NEXCART partner
                                    </span>

                                </div>

                            </div>

                            <span class="store-status <?= $hasOffers ? 'is-active' : 'is-inactive' ?>">

                                <span class="status-dot"></span>

                                <?= $hasOffers ? 'Active' : 'Inactive' ?>

                            </span>

                        </div>

                        <div class="store-card-divider"></div>

                        <!-- CARD BODY -->

                        <div class="store-card-body">

                            <div class="store-card-body-heading">

                                <span>STORE OVERVIEW</span>

                                <span class="store-overview-mark">
                                    N° <?= (int) $store['id'] ?>
                                </span>

                            </div>

                            <div class="store-details">

                                <div class="store-detail">

                                    <span>Products listed</span>

                                    <strong>
                                        <?= number_format((int) $store['product_count']) ?>
                                    </strong>

                                </div>

                                <div class="store-detail">

                                    <span>Available offers</span>

                                    <strong>
                                        <?= number_format((int) $store['available_offers']) ?>
                                    </strong>

                                </div>

                                <div class="store-detail">

                                    <span>Total offers</span>

                                    <strong>
                                        <?= number_format((int) $store['total_offers']) ?>
                                    </strong>

                                </div>

                                <div class="store-detail store-detail-price">

                                    <span>Lowest available price</span>

                                    <strong>

                                        <?php if ($store['lowest_price'] !== null): ?>

                                            $<?= number_format(
                                                (float) $store['lowest_price'],
                                                2
                                            ) ?>

                                        <?php else: ?>

                                            —

                                        <?php endif; ?>

                                    </strong>

                                </div>

                            </div>

                        </div>

                        <!-- CARD FOOTER -->

                        <div class="store-card-footer">

                            <span class="store-footer-label">

                                <span class="store-footer-dot"></span>

                                NEXCART Store

                            </span>

                            <?php if ($websiteAvailable): ?>

                                <a
                                    href="<?= nexcartStoreEscape($store['website']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="website-link"
                                >

                                    Visit Website

                                    <span>↗</span>

                                </a>

                            <?php else: ?>

                                <span class="website-link is-disabled">
                                    Website unavailable
                                </span>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
