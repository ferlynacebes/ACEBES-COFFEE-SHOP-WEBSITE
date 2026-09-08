<?php
session_start();

require_once "../config/db.php";

/* =========================================================
   ADMIN ACCESS
========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["user_role"]) ||
    $_SESSION["user_role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$adminName = $_SESSION["user_name"] ?? "Administrator";


/* =========================================================
   DASHBOARD STATISTICS
========================================================= */

$totalCustomers = 0;
$totalProducts = 0;
$totalOrders = 0;
$completedSales = 0;
$pendingOrders = 0;


/* TOTAL CUSTOMERS */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'customer'
");

if ($result) {
    $row = $result->fetch_assoc();
    $totalCustomers = (int) ($row["total"] ?? 0);
}


/* TOTAL PRODUCTS */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM products
");

if ($result) {
    $row = $result->fetch_assoc();
    $totalProducts = (int) ($row["total"] ?? 0);
}


/* TOTAL ORDERS */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM orders
");

if ($result) {
    $row = $result->fetch_assoc();
    $totalOrders = (int) ($row["total"] ?? 0);
}


/* COMPLETED SALES */

$result = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total
    FROM orders
    WHERE status = 'Completed'
");

if ($result) {
    $row = $result->fetch_assoc();
    $completedSales = (float) ($row["total"] ?? 0);
}


/* PENDING ORDERS */

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE status = 'Pending'
");

if ($result) {
    $row = $result->fetch_assoc();
    $pendingOrders = (int) ($row["total"] ?? 0);
}


/* =========================================================
   RECENT ORDERS
========================================================= */

