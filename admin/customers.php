<?php
session_start();

require_once "../config/db.php";

/* =========================================================
   ADMIN ACCESS
========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$adminName = $_SESSION["user_name"] ?? "Administrator";

/* =========================================================
   DELETE CUSTOMER
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_customer"])) {

    $customerId = (int) ($_POST["customer_id"] ?? 0);

    if ($customerId > 0) {

        /*
         * Prevent deleting an admin account.
         */
        $stmt = $conn->prepare("
            DELETE FROM users
            WHERE id = ?
            AND role = 'customer'
        ");

        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $stmt->close();

        header("Location: customers.php?deleted=1");
        exit;
    }
}

/* =========================================================
   SEARCH
========================================================= */

$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $searchValue = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.name,
            u.email,
            u.role,
            u.created_at,

            COUNT(DISTINCT o.id) AS order_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN o.status = 'Completed'
                        THEN o.total_amount
                        ELSE 0
                    END
                ),
                0
            ) AS total_spent

        FROM users u

        LEFT JOIN orders o
            ON o.user_id = u.id

        WHERE
            u.role = 'customer'
            AND (
                u.name LIKE ?
                OR u.email LIKE ?
            )

        GROUP BY
            u.id,
            u.name,
            u.email,
            u.role,
            u.created_at

        ORDER BY u.id DESC
    ");

    $stmt->bind_param(
        "ss",
        $searchValue,
        $searchValue
    );

    $stmt->execute();

    $customers = $stmt->get_result();

} else {

    $customers = $conn->query("
        SELECT
            u.id,
            u.name,
            u.email,
            u.role,
            u.created_at,

            COUNT(DISTINCT o.id) AS order_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN o.status = 'Completed'
                        THEN o.total_amount
                        ELSE 0
                    END
                ),
                0
            ) AS total_spent

        FROM users u

        LEFT JOIN orders o
            ON o.user_id = u.id

        WHERE u.role = 'customer'

        GROUP BY
            u.id,
            u.name,
            u.email,
            u.role,
            u.created_at

        ORDER BY u.id DESC
    ");
}

/* =========================================================
   CUSTOMER STATISTICS
========================================================= */

$totalCustomers = 0;
$totalOrders = 0;
$totalRevenue = 0;

