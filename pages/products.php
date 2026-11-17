
<?php

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'NEXCART | Products';

/* =========================================
   01. SEARCH & FILTER
========================================= */

$search = trim($_GET['q'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$sort = trim($_GET['sort'] ?? 'featured');

$allowedSorts = [
    'featured',
    'newest',
    'price_low',
    'price_high',
    'name'
];

if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'featured';
}

/* =========================================
   02. FETCH CATEGORIES
========================================= */

$categoryStmt = $pdo->query("
    SELECT id, name, slug, icon
    FROM categories
    ORDER BY name ASC
");

$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   03. SORTING
========================================= */

$orderBy = match ($sort) {
    'newest' => 'p.created_at DESC',
    'price_low' => 'display_price ASC, p.created_at DESC',
    'price_high' => 'display_price DESC, p.created_at DESC',
    'name' => 'p.name ASC',
    default => 'p.is_featured DESC, p.created_at DESC'
};

/* =========================================
   04. FETCH PRODUCTS
========================================= */

$sql = "
    SELECT
        p.id,
        p.name,
        p.slug,
        p.description,
        p.price,
        p.image,
        p.is_featured,
        p.created_at,

        c.name AS category_name,
        c.slug AS category_slug,

        MIN(po.price) AS best_price,

        COALESCE(MIN(po.price), p.price) AS display_price

    FROM products p

    LEFT JOIN categories c
        ON c.id = p.category_id

    LEFT JOIN product_offers po
        ON po.product_id = p.id
        AND po.availability = 'available'

    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            p.name LIKE :search_name
            OR p.description LIKE :search_description
            OR c.name LIKE :search_category
        )
    ";

    $searchTerm = '%' . $search . '%';

    $params[':search_name'] = $searchTerm;
    $params[':search_description'] = $searchTerm;
    $params[':search_category'] = $searchTerm;
}

if ($categorySlug !== '') {
    $sql .= "
        AND c.slug = :category_slug
    ";

    $params[':category_slug'] = $categorySlug;
}

$sql .= "
    GROUP BY
        p.id,
        p.name,
        p.slug,
        p.description,
        p.price,
        p.image,
        p.is_featured,
        p.created_at,
        c.name,
        c.slug

    ORDER BY {$orderBy}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   05. PRODUCT IMAGE SYSTEM
========================================= */

function getProductImage($product)
{
    $image = trim($product['image'] ?? '');

    // External image URL
    if ($image !== '' && filter_var($image, FILTER_VALIDATE_URL)) {
        $scheme = strtolower(
            parse_url($image, PHP_URL_SCHEME) ?? ''
        );

        if (in_array($scheme, ['http', 'https'], true)) {
            return $image;
        }
    }

    // Local image
    if ($image !== '') {
        $image = str_replace('\\', '/', $image);
        $localImage = ltrim($image, '/');

        if (strpos($localImage, 'nexcart/') === 0) {
            $localImage = substr($localImage, 8);
        }

        if (
            strpos($localImage, '../') === false &&
            strpos($localImage, '..\\') === false
        ) {
            $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR .
                str_replace('/', DIRECTORY_SEPARATOR, $localImage);

            if (is_file($fullPath)) {
                return '../' . $localImage;
            }
        }
    }

    // Fallback images
    $name = strtolower($product['name'] ?? '');
    $category = strtolower($product['category_name'] ?? '');
    $searchText = $name . ' ' . $category;

    if (
        strpos($searchText, 'smartphone') !== false ||
        strpos($searchText, 'phone') !== false ||
        strpos($searchText, 'mobile') !== false
    ) {
        return 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=1000&auto=format&fit=crop&q=90';
    }

    if (
        strpos($searchText, 'laptop') !== false ||
        strpos($searchText, 'computer') !== false ||
        strpos($searchText, 'notebook') !== false
    ) {
        return 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=1000&auto=format&fit=crop&q=90';
    }

    if (
        strpos($searchText, 'headphone') !== false ||
        strpos($searchText, 'headset') !== false ||
        strpos($searchText, 'audio') !== false
    ) {
        return 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=1000&auto=format&fit=crop&q=90';
    }

    if (
        strpos($searchText, 'watch') !== false ||
        strpos($searchText, 'wearable') !== false
    ) {
        return 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=1000&auto=format&fit=crop&q=90';
    }

    return 'https://placehold.co/900x700/f2f5fa/64748b?text=NEXCART';
}

/* =========================================
   06. PAGE HEADER
========================================= */

require_once __DIR__ . '/../includes/header.php';

?>

<!-- PRODUCTS HERO -->

<section class="products-page-hero">

    <div class="container">

        <span class="section-label">
            <span></span>
            EXPLORE OUR COLLECTION
        </span>

        <h1>
            Find the right product.
            <span>At the right price.</span>
        </h1>

        <p>
            Explore products, compare store offers,
            and discover smarter ways to shop.
        </p>

    </div>