$recentOrders = $conn->query("
    SELECT
        orders.id,
        orders.customer_name,
        orders.total_amount,
        orders.status,
        orders.order_date,
        users.name AS user_name
    FROM orders
    LEFT JOIN users
        ON orders.user_id = users.id
    ORDER BY orders.order_date DESC
    LIMIT 6
");


/* =========================================================
   LOW STOCK PRODUCTS
========================================================= */

$lowStockProducts = $conn->query("
    SELECT
        id,
        name,
        stock,
        status
    FROM products
    WHERE stock <= 5
    ORDER BY stock ASC, name ASC
    LIMIT 6
");

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard | Acebes Coffee Admin</title>

    <!-- Montserrat -->
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        /* =====================================================
           RESET
        ====================================================== */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        html {
            scroll-behavior: smooth;
        }


        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: #f6f1eb;
            color: #2a1a13;
        }


        a {
            text-decoration: none;
            color: inherit;
        }


        button,
        input,
        select,
        textarea {
            font-family: inherit;
        }


        /* =====================================================
           MAIN LAYOUT
        ====================================================== */

        .admin-layout {
            min-height: 100vh;
            display: flex;
        }


        .main {
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
            padding: 35px;
        }

        .brand-logo {
            width: 58px;
            height: 58px;
            flex-shrink: 0;
            object-fit: cover;
            border-radius: 14px;
            display: block;
            background: #4b2e20;
        }


        /* =====================================================
           PAGE HEADER
        ====================================================== */

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }


        .page-header h2 {
            font-family: 'Montserrat', Arial, sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: #2a1a13;
        }


        .page-header p {
            color: #806f64;
            margin-top: 6px;
            font-size: 14px;
            line-height: 1.6;
        }


        .welcome-badge {
            background: #ffffff;
            border: 1px solid #e8ddd4;
            border-radius: 12px;
            padding: 12px 16px;
            color: #806f64;
            font-size: 12px;
            font-weight: 600;
        }


        .welcome-badge strong {
            color: #4b2e20;
        }


        /* =====================================================
           STATISTICS
        ====================================================== */

        .stats {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }


        .stat-card {
            background: #ffffff;
            border: 1px solid #e9dfd7;
            border-radius: 15px;
            padding: 22px;
            box-shadow:
                0 5px 18px rgba(42, 26, 19, .04);
            transition:
                transform .2s ease,
                box-shadow .2s ease;
        }


        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow:
                0 9px 25px rgba(42, 26, 19, .07);
        }


        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 15px;
        }


        .stat-card span {
            color: #806f64;
            font-size: 12px;
            font-weight: 600;
        }


        .stat-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1e5d9;
            font-size: 17px;
        }


        .stat-card strong {
            display: block;
            color: #4b2e20;
            font-family: 'Montserrat', Arial, sans-serif;
            font-size: 27px;
            font-weight: 800;
            line-height: 1.2;
        }


        .stat-card small {
            display: block;
            color: #a18e82;
            font-size: 10px;
            margin-top: 7px;
        }


        /* =====================================================
           CONTENT GRID
        ====================================================== */

        .dashboard-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 1.7fr)
                minmax(300px, 1fr);
            gap: 24px;
        }


        .dashboard-card {
            background: #ffffff;
            border: 1px solid #e9dfd7;
            border-radius: 15px;
            overflow: hidden;
            box-shadow:
                0 5px 18px rgba(42, 26, 19, .04);
        }


        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 20px 22px;
            border-bottom: 1px solid #eee5de;
        }


        .card-header h3 {
            color: #4b2e20;
            font-family: 'Montserrat', Arial, sans-serif;
            font-size: 16px;
            font-weight: 700;
        }


        .card-header p {
            color: #9a897e;
            font-size: 11px;
            margin-top: 4px;
        }


        .view-link {
            color: #8a5a3b;
            font-size: 11px;
            font-weight: 700;
            transition: color .2s ease;
        }


        .view-link:hover {
            color: #4b2e20;
        }


        /* =====================================================
           ORDERS TABLE
        ====================================================== */

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }


        .orders-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }


        .orders-table th {
            text-align: left;
            color: #9a897e;
            background: #faf7f4;
            padding: 12px 18px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
        }


        .orders-table td {
            padding: 15px 18px;
            border-top: 1px solid #f0e8e2;
            color: #5e4a3f;
            font-size: 12px;
            vertical-align: middle;
        }


        .orders-table tbody tr {
            transition: background .2s ease;
        }


        .orders-table tbody tr:hover {
            background: #fcfaf8;
        }


        .order-id {
            color: #4b2e20;
            font-weight: 700;
        }


        .customer-name {
            color: #4b2e20;
            font-weight: 600;
        }


        .order-date {
            color: #97877c;
            font-size: 11px;
        }


        .order-total {
            color: #4b2e20;
            font-weight: 700;
        }


        /* =====================================================
           STATUS
        ====================================================== */

        .status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }


        .status.pending {
            background: #fff2d8;
            color: #9a6a16;
        }


        .status.processing {
            background: #e6eff8;
            color: #37668c;
        }


        .status.completed {
            background: #e4f3e7;
            color: #397348;
        }


        .status.cancelled {
            background: #f8e5e3;
            color: #a13f35;
        }


        /* =====================================================
           LOW STOCK
        ====================================================== */

        .stock-list {
            padding: 8px 22px 16px;
        }


        .stock-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0e8e2;
        }


        .stock-item:last-child {
            border-bottom: 0;
        }


        .stock-name {
            color: #4b2e20;
            font-size: 12px;
            font-weight: 600;
        }


        .stock-id {
            color: #a18e82;
            font-size: 10px;
            margin-top: 4px;
        }


        .stock-number {
            min-width: 55px;
            text-align: center;
            padding: 7px 8px;
            border-radius: 8px;
            background: #f8e5e3;
            color: #a13f35;
            font-size: 10px;
            font-weight: 800;
        }


        .stock-number.ok {
            background: #fff2d8;
            color: #9a6a16;
        }


        .empty-state {
            padding: 35px 20px;
            text-align: center;
            color: #9a897e;
            font-size: 12px;
        }


        .empty-state strong {
            display: block;
            color: #4b2e20;
            margin-bottom: 5px;
            font-size: 13px;
        }


        /* =====================================================
           QUICK ACTIONS
        ====================================================== */

        .quick-actions {
            margin-top: 24px;
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
            gap: 16px;
        }


        .quick-action {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #ffffff;
            border: 1px solid #e9dfd7;
            border-radius: 13px;
            padding: 17px;
            transition:
                transform .2s ease,
                border-color .2s ease,
                box-shadow .2s ease;
        }


        .quick-action:hover {
            transform: translateY(-2px);
            border-color: #d8c5b6;
            box-shadow:
                0 7px 20px rgba(42, 26, 19, .05);
        }


        .quick-icon {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            border-radius: 10px;
            background: #f1e5d9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
        }


        .quick-action strong {
            display: block;
            color: #4b2e20;
            font-size: 12px;
            font-weight: 700;
        }


        .quick-action span {
            display: block;
            color: #9a897e;
            font-size: 10px;
            margin-top: 4px;
        }


        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 1100px) {

            .stats {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 950px) {

            .main {
                margin-left: 210px;
                width: calc(100% - 210px);
                padding: 25px;
            }

            .stats {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

        }


        @media (max-width: 700px) {

            .admin-layout {
                display: block;
            }

            .main {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .welcome-badge {
                width: 100%;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>


<div class="admin-layout">

    <!-- =====================================================
         SHARED ADMIN SIDEBAR
    ====================================================== -->

    <?php include __DIR__ . "/sidebar.php"; ?>


    <!-- =====================================================
         MAIN DASHBOARD
    ====================================================== -->

    <main class="main">


        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <h2>
                    Dashboard
                </h2>

                <p>
                    Welcome back! Here's what's happening
                    with your coffee shop today.
                </p>

            </div>


            <div class="welcome-badge">

                Welcome,
                <strong>
                    <?= htmlspecialchars($adminName, ENT_QUOTES, "UTF-8") ?>
                </strong>

            </div>

        </div>


        <!-- =================================================
             STAT CARDS
        ================================================== -->

        <section class="stats">


            <!-- CUSTOMERS -->

            <div class="stat-card">

                <div class="stat-top">

                    <span>
                        Total Customers
                    </span>

                    <div class="stat-icon">
                        👥
                    </div>

                </div>

                <strong>
                    <?= number_format($totalCustomers) ?>
                </strong>

                <small>
                    Registered customer accounts
                </small>

            </div>


            <!-- PRODUCTS -->

            <div class="stat-card">

                <div class="stat-top">

                    <span>
                        Total Products
                    </span>

                    <div class="stat-icon">
                        ☕
                    </div>

                </div>

                <strong>
                    <?= number_format($totalProducts) ?>
                </strong>

                <small>
                    Products in inventory
                </small>

            </div>


            <!-- ORDERS -->

            <div class="stat-card">

                <div class="stat-top">

                    <span>
                        Total Orders
                    </span>

                    <div class="stat-icon">
                        🛒
                    </div>

                </div>

                <strong>
                    <?= number_format($totalOrders) ?>
                </strong>

                <small>
                    All customer orders
                </small>

            </div>


            <!-- SALES -->

            <div class="stat-card">

                <div class="stat-top">

                    <span>
                        Completed Sales
                    </span>

                    <div class="stat-icon">
                        ₱
                    </div>

                </div>

                <strong>
                    ₱<?= number_format($completedSales, 2) ?>
                </strong>

                <small>
                    From completed orders
                </small>

            </div>


        </section>


        <!-- =================================================
             SECONDARY SUMMARY
        ================================================== -->

        <section class="stats"
            style="
                grid-template-columns:
                    minmax(0, 1fr);
                margin-top: -6px;
            "
        >

            <div
                class="stat-card"
                style="
                    padding: 17px 22px;
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:20px;
                "
            >

                <div>

                    <span>
                        Pending Orders
                    </span>

                    <strong
                        style="
                            font-size:22px;
                            margin-top:6px;
                        "
                    >
                        <?= number_format($pendingOrders) ?>
                    </strong>

                </div>

                <a
                    href="orders.php"
                    class="view-link"
                >
                    Review Orders →
                </a>

            </div>

        </section>


        <!-- =================================================
             DASHBOARD CONTENT
        ================================================== -->

        <div class="dashboard-grid">


            <!-- =================================================
                 RECENT ORDERS
            ================================================== -->

            <section class="dashboard-card">

                <div class="card-header">

                    <div>

                        <h3>
                            Recent Orders
                        </h3>

                        <p>
                            Latest customer orders
                        </p>

                    </div>

                    <a
                        href="orders.php"
                        class="view-link"
                    >
                        View All →
                    </a>

                </div>


                <?php if ($recentOrders && $recentOrders->num_rows > 0): ?>

                    <div class="table-wrap">

                        <table class="orders-table">

                            <thead>

                                <tr>

                                    <th>
                                        Order
                                    </th>

                                    <th>
                                        Customer
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Total
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php while ($order = $recentOrders->fetch_assoc()): ?>

                                    <?php
                                    $statusClass = strtolower(
                                        preg_replace(
                                            "/[^A-Za-z0-9]+/",
                                            "",
                                            $order["status"] ?? ""
                                        )
                                    );

                                    $customerName =
                                        $order["customer_name"]
                                        ?: ($order["user_name"] ?? "Guest");

                                    $orderDate = !empty($order["order_date"])
                                        ? date(
                                            "M d, Y h:i A",
                                            strtotime($order["order_date"])
                                        )
                                        : "—";
                                    ?>

                                    <tr>

                                        <td>
                                            <span class="order-id">
                                                #<?= (int) $order["id"] ?>
                                            </span>
                                        </td>


                                        <td>

                                            <div class="customer-name">
                                                <?= htmlspecialchars(
                                                    $customerName,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>
                                            </div>

                                        </td>


                                        <td>

                                            <span class="order-date">
                                                <?= htmlspecialchars(
                                                    $orderDate,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>
                                            </span>

                                        </td>


                                        <td>

                                            <span class="order-total">
                                                ₱<?= number_format(
                                                    (float) $order["total_amount"],
                                                    2
                                                ) ?>
                                            </span>

                                        </td>


                                        <td>

                                            <span
                                                class="status <?= htmlspecialchars(
                                                    $statusClass,
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>"
                                            >
                                                <?= htmlspecialchars(
                                                    $order["status"] ?? "Unknown",
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ) ?>
                                            </span>

                                        </td>

                                    </tr>

                                <?php endwhile; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        <strong>
                            No orders yet
                        </strong>

                        Recent customer orders will appear here.

                    </div>

                <?php endif; ?>

            </section>


            <!-- =================================================
                 LOW STOCK PRODUCTS
            ================================================== -->

            <section class="dashboard-card">

                <div class="card-header">

                    <div>

                        <h3>
                            Low Stock
                        </h3>

                        <p>
                            Products that need attention
                        </p>

                    </div>

                    <a
                        href="products.php"
                        class="view-link"
                    >
                        Manage →
                    </a>

                </div>


                <?php if ($lowStockProducts && $lowStockProducts->num_rows > 0): ?>

                    <div class="stock-list">

                        <?php while ($product = $lowStockProducts->fetch_assoc()): ?>

                            <?php
                            $stock = (int) $product["stock"];
                            $stockClass = $stock > 0 ? "ok" : "";
                            ?>

                            <div class="stock-item">

                                <div>

                                    <div class="stock-name">

                                        <?= htmlspecialchars(
                                            $product["name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>

                                    </div>

                                    <div class="stock-id">
                                        Product #<?= (int) $product["id"] ?>
                                    </div>

                                </div>


                                <div
                                    class="stock-number <?= $stockClass ?>"
                                >

                                    <?= $stock ?>

                                    <?= $stock === 1
                                        ? "left"
                                        : "left" ?>

                                </div>

                            </div>

                        <?php endwhile; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        <strong>
                            Stock looks good
                        </strong>

                        No products are currently at or below 5 units.

                    </div>

                <?php endif; ?>

            </section>


        </div>


        <!-- =================================================
             QUICK ACTIONS
        ================================================== -->

        <section class="quick-actions">


            <a
                href="products.php"
                class="quick-action"
            >

                <div class="quick-icon">
                    ☕
                </div>

                <div>

                    <strong>
                        Manage Products
                    </strong>

                    <span>
                        Add or update coffee products
                    </span>

                </div>

            </a>


            <a
                href="orders.php"
                class="quick-action"
            >

                <div class="quick-icon">
                    🛒
                </div>

                <div>

                    <strong>
                        Manage Orders
                    </strong>

                    <span>
                        Review and update order status
                    </span>

                </div>

            </a>


            <a
                href="customers.php"
                class="quick-action"
            >

                <div class="quick-icon">
                    👥
                </div>

                <div>

                    <strong>
                        View Customers
                    </strong>

                    <span>
                        Manage registered customers
                    </span>

                </div>

            </a>


        </section>


    </main>

</div>


</body>

</html>
