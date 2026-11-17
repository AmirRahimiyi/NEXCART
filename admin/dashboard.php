
<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Dashboard statistics
$productCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM products"
)->fetchColumn();

$categoryCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM categories"
)->fetchColumn();

$storeCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM stores"
)->fetchColumn();

$offerCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM product_offers"
)->fetchColumn();

// Latest products
$latestProducts = $pdo->query(
    "SELECT id, name, price, created_at
     FROM products
     ORDER BY id DESC
     LIMIT 5"
)->fetchAll();

$adminName = htmlspecialchars(
    $_SESSION['admin_name'] ?? 'Administrator',
    ENT_QUOTES,
    'UTF-8'
);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | NEXCART Admin</title>

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

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: #111827;
            color: white;
            padding: 30px 20px;
            flex-shrink: 0;
        }

        .brand {
            font-size: 25px;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 45px;
        }

        .brand span {
            color: #3b82f6;
        }

        .sidebar-label {
            color: #8893a7;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 15px;
        }

        .sidebar a {
            display: block;
            color: #cbd5e1;
            text-decoration: none;
            padding: 13px 14px;
            border-radius: 8px;
            margin-bottom: 7px;
            font-size: 14px;
            transition: 0.2s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #26364f;
            color: white;
        }

        .sidebar a.logout {
            color: #f87171;
            margin-top: 35px;
        }

        .main-content {
            flex: 1;
            padding: 35px;
            min-width: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 35px;
        }

        .topbar h1 {
            font-size: 27px;
            margin-bottom: 7px;
        }

        .topbar p {
            color: #788397;
            font-size: 14px;
        }

        .admin-profile {
            background: white;
            padding: 12px 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 3px 15px rgba(0,0,0,0.04);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 13px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.035);
        }

        .stat-card p {
            color: #788397;
            font-size: 13px;
            margin-bottom: 17px;
        }

        .stat-card h2 {
            font-size: 30px;
            color: #172033;
        }

        .section-card {
            background: white;
            border-radius: 13px;
            padding: 25px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.035);
            margin-bottom: 25px;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .section-heading h2 {
            font-size: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 15px 10px;
            border-bottom: 1px solid #edf0f5;
            font-size: 13px;
        }

        th {
            color: #788397;
            font-weight: 600;
        }

        .product-id {
            color: #3b82f6;
            font-weight: 700;
        }

        .empty-state {
            color: #788397;
            padding: 20px 0;
            font-size: 14px;
        }

        .dashboard-footer {
            color: #9aa3b2;
            font-size: 12px;
            text-align: center;
            padding: 20px;
        }

        @media (max-width: 1000px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 650px) {
            .admin-layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .main-content {
                padding: 22px 15px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .section-card {
                overflow-x: auto;
            }

            table {
                min-width: 450px;
            }
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <aside class="sidebar">

        <div class="brand">
            NEX<span>CART</span>
        </div>

        <div class="sidebar-label">Main Menu</div>

        <a href="dashboard.php" class="active">
            Dashboard
        </a>

        <a href="manage-products.php">
            Products
        </a>

        <a href="manage-categories.php">
            Categories
        </a>

        <a href="manage-stores.php">
            Stores
        </a>

        <a href="manage-offers.php">
            Price Offers
        </a>

        <a href="../index.php">
            View Website
        </a>

        <a href="logout.php" class="logout">
            Sign Out
        </a>

    </aside>

    <main class="main-content">

        <header class="topbar">

            <div>
                <h1>Dashboard</h1>
                <p>Welcome back. Here's what's happening with NEXCART.</p>
            </div>

            <div class="admin-profile">
                <?= $adminName ?>
            </div>

        </header>

        <section class="stats-grid">

            <div class="stat-card">
                <p>Total Products</p>
                <h2><?= $productCount ?></h2>
            </div>

            <div class="stat-card">
                <p>Categories</p>
                <h2><?= $categoryCount ?></h2>
            </div>

            <div class="stat-card">
                <p>Stores</p>
                <h2><?= $storeCount ?></h2>
            </div>

            <div class="stat-card">
                <p>Price Offers</p>
                <h2><?= $offerCount ?></h2>
            </div>

        </section>

        <section class="section-card">

            <div class="section-heading">
                <h2>Recently Added Products</h2>
            </div>

            <?php if (!empty($latestProducts)): ?>

                <table>

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Base Price</th>
                            <th>Created At</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($latestProducts as $product): ?>

                            <tr>
                                <td class="product-id">
                                    #<?= (int) $product['id'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    $<?= number_format((float) $product['price'], 2) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($product['created_at'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <p class="empty-state">
                    No products have been added yet.
                </p>

            <?php endif; ?>

        </section>

        <div class="dashboard-footer">
            © <?= date('Y') ?> NEXCART. All rights reserved.
        </div>

    </main>

</div>

</body>
</html>