$statsResult = $conn->query("
    SELECT

        COUNT(DISTINCT u.id) AS total_customers,

        COUNT(DISTINCT
            CASE
                WHEN o.id IS NOT NULL
                THEN o.id
            END
        ) AS total_orders,

        COALESCE(
            SUM(
                CASE
                    WHEN o.status = 'Completed'
                    THEN o.total_amount
                    ELSE 0
                END
            ),
            0
        ) AS total_revenue

    FROM users u

    LEFT JOIN orders o
        ON o.user_id = u.id

    WHERE u.role = 'customer'
");

if ($statsResult) {

    $stats = $statsResult->fetch_assoc();

    $totalCustomers =
        (int) ($stats["total_customers"] ?? 0);

    $totalOrders =
        (int) ($stats["total_orders"] ?? 0);

    $totalRevenue =
        (float) ($stats["total_revenue"] ?? 0);
}

/* =========================================================
   VIEW CUSTOMER
========================================================= */

$viewCustomer = null;
$customerOrders = [];

if (isset($_GET["view"])) {

    $viewId = (int) $_GET["view"];

    if ($viewId > 0) {

        /*
         * Customer information
         */

        $stmt = $conn->prepare("
            SELECT
                id,
                name,
                email,
                role,
                created_at
            FROM users
            WHERE id = ?
            AND role = 'customer'
            LIMIT 1
        ");

        $stmt->bind_param("i", $viewId);
        $stmt->execute();

        $result = $stmt->get_result();

        $viewCustomer = $result->fetch_assoc();

        $stmt->close();


        /*
         * Customer orders
         */

        if ($viewCustomer) {

            $stmt = $conn->prepare("
                SELECT
                    id,
                    total_amount,
                    payment_method,
                    status,
                    order_date
                FROM orders
                WHERE user_id = ?
                ORDER BY id DESC
            ");

            $stmt->bind_param("i", $viewId);
            $stmt->execute();

            $orderResult = $stmt->get_result();

            while ($order = $orderResult->fetch_assoc()) {
                $customerOrders[] = $order;
            }

            $stmt->close();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Customers | Acebes Coffee Admin</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
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

/* =========================================================
   LAYOUT
========================================================= */

.admin-layout {
    min-height: 100vh;
    display: flex;
}

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    width: 250px;

    background: #2a1a13;
    color: white;

    padding: 28px 18px;

    position: fixed;

    left: 0;
    top: 0;
    bottom: 0;

    z-index: 100;
}

.brand {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 12px 30px;

    border-bottom:
        1px solid rgba(255,255,255,.1);

    margin-bottom: 24px;
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

.brand-text {
    min-width: 0;
}

.brand h1 {
    font-family: inherit;

    font-size: 20px;

    color: #f5efe6;
}

.brand p {
    color: #c49a6c;

    font-size: 12px;

    margin-top: 5px;
}

.admin-profile {
    background:
        rgba(255,255,255,.07);

    padding: 13px;

    border-radius: 12px;

    margin-bottom: 22px;
}

.admin-profile strong {
    display: block;

    font-size: 14px;
}

.admin-profile span {
    display: block;

    color: #c49a6c;

    font-size: 12px;

    margin-top: 3px;
}

.nav-title {
    color: #a99688;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: 1.5px;

    padding: 0 12px;

    margin-bottom: 8px;
}

.sidebar nav a {
    display: flex;

    align-items: center;

    gap: 12px;

    padding: 13px 14px;

    border-radius: 10px;

    margin-bottom: 5px;

    color: #ddd0c8;

    font-size: 14px;

    transition: .2s;
}

.sidebar nav a:hover,
.sidebar nav a.active {
    background: #4b2e20;

    color: white;
}

.sidebar-bottom {
    position: absolute;

    left: 18px;
    right: 18px;

    bottom: 25px;
}

.sidebar-bottom a {
    display: block;

    padding: 12px 14px;

    border-radius: 10px;

    color: #d7c6bb;

    font-size: 13px;

    margin-top: 6px;
}

.sidebar-bottom a:hover {
    background:
        rgba(255,255,255,.07);
}

/* =========================================================
   MAIN
========================================================= */

.main {
    margin-left: 250px;

    width:
        calc(100% - 250px);

    padding: 35px;
}

.page-header {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 28px;
}

.page-header h2 {
    font-family: inherit;

    font-size: 32px;
}

.page-header p {
    color: #806f64;

    margin-top: 5px;

    font-size: 14px;
}

/* =========================================================
   STATS
========================================================= */

.stats {
    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 16px;

    margin-bottom: 25px;
}

.stat-card {
    background: white;

    border:
        1px solid #eadfd5;

    border-radius: 15px;

    padding: 20px;
}

.stat-card span {
    color: #88766a;

    font-size: 12px;
}

.stat-card strong {
    display: block;

    font-family: inherit;

    font-size: 28px;

    margin-top: 7px;
}

.stat-card.revenue {
    background: #4b2e20;

    color: white;
}

.stat-card.revenue span {
    color: #d9c7b9;
}

/* =========================================================
   CONTENT
========================================================= */

.content-card {
    background: white;

    border:
        1px solid #eadfd5;

    border-radius: 18px;

    overflow: hidden;
}

.toolbar {
    padding: 18px;

    border-bottom:
        1px solid #eee4dc;

    display: flex;

    justify-content: space-between;

    gap: 15px;
}

.search-box {
    display: flex;

    gap: 8px;

    width:
        min(450px, 100%);
}

.search-box input {
    flex: 1;

    border:
        1px solid #dfd2c7;

    border-radius: 9px;

    padding: 11px 13px;

    outline: none;

    font-size: 14px;
}

.search-box input:focus {
    border-color: #c49a6c;
}

.search-box button {
    border: 0;

    background: #f0e7df;

    color: #4b2e20;

    padding: 0 17px;

    border-radius: 9px;

    cursor: pointer;

    font-weight: 700;
}

/* =========================================================
   TABLE
========================================================= */

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 850px;
}

th {
    background: #fbf8f5;

    text-align: left;

    padding: 14px 18px;

    color: #806f64;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .8px;
}

td {
    padding: 15px 18px;

    border-top:
        1px solid #f0e7df;

    font-size: 14px;
}

/* =========================================================
   CUSTOMER
========================================================= */

.customer-cell {
    display: flex;

    align-items: center;

    gap: 12px;
}

.avatar {
    width: 43px;
    height: 43px;

    flex-shrink: 0;

    border-radius: 50%;

    background: #4b2e20;

    color: white;

    display: flex;

    align-items: center;
    justify-content: center;

    font-family: inherit;

    font-weight: 700;

    font-size: 17px;
}

.customer-name {
    font-weight: 700;
}

.customer-email {
    color: #907e72;

    font-size: 12px;

    margin-top: 3px;
}

.orders-count {
    font-weight: 700;
}

.spent {
    font-weight: 800;

    color: #4b2e20;
}

.joined {
    color: #77665b;

    font-size: 13px;
}

/* =========================================================
   ACTIONS
========================================================= */

.actions {
    display: flex;

    gap: 7px;

    align-items: center;
}

.action-btn {
    border: 0;

    padding: 7px 10px;

    border-radius: 8px;

    font-size: 12px;

    font-weight: 700;

    cursor: pointer;
}

.view-btn {
    background: #f0e7df;

    color: #4b2e20;
}

.view-btn:hover {
    background: #e4d5c8;
}

.delete-btn {
    background: #f8e5e3;

    color: #a13f35;
}

.delete-btn:hover {
    background: #f0d2ce;
}

/* =========================================================
   ALERT
========================================================= */

.alert {
    padding: 13px 16px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 13px;

    font-weight: 600;
}

.alert.success {
    background: #e7f3e9;

    color: #387346;
}

/* =========================================================
   MODAL
========================================================= */

.modal {
    position: fixed;

    inset: 0;

    background:
        rgba(30,18,12,.65);

    display: none;

    align-items: center;

    justify-content: center;

    padding: 20px;

    z-index: 500;
}

.modal.show {
    display: flex;
}

.modal-box {
    background: white;

    width:
        min(720px, 100%);

    max-height: 92vh;

    overflow-y: auto;

    border-radius: 20px;

    padding: 28px;

    box-shadow:
        0 25px 70px
        rgba(0,0,0,.25);
}

.modal-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 22px;
}

.modal-header h3 {
    font-family: inherit;

    font-size: 25px;
}

.close-btn {
    width: 35px;
    height: 35px;

    border: 0;

    border-radius: 50%;

    background: #f2ebe5;

    cursor: pointer;

    font-size: 18px;
}

/* =========================================================
   PROFILE BOX
========================================================= */

.profile-header {
    display: flex;

    align-items: center;

    gap: 16px;

    padding-bottom: 22px;

    border-bottom:
        1px solid #eee4dc;

    margin-bottom: 20px;
}

.profile-avatar {
    width: 65px;
    height: 65px;

    border-radius: 50%;

    background: #4b2e20;

    color: white;

    display: flex;

    align-items: center;
    justify-content: center;

    font-family: inherit;

    font-size: 25px;

    font-weight: 700;
}

.profile-header h4 {
    font-family: inherit;

    font-size: 21px;
}

.profile-header p {
    color: #8c7b70;

    font-size: 13px;

    margin-top: 3px;
}

.info-grid {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 13px;

    margin-bottom: 25px;
}

.info-box {
    background: #fbf8f5;

    border:
        1px solid #eee4dc;

    border-radius: 12px;

    padding: 15px;
}

.info-box label {
    display: block;

    color: #8b796d;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: 1px;

    margin-bottom: 5px;
}

.info-box strong {
    font-size: 14px;
}

/* =========================================================
   CUSTOMER ORDERS
========================================================= */

.orders-title {
    font-family: inherit;

    font-size: 19px;

    margin-bottom: 12px;
}

.customer-order {
    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    padding: 13px 0;

    border-bottom:
        1px solid #eee4dc;
}

.customer-order-number {
    font-weight: 800;

    color: #4b2e20;
}

.customer-order-date {
    color: #907e72;

    font-size: 12px;

    margin-top: 3px;
}

.customer-order-right {
    text-align: right;
}

.customer-order-total {
    font-weight: 800;
}

.order-status {
    display: inline-block;

    margin-top: 4px;

    padding: 4px 8px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 800;
}

.order-status.pending {
    background: #fff2d8;
    color: #9a6a16;
}

.order-status.processing {
    background: #e6eff8;
    color: #37668c;
}

.order-status.completed {
    background: #e4f3e7;
    color: #397348;
}

.order-status.cancelled {
    background: #f8e5e3;
    color: #a13f35;
}

.empty-orders {
    color: #8c7b70;

    padding: 20px 0;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

    .sidebar {
        width: 215px;
    }

    .main {
        margin-left: 215px;

        width:
            calc(100% - 215px);

        padding: 25px;
    }

    .stats {
        grid-template-columns:
            1fr 1fr;
    }
}

@media (max-width: 700px) {

    .sidebar {
        position: static;

        width: 100%;

        min-height: auto;
    }

    .admin-layout {
        display: block;
    }

    .sidebar-bottom {
        position: static;

        margin-top: 20px;
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

    .stats {
        grid-template-columns: 1fr;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<div class="admin-layout">

<!-- =====================================================
     SIDEBAR
====================================================== -->

<?php include __DIR__ . "/sidebar.php"; ?>


<!-- =====================================================
     MAIN
====================================================== -->

<main class="main">

    <div class="page-header">

        <div>

            <h2>Customers</h2>

            <p>
                Manage registered customers and view their order history.
            </p>

        </div>

    </div>


    <!-- ALERT -->

    <?php if (isset($_GET["deleted"])): ?>

        <div class="alert success">
            Customer deleted successfully.
        </div>

    <?php endif; ?>


    <!-- =================================================
         STATS
    ================================================== -->

    <section class="stats">

        <div class="stat-card">

            <span>
                Total Customers
            </span>

            <strong>
                <?= $totalCustomers ?>
            </strong>

        </div>


        <div class="stat-card">

            <span>
                Total Orders
            </span>

            <strong>
                <?= $totalOrders ?>
            </strong>

        </div>


        <div class="stat-card revenue">

            <span>
                Completed Revenue
            </span>

            <strong>
                ₱<?= number_format(
                    $totalRevenue,
                    2
                ) ?>
            </strong>

        </div>

    </section>


    <!-- =================================================
         CUSTOMER TABLE
    ================================================== -->

    <section class="content-card">

        <div class="toolbar">

            <form
                method="GET"
                class="search-box"
            >

                <input
                    type="text"
                    name="search"
                    placeholder="Search customer name or email..."
                    value="<?= htmlspecialchars($search) ?>"
                >

                <button type="submit">
                    Search
                </button>

            </form>

        </div>


        <div class="table-wrap">

            <?php if ($customers && $customers->num_rows > 0): ?>

                <table>

                    <thead>

                    <tr>

                        <th>
                            Customer
                        </th>

                        <th>
                            Orders
                        </th>

                        <th>
                            Total Spent
                        </th>

                        <th>
                            Joined
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                    </thead>


                    <tbody>

                    <?php while ($customer = $customers->fetch_assoc()): ?>

                        <?php

                        $customerName =
                            $customer["name"] ?: "Customer";

                        $initial =
                            strtoupper(
                                substr(
                                    $customerName,
                                    0,
                                    1
                                )
                            );

                        ?>

                        <tr>

                            <td>

                                <div class="customer-cell">

                                    <div class="avatar">
                                        <?= htmlspecialchars($initial) ?>
                                    </div>

                                    <div>

                                        <div class="customer-name">
                                            <?= htmlspecialchars(
                                                $customerName
                                            ) ?>
                                        </div>

                                        <div class="customer-email">
                                            <?= htmlspecialchars(
                                                $customer["email"]
                                            ) ?>
                                        </div>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <span class="orders-count">
                                    <?= (int)$customer["order_count"] ?>
                                </span>

                            </td>


                            <td>

                                <span class="spent">

                                    ₱<?= number_format(
                                        (float)$customer["total_spent"],
                                        2
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span class="joined">

                                    <?= date(
                                        "M d, Y",
                                        strtotime(
                                            $customer["created_at"]
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <div class="actions">

                                    <a
                                        href="customers.php?view=<?= (int)$customer["id"] ?>"
                                        class="action-btn view-btn"
                                    >
                                        View
                                    </a>


                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="return confirm('Delete this customer account?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="customer_id"
                                            value="<?= (int)$customer["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete_customer"
                                            class="action-btn delete-btn"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div
                    style="
                        text-align:center;
                        padding:60px 20px;
                        color:#8c7b70;
                    "
                >

                    <div
                        style="
                            font-size:40px;
                            margin-bottom:12px;
                        "
                    >
                        👥
                    </div>

                    <h3
                        style="
                            font-family:inherit;
                            color:#4b2e20;
                            margin-bottom:5px;
                        "
                    >
                        No customers found
                    </h3>

                    <p>
                        Registered customers will appear here.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>

</div>


<!-- =========================================================
     CUSTOMER VIEW MODAL
========================================================= -->

<?php if ($viewCustomer): ?>

<div
    class="modal show"
    id="customerModal"
>

    <div class="modal-box">

        <div class="modal-header">

            <h3>
                Customer Details
            </h3>

            <button
                class="close-btn"
                onclick="closeCustomerModal()"
            >
                ×
            </button>

        </div>


        <!-- CUSTOMER PROFILE -->

        <div class="profile-header">

            <div class="profile-avatar">

                <?= htmlspecialchars(
                    strtoupper(
                        substr(
                            $viewCustomer["name"],
                            0,
                            1
                        )
                    )
                ) ?>

            </div>


            <div>

                <h4>
                    <?= htmlspecialchars(
                        $viewCustomer["name"]
                    ) ?>
                </h4>

                <p>
                    <?= htmlspecialchars(
                        $viewCustomer["email"]
                    ) ?>
                </p>

            </div>

        </div>


        <!-- CUSTOMER INFO -->

        <div class="info-grid">

            <div class="info-box">

                <label>
                    Customer ID
                </label>

                <strong>
                    #<?= (int)$viewCustomer["id"] ?>
                </strong>

            </div>


            <div class="info-box">

                <label>
                    Account Role
                </label>

                <strong>
                    <?= htmlspecialchars(
                        ucfirst(
                            $viewCustomer["role"]
                        )
                    ) ?>
                </strong>

            </div>


            <div class="info-box">

                <label>
                    Email
                </label>

                <strong>
                    <?= htmlspecialchars(
                        $viewCustomer["email"]
                    ) ?>
                </strong>

            </div>


            <div class="info-box">

                <label>
                    Registered
                </label>

                <strong>
                    <?= date(
                        "M d, Y",
                        strtotime(
                            $viewCustomer["created_at"]
                        )
                    ) ?>
                </strong>

            </div>

        </div>


        <!-- ORDER HISTORY -->

        <div class="orders-title">
            Order History
        </div>


        <?php if (!empty($customerOrders)): ?>

            <?php foreach ($customerOrders as $order): ?>

                <?php
                $orderStatusClass =
                    strtolower(
                        $order["status"]
                    );
                ?>

                <div class="customer-order">

                    <div>

                        <div class="customer-order-number">

                            Order #<?= str_pad(
                                (int)$order["id"],
                                5,
                                "0",
                                STR_PAD_LEFT
                            ) ?>

                        </div>

                        <div class="customer-order-date">

                            <?= date(
                                "M d, Y • h:i A",
                                strtotime(
                                    $order["order_date"]
                                )
                            ) ?>

                            ·

                            <?= htmlspecialchars(
                                $order["payment_method"]
                            ) ?>

                        </div>

                    </div>


                    <div class="customer-order-right">

                        <div class="customer-order-total">

                            ₱<?= number_format(
                                (float)$order["total_amount"],
                                2
                            ) ?>

                        </div>


                        <span
                            class="
                                order-status
                                <?= $orderStatusClass ?>
                            "
                        >

                            <?= htmlspecialchars(
                                $order["status"]
                            ) ?>

                        </span>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty-orders">

                This customer has no orders yet.

            </div>

        <?php endif; ?>

    </div>

</div>

<?php endif; ?>


<script>

function closeCustomerModal() {

    window.location.href = "customers.php";

}


document.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {

            closeCustomerModal();

        }

    }
);


const customerModal =
    document.getElementById("customerModal");

if (customerModal) {

    customerModal.addEventListener(
        "click",
        function(event) {

            if (event.target === this) {

                closeCustomerModal();

            }

        }
    );

}

</script>

</body>
</html>