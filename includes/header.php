
<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="description" content="NEXCART - Smart Shopping. Better Choices.">
    <meta name="theme-color" content="#ffffff">

    <title>
        <?= isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') : 'NEXCART | Smart Shopping' ?>
    </title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link rel="stylesheet" href="/nexcart/assests/css/style.css">
</head>

<body>

<header class="site-header">
    <div class="container header-container">

        <a href="/nexcart/index.php" class="site-logo">
            NEX<span>CART</span>
        </a>

        <nav class="main-navigation">
            <a href="/nexcart/index.php"
               class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                Home
            </a>

            <a href="/nexcart/pages/products.php"
               class="<?= in_array($currentPage, ['products.php', 'product.php'], true) ? 'active' : '' ?>">
                Products
            </a>

            <a href="/nexcart/pages/stores.php"
               class="<?= $currentPage === 'stores.php' ? 'active' : '' ?>">
                Stores
            </a>
        </nav>

        <div class="header-actions">
            <a href="/nexcart/pages/products.php" class="header-cta">
                Explore Products <span>↗</span>
            </a>
        </div>

    </div>
</header>

<main>
