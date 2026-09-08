<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";

/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE ORDER STATUS
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Once an order is Completed, it can NEVER be changed again.
|
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $orderId = (int) ($_POST["order_id"] ?? 0);
    $newStatus = trim($_POST["status"] ?? "");

    $allowedStatuses = [
        "Pending",
        "Processing",
        "Completed",
        "Cancelled"
    ];

    if (
        $orderId > 0 &&
        in_array($newStatus, $allowedStatuses, true)
    ) {

        /*
         * The WHERE clause is intentionally checking that the
         * current status is NOT Completed.
         */
        $stmt = $conn->prepare("
            UPDATE orders
            SET status = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND status <> 'Completed'
        ");

        if ($stmt) {
            $stmt->bind_param(
                "si",
                $newStatus,
                $orderId
            );

            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: orders.php?updated=1");
    exit;
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/
$search = trim($_GET["search"] ?? "");

/*
|--------------------------------------------------------------------------
| LOAD ORDERS
|--------------------------------------------------------------------------
*/
$orders = [];

if ($search !== "") {

    $searchLike = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT
            o.id,
            o.customer_name,
            o.customer_email,
            o.phone,
            o.address,
            o.total_amount,
            o.payment_method,
            o.gcash_receipt,
            o.status,
            o.order_date,
            o.updated_at,
            u.name AS account_name
        FROM orders o
        LEFT JOIN users u
            ON o.user_id = u.id
        WHERE
            CAST(o.id AS CHAR) LIKE ?
            OR o.customer_name LIKE ?
            OR o.customer_email LIKE ?
            OR o.phone LIKE ?
            OR o.payment_method LIKE ?
            OR o.gcash_receipt LIKE ?
            OR o.status LIKE ?
        ORDER BY o.order_date DESC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "sssssss",
            $searchLike,
            $searchLike,
            $searchLike,
            $searchLike,
            $searchLike,
            $searchLike,
            $searchLike
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }

        $stmt->close();
    }

} else {

    $result = $conn->query("
        SELECT
            o.id,
            o.customer_name,
            o.customer_email,
            o.phone,
            o.address,
            o.total_amount,
            o.payment_method,
            o.gcash_receipt,
            o.status,
            o.order_date,
            o.updated_at,
            u.name AS account_name
        FROM orders o
        LEFT JOIN users u
            ON o.user_id = u.id
        ORDER BY o.order_date DESC
    ");

    if ($result) {

        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}

/*
|--------------------------------------------------------------------------
| SUMMARY COUNTS
|--------------------------------------------------------------------------
*/
$totalOrders = count($orders);
$pendingCount = 0;
$processingCount = 0;
$completedCount = 0;

foreach ($orders as $order) {

    if ($order["status"] === "Pending") {
        $pendingCount++;
    }

    if ($order["status"] === "Processing") {
        $processingCount++;
    }

    if ($order["status"] === "Completed") {
        $completedCount++;
    }
}

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? "",
        ENT_QUOTES,
        "UTF-8"
    );
}

function statusClass(string $status): string
{
    return match ($status) {
        "Pending" => "status-pending",
        "Processing" => "status-processing",
        "Completed" => "status-completed",
        "Cancelled" => "status-cancelled",
        default => "status-default"
    };
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

    <title>Orders | Acebes Coffee Admin</title>

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

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f6f1eb;
            color: #2a1a13;
            font-family: "Montserrat", sans-serif;
        }

        .main {
            margin-left: 250px;
            min-height: 100vh;
            padding: 38px;
        }

        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }

        .page-header h1 {
            margin: 0 0 7px;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -.03em;
        }

        .page-header p {
            margin: 0;
            color: #806f65;
            font-size: 13px;
        }

        .alert {
            margin-bottom: 20px;
            padding: 14px 17px;
            border-radius: 12px;
            background: #edf6e8;
            border: 1px solid #d1e3c8;
            color: #557447;
            font-size: 13px;
            font-weight: 600;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 17px;
            margin-bottom: 25px;
        }

        .stat-card {
            padding: 21px;
            background: #fff;
            border: 1px solid #eee3da;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(42, 26, 19, .05);
        }

        .stat-card span {
            display: block;
            margin-bottom: 9px;
            color: #806f65;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
        }

        .stat-card strong {
            font-size: 25px;
            font-weight: 800;
        }

        .content-card {
            overflow: hidden;
            background: #fff;
            border: 1px solid #eee3da;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(42, 26, 19, .06);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 23px 25px;
            border-bottom: 1px solid #eee4dc;
        }

        .card-header h2 {
            margin: 0;
            font-size: 18px;
        }

        .card-header p {
            margin: 5px 0 0;
            color: #8b7b72;
            font-size: 12px;
        }

        .search-form {
            display: flex;
            gap: 8px;
            width: min(100%, 390px);
        }

        .search-input {
            width: 100%;
            min-width: 0;
            padding: 11px 13px;
            border: 1px solid #ded1c7;
            border-radius: 10px;
            outline: none;
            font-family: inherit;
            font-size: 12px;
        }

        .search-input:focus {
            border-color: #9a7355;
        }

        .search-button {
            padding: 0 17px;
            border: 0;
            border-radius: 10px;
            background: #2a1a13;
            color: #fff;
            font-family: inherit;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        th {
            padding: 14px 18px;
            background: #faf7f4;
            color: #78685f;
            font-size: 10px;
            font-weight: 800;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: .07em;
            white-space: nowrap;
        }

        td {
            padding: 18px;
            border-top: 1px solid #f0e8e2;
            vertical-align: middle;
            font-size: 12px;
        }

        tbody tr:hover {
            background: #fffdfb;
        }

        .order-number {
            color: #6c4935;
            font-weight: 800;
        }

        .customer-name {
            display: block;
            margin-bottom: 4px;
            color: #2a1a13;
            font-weight: 700;
        }

        .customer-email,
        .customer-phone {
            display: block;
            color: #8b7b72;
            font-size: 10px;
        }

        .order-total {
            color: #2a1a13;
            font-weight: 800;
            white-space: nowrap;
        }

        .payment-method {
            display: inline-flex;
            align-items: center;
            padding: 6px 9px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .payment-method.gcash {
            background: #eee8f8;
            color: #674b8c;
        }

        .payment-method.cash {
            background: #edf4e9;
            color: #557449;
        }

        .receipt-number {
            display: block;
            max-width: 180px;
            margin-top: 6px;
            color: #705e54;
            font-size: 9px;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .no-receipt {
            display: block;
            margin-top: 6px;
            color: #a24e43;
            font-size: 9px;
        }

        .cash-note {
            margin-top: 6px;
            color: #8c7c73;
            font-size: 9px;
        }

        .payment-paid {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 7px;
            padding: 5px 9px;
            border-radius: 8px;
            background: #e5f3e8;
            color: #397149;
            font-size: 9px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-form {
            margin: 0;
        }

        .status-select {
            min-width: 122px;
            padding: 8px 10px;
            border: 1px solid #ded2c9;
            border-radius: 9px;
            background: #fff;
            color: #4b3429;
            font-family: inherit;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
            outline: none;
        }

        .status-select:focus {
            border-color: #987354;
        }

        .completed-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 11px;
            border-radius: 9px;
            background: #e5f3e8;
            color: #397149;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .completed-status::before {
            content: "✓";
            font-size: 11px;
        }

        .status-badge {
            display: inline-flex;
            padding: 7px 10px;
            border-radius: 9px;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-pending {
            background: #fff2d9;
            color: #9a6b18;
        }

        .status-processing {
            background: #e8f0f8;
            color: #3d6687;
        }

        .status-completed {
            background: #e5f3e8;
            color: #397149;
        }

        .status-cancelled {
            background: #fae7e4;
            color: #a0483e;
        }

        .status-default {
            background: #eee;
            color: #555;
        }

        .date {
            color: #76665d;
            font-size: 10px;
            line-height: 1.5;
            white-space: nowrap;
        }

        .empty {
            padding: 70px 25px;
            text-align: center;
        }

        .empty-icon {
            margin-bottom: 13px;
            font-size: 38px;
        }

        .empty h3 {
            margin: 0 0 7px;
            font-size: 17px;
        }

        .empty p {
            margin: 0;
            color: #887870;
            font-size: 12px;
        }

        @media (max-width: 1100px) {

            .main {
                padding: 27px;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 700px) {

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats {
                grid-template-columns: 1fr 1fr;
            }

            .card-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .search-form {
                width: 100%;
            }

        }

        @media (max-width: 450px) {

            .stats {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<?php include __DIR__ . "/sidebar.php"; ?>


<main class="main">

    <div class="page-header">

        <div>

            <h1>Orders</h1>

            <p>
                Manage customer transactions, payments, and order status.
            </p>

        </div>

    </div>


    <?php if (isset($_GET["updated"])): ?>

        <div class="alert">
            ✓ Order status updated successfully.
        </div>

    <?php endif; ?>


    <section class="stats">

        <div class="stat-card">
            <span>Total Orders</span>
            <strong><?= $totalOrders ?></strong>
        </div>

        <div class="stat-card">
            <span>Pending</span>
            <strong><?= $pendingCount ?></strong>
        </div>

        <div class="stat-card">
            <span>Processing</span>
            <strong><?= $processingCount ?></strong>
        </div>

        <div class="stat-card">
            <span>Completed</span>
            <strong><?= $completedCount ?></strong>
        </div>

    </section>


    <section class="content-card">

        <div class="card-header">

            <div>

                <h2>All Orders</h2>

                <p>
                    Payment details and customer transactions
                </p>

            </div>


            <form
                method="GET"
                class="search-form"
            >

                <input
                    type="text"
                    name="search"
                    class="search-input"
                    value="<?= e($search) ?>"
                    placeholder="Search order, customer, payment..."
                >

                <button
                    type="submit"
                    class="search-button"
                >
                    SEARCH
                </button>

            </form>

        </div>


        <?php if (empty($orders)): ?>

            <div class="empty">

                <div class="empty-icon">
                    🛒
                </div>

                <h3>
                    No orders found
                </h3>

                <p>
                    Customer orders will appear here after checkout.
                </p>

            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>Order #</th>

                            <th>Customer</th>

                            <th>Total</th>

                            <th>Payment</th>

                            <th>Status</th>

                            <th>Order Date</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($orders as $order): ?>

                        <?php
                        $paymentMethod =
                            (string) ($order["payment_method"] ?? "");

                        $receipt =
                            trim((string) ($order["gcash_receipt"] ?? ""));

                        $status =
                            (string) ($order["status"] ?? "Pending");
                        ?>

                        <tr>

                            <td>

                                <span class="order-number">
                                    #<?= (int) $order["id"] ?>
                                </span>

                            </td>


                            <td>

                                <span class="customer-name">
                                    <?= e((string) $order["customer_name"]) ?>
                                </span>

                                <?php if (!empty($order["customer_email"])): ?>

                                    <span class="customer-email">
                                        <?= e((string) $order["customer_email"]) ?>
                                    </span>

                                <?php endif; ?>


                                <?php if (!empty($order["phone"])): ?>

                                    <span class="customer-phone">
                                        <?= e((string) $order["phone"]) ?>
                                    </span>

                                <?php endif; ?>

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

                                <?php if (
                                    strcasecmp(
                                        $paymentMethod,
                                        "GCash"
                                    ) === 0
                                ): ?>

                                    <span class="payment-method gcash">
                                        GCash
                                    </span>

                                    <?php if ($receipt !== ""): ?>

                                        <span class="receipt-number">
                                            Receipt:
                                            <?= e($receipt) ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="no-receipt">
                                            No receipt number
                                        </span>

                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="payment-method cash">
                                        Cash
                                    </span>

                                    <span class="cash-note">
                                        Cash payment
                                    </span>

                                <?php endif; ?>

                                <?php if ($status === "Completed"): ?>
                                    <div class="payment-paid">
                                        ✓ Paid
                                    </div>
                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if ($status === "Completed"): ?>

                                    <!--
                                        NO SELECT / NO ARROW HERE.
                                        Completed orders are permanently locked.
                                    -->

                                    <span class="completed-status">
                                        Completed
                                    </span>

                                <?php elseif ($status === "Cancelled"): ?>

                                    <span
                                        class="status-badge <?= e(statusClass($status)) ?>"
                                    >
                                        Cancelled
                                    </span>

                                <?php else: ?>

                                    <form
                                        method="POST"
                                        class="status-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?= (int) $order["id"] ?>"
                                        >

                                        <select
                                            name="status"
                                            class="status-select"
                                            onchange="this.form.submit()"
                                        >

                                            <option
                                                value="Pending"
                                                <?= $status === "Pending"
                                                    ? "selected"
                                                    : "" ?>
                                            >
                                                Pending
                                            </option>

                                            <option
                                                value="Processing"
                                                <?= $status === "Processing"
                                                    ? "selected"
                                                    : "" ?>
                                            >
                                                Processing
                                            </option>

                                            <option
                                                value="Completed"
                                            >
                                                Completed
                                            </option>

                                            <option
                                                value="Cancelled"
                                                <?= $status === "Cancelled"
                                                    ? "selected"
                                                    : "" ?>
                                            >
                                                Cancelled
                                            </option>

                                        </select>

                                    </form>

                                <?php endif; ?>

                            </td>


                            <td>

                                <span class="date">

                                    <?= date(
                                        "M d, Y",
                                        strtotime(
                                            (string) $order["order_date"]
                                        )
                                    ) ?>

                                    <br>

                                    <?= date(
                                        "h:i A",
                                        strtotime(
                                            (string) $order["order_date"]
                                        )
                                    ) ?>

                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</main>


<script>
/*
|--------------------------------------------------------------------------
| Prevent accidental double submission
|--------------------------------------------------------------------------
*/
document.querySelectorAll(".status-form").forEach(function (form) {

    form.addEventListener("submit", function () {

        const select =
            form.querySelector(".status-select");

        if (select) {
            select.disabled = true;
        }

    });

});
</script>

</body>
</html>
