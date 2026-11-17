
<?php

require_once __DIR__ . '/config/database.php';

$pageTitle = 'NEXCART | Smart Shopping';

/* Featured products */
$productQuery = $pdo->query("
    SELECT
        p.id,
        p.name,
        p.slug,
        p.description,
        p.price,
        p.image,
        c.name AS category_name,
        c.icon AS category_icon,
        MIN(po.price) AS best_price
    FROM products p
    LEFT JOIN categories c
        ON p.category_id = c.id
    LEFT JOIN product_offers po
        ON p.id = po.product_id
        AND po.availability = 'available'
    WHERE p.is_featured = 1
    GROUP BY
        p.id,
        p.name,
        p.slug,
        p.description,
        p.price,
        p.image,
        c.name,
        c.icon
    ORDER BY p.created_at DESC
");

$products = $productQuery->fetchAll(PDO::FETCH_ASSOC);

/* Categories */
$categoryQuery = $pdo->query("
    SELECT id, name, slug, icon
    FROM categories
    ORDER BY name ASC
");

$categories = $categoryQuery->fetchAll(PDO::FETCH_ASSOC);

/* Statistics */
$totalProducts = (int) $pdo
    ->query("SELECT COUNT(*) FROM products")
    ->fetchColumn();

$totalStores = (int) $pdo
    ->query("SELECT COUNT(*) FROM stores")
    ->fetchColumn();

$totalCategories = count($categories);

?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<script src="/nexcart/assests/js/app.js" defer></script>

<!-- =========================
     HERO SECTION
========================= -->

<section class="hero">
    <div class="container hero-layout">

        <div class="hero-text">

            <span class="hero-label">
                <span class="hero-label-dot"></span>
                THE SMARTER WAY TO SHOP
            </span>

            <h1>
                Smart Shopping.
                <span>Better Choices.</span>
            </h1>

            <p>
                Discover products, compare prices across stores,
                and make smarter shopping decisions with NEXCART.
            </p>

            <div class="hero-actions">

                <a href="/nexcart/pages/products.php" class="primary-btn">
                    Explore Products
                    <span aria-hidden="true">↗</span>
                </a>

                <a href="#categories" class="secondary-btn">
                    Browse Categories
                </a>

            </div>

            <div class="hero-trust">
                <span class="hero-trust-icon">✓</span>
                Smarter comparisons. Better shopping decisions.
            </div>

        </div>

        <div class="hero-visual">

            <div class="hero-card">

                <div class="hero-card-top">

                    <div>
                        <span class="hero-card-eyebrow">NEXCART</span>
                        <span class="hero-card-label">COMPARISON PLATFORM</span>
                    </div>

                    <span class="hero-card-status">
                        <span></span>
                        SMART CHOICE
                    </span>

                </div>

                <div class="hero-product-icon">
                    <span>🛍️</span>
                </div>

                <div class="hero-card-content">
                    <span class="hero-card-kicker">SHOP SMARTER</span>
                    <h2>Smarter Shopping</h2>
                    <p>Compare. Discover. Choose.</p>
                </div>

                <div class="hero-card-divider"></div>

                <div class="hero-card-footer">

                    <div>
                        <span class="hero-footer-icon">↘</span>
                        <span>Better prices</span>
                    </div>

                    <div>
                        <span class="hero-footer-icon">✓</span>
                        <span>Better decisions</span>
                    </div>

                </div>

                <div class="hero-card-decoration"></div>

            </div>

            <div class="hero-floating-badge">
                <span class="floating-badge-icon">✦</span>
                <div>
                    <strong>Compare with confidence</strong>
                    <small>Find the right choice</small>
                </div>
            </div>

        </div>

    </div>
</section>


<!-- =========================
     STATISTICS
========================= -->

<section class="stats-section">
    <div class="container stats-grid">

        <div class="stat-item">
            <strong><?= number_format($totalProducts) ?></strong>
            <span>Products</span>
        </div>

        <div class="stat-item">
            <strong><?= number_format($totalCategories) ?></strong>
            <span>Categories</span>
        </div>

        <div class="stat-item">
            <strong><?= number_format($totalStores) ?></strong>
            <span>Stores</span>
        </div>

    </div>
</section>


<!-- =========================
     CATEGORIES
========================= -->

<section class="categories-section" id="categories">

    <div class="container">

        <div class="section-heading categories-heading">

            <div>
                <span class="section-label">
                    <span></span>
                    EXPLORE
                </span>

                <h2>Shop by Category</h2>

                <p>
                    Discover products from categories that match your needs.
                </p>
            </div>

            <a href="/nexcart/pages/products.php" class="text-link">
                View All Products <span>→</span>
            </a>

        </div>

        <div class="categories-grid">

            <?php if (!empty($categories)): ?>

                <?php foreach ($categories as $category): ?>

                    <a
                        href="/nexcart/pages/products.php?category=<?= urlencode($category['slug']) ?>"
                        class="category-card"
                    >

                        <span class="category-icon">
                            <?= htmlspecialchars($category['icon'] ?? '📦') ?>
                        </span>

                        <div class="category-info">
                            <h3>
                                <?= htmlspecialchars($category['name']) ?>
                            </h3>
                            <span>Explore products</span>
                        </div>

                        <span class="category-arrow">↗</span>

                    </a>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="products-empty-state">
                    <div class="empty-icon">▦</div>
                    <h3>No categories yet</h3>
                    <p>Categories will appear here when they are added.</p>
                </div>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- =========================
     FEATURED PRODUCTS
========================= -->

<section class="products-section" id="products">

    <div class="container">

        <div class="section-heading">

            <div>
                <span class="section-label">
                    <span></span>
                    OUR SELECTION
                </span>

                <h2>Featured Products</h2>

                <p>
                    Explore selected products and compare their available prices.
                </p>
            </div>

            <a href="/nexcart/pages/products.php" class="text-link">
                View All Products <span>→</span>
            </a>

        </div>

        <div class="products-grid">

            <?php if (!empty($products)): ?>

                <?php foreach ($products as $product): ?>

                    <article class="product-card">

                        <div class="product-image">

                            <span class="product-badge">
                                FEATURED
                            </span>

                            <?php if (!empty($product['image'])): ?>

                                <img
                                    src="/nexcart/assests/images/<?= htmlspecialchars(basename($product['image'])) ?>"
                                    alt="<?= htmlspecialchars($product['name']) ?>"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <div class="product-placeholder">
                                    <?= htmlspecialchars($product['category_icon'] ?? '📦') ?>
                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="product-info">

                            <span class="product-category">
                                <?= htmlspecialchars($product['category_name'] ?? 'GENERAL') ?>
                            </span>

                            <h3>
                                <?= htmlspecialchars($product['name']) ?>
                            </h3>

                            <p class="product-description">
                                <?= htmlspecialchars($product['description'] ?? '') ?>
                            </p>

                            <div class="product-price">

                                <?php if ($product['best_price'] !== null): ?>

                                    <span class="price-label">
                                        Best available price
                                    </span>

                                    <strong>
                                        $<?= number_format((float) $product['best_price'], 2) ?>
                                    </strong>

                                <?php else: ?>

                                    <span class="price-label">
                                        Listed price
                                    </span>

                                    <strong>
                                        $<?= number_format((float) $product['price'], 2) ?>
                                    </strong>

                                <?php endif; ?>

                            </div>

                            <a
                                href="/nexcart/pages/product.php?id=<?= (int) $product['id'] ?>"
                                class="compare-btn"
                            >
                                Compare Prices
                                <span>↗</span>
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="products-empty-state">
                    <div class="empty-icon">▦</div>
                    <h3>No featured products yet</h3>
                    <p>
                        Featured products will appear here once they are added.
                    </p>
                    <a href="/nexcart/pages/products.php" class="primary-btn">
                        Explore Products
                    </a>
                </div>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- =========================
     ABOUT NEXCART
========================= -->

<section class="about-section" id="about">

    <div class="container about-layout">

        <div class="about-copy">

            <span class="section-label">
                <span></span>
                ABOUT NEXCART
            </span>

            <h2>
                Shopping should be
                <span>simple and smart.</span>
            </h2>

            <p>
                NEXCART brings product discovery and price comparison
                together in one place. Our goal is to make online
                shopping more transparent, convenient, and efficient.
            </p>

            <a href="/nexcart/pages/products.php" class="primary-btn">
                Start Exploring
                <span>↗</span>
            </a>

        </div>

        <div class="about-visual">

            <div class="about-visual-orbit orbit-one"></div>
            <div class="about-visual-orbit orbit-two"></div>

            <div class="about-visual-center">
                <span>NC</span>
            </div>

            <div class="about-floating about-floating-one">
                <span>✓</span>
                Smart choices
            </div>

            <div class="about-floating about-floating-two">
                <span>↘</span>
                Better prices
            </div>

        </div>

    </div>

</section>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