</section>


<!-- PRODUCTS SECTION -->

<section class="products-list-section">

    <div class="container">

        <!-- SEARCH & FILTER -->

        <form
            method="GET"
            action="products.php"
            class="products-filter"
        >

            <div class="search-field">

                <span class="search-icon" aria-hidden="true">⌕</span>

                <input
                    type="search"
                    name="q"
                    placeholder="Search products..."
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                    aria-label="Search products"
                >

            </div>


            <div class="category-filter">

                <select
                    name="category"
                    aria-label="Select product category"
                >

                    <option value="">All Categories</option>

                    <?php foreach ($categories as $category): ?>

                        <option
                            value="<?= htmlspecialchars($category['slug'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= $categorySlug === $category['slug'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="category-filter">

                <select
                    name="sort"
                    aria-label="Sort products"
                >

                    <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>
                        Featured
                    </option>

                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>
                        Newest
                    </option>

                    <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>
                        Price: Low to High
                    </option>

                    <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>
                        Price: High to Low
                    </option>

                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>
                        Name: A to Z
                    </option>

                </select>

            </div>


            <button type="submit" class="filter-button">
                Search
                <span>↗</span>
            </button>

        </form>


        <!-- EDITORIAL COLLECTION -->

        <div class="editorial-collection">

            <div class="editorial-topline">

                <span class="editorial-label">
                    <span class="editorial-label-dot"></span>
                    NEXCART DISCOVERY
                </span>

                <span>SMARTER SHOPPING EXPERIENCE</span>

            </div>


            <div class="editorial-main">

                <div class="editorial-copy">

                    <h2>

                        <?php if ($search !== ''): ?>

                            Search results

                        <?php elseif ($categorySlug !== ''): ?>

                            Explore this category

                        <?php else: ?>

                            Discover your next favorite.

                        <?php endif; ?>

                    </h2>

                    <p>
                        Compare products and find the offer
                        that makes the most sense for you.
                    </p>


                    <div class="editorial-meta">

                        <span>
                            <?= count($products) ?>
                            <?= count($products) === 1 ? 'product' : 'products' ?>
                            found
                        </span>

                        <?php if ($search !== '' || $categorySlug !== '' || $sort !== 'featured'): ?>

                            <a href="products.php">
                                Clear filters
                                <span>↗</span>
                            </a>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="editorial-visual">

                    <div class="editorial-orbit editorial-orbit-one"></div>

                    <div class="editorial-orbit editorial-orbit-two"></div>

                    <div class="editorial-center">
                        N
                    </div>

                </div>

            </div>


            <div class="editorial-bottom">

                <span>DISCOVER MORE. COMPARE BETTER.</span>

                <span>NEXCART ®</span>

            </div>

        </div>


        <!-- PRODUCT GRID -->

        <div class="products-grid">

            <?php if (!empty($products)): ?>

                <?php foreach ($products as $product): ?>

                    <?php

                    $productImage = getProductImage($product);

                    $displayPrice = (float) $product['display_price'];

                    $description = trim($product['description'] ?? '');

                    if (function_exists('mb_strimwidth')) {

                        $description = mb_strimwidth(
                            $description,
                            0,
                            105,
                            '...',
                            'UTF-8'
                        );

                    } else {

                        $description = substr($description, 0, 105);

                    }

                    ?>

                    <article class="product-card">

                        <div class="product-image">

                            <img
                                src="<?= htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                                onerror="this.onerror=null;this.src='https://placehold.co/900x700/f2f5fa/64748b?text=NEXCART';"
                            >

                            <?php if (!empty($product['is_featured'])): ?>

                                <span class="product-badge">
                                    FEATURED
                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="product-info">

                            <span class="product-category">
                                <?= htmlspecialchars(
                                    $product['category_name'] ?? 'General',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>


                            <h3>
                                <?= htmlspecialchars(
                                    $product['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h3>


                            <p>
                                <?= htmlspecialchars(
                                    $description,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </p>


                            <div class="product-price">

                                <span class="price-label">

                                    <?= $product['best_price'] !== null
                                        ? 'Best available price'
                                        : 'Listed price' ?>

                                </span>

                                <strong>
                                    $<?= number_format($displayPrice, 2) ?>
                                </strong>

                            </div>


                            <a
                                href="product.php?id=<?= (int) $product['id'] ?>"
                                class="compare-btn"
                            >
                                Compare Offers
                                <span>↗</span>
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="products-empty-state">

                    <div class="empty-icon">⌕</div>

                    <h3>No products found</h3>

                    <p>
                        We couldn't find any products matching your search.
                        Try another keyword or explore all products.
                    </p>

                    <a href="products.php" class="compare-btn">
                        View All Products
                        <span>↗</span>
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>

</section>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
