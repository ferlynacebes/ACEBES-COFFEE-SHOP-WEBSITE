<?php
session_start();

require_once __DIR__ . "/config/db.php";

/* =========================================================
   CUSTOMER AUTHENTICATION
========================================================= */
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$pageTitle = "My Orders";

/* =========================================================
   LOAD CUSTOMER ORDERS + ORDER ITEMS
========================================================= */
$orders = [];

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
        oi.id AS item_id,
        oi.product_id,
        oi.product_name,
        oi.price,
        oi.quantity,
        oi.subtotal
    FROM orders o
    LEFT JOIN order_items oi
        ON oi.order_id = o.id
    WHERE o.user_id = ?
    ORDER BY o.order_date DESC, o.id DESC
");

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $orderId = (int) $row["id"];

        if (!isset($orders[$orderId])) {
            $orders[$orderId] = [
                "id" => $orderId,
                "customer_name" => $row["customer_name"],
                "customer_email" => $row["customer_email"],
                "phone" => $row["phone"],
                "address" => $row["address"],
                "total_amount" => (float) $row["total_amount"],
                "payment_method" => $row["payment_method"],
                "gcash_receipt" => $row["gcash_receipt"],
                "status" => $row["status"],
                "order_date" => $row["order_date"],
                "updated_at" => $row["updated_at"],
                "items" => []
            ];
        }

        if (!empty($row["item_id"])) {
            $orders[$orderId]["items"][] = [
                "product_id" => (int) $row["product_id"],
                "product_name" => $row["product_name"],
                "price" => (float) $row["price"],
                "quantity" => (int) $row["quantity"],
                "subtotal" => (float) $row["subtotal"]
            ];
        }
    }

    $stmt->close();
}

$orders = array_values($orders);

/* =========================================================
   HELPERS
========================================================= */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function status_class(string $status): string
{
    return strtolower(preg_replace("/[^a-zA-Z0-9]+/", "-", $status));
}

function format_order_date(?string $date): string
{
    if (!$date) {
        return "—";
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return e($date);
    }

    return date("M d, Y • h:i A", $timestamp);
}
?>

<?php include __DIR__ . "/includes/header.php"; ?>

<style>
    .orders-page {
        background: #f7f2ed;
        min-height: calc(100vh - 90px);
        padding: 70px 20px 90px;
    }

    .orders-container {
        width: min(1100px, 100%);
        margin: 0 auto;
    }

    .orders-hero {
        text-align: center;
        margin-bottom: 45px;
    }

    .orders-label {
        display: inline-block;
        margin-bottom: 10px;
        color: #a66a3f;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    .orders-hero h1 {
        margin: 0 0 12px;
        color: #2b1b14;
        font-size: clamp(34px, 5vw, 52px);
        line-height: 1.1;
    }

    .orders-hero p {
        max-width: 650px;
        margin: 0 auto;
        color: #765f53;
        font-size: 16px;
        line-height: 1.7;
    }

    .orders-list {
        display: grid;
        gap: 22px;
    }

    .order-card {
        overflow: hidden;
        background: #fff;
        border: 1px solid #eadfd6;
        border-radius: 20px;
        box-shadow: 0 12px 35px rgba(55, 35, 24, 0.08);
    }

    .order-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 22px 25px;
        border-bottom: 1px solid #eee4dc;
        background: #fffdfb;
    }

    .order-number {
        margin: 0 0 5px;
        color: #2b1b14;
        font-size: 20px;
        font-weight: 800;
    }

    .order-date {
        color: #8a7468;
        font-size: 13px;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 112px;
        padding: 9px 15px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .3px;
    }

    .status-badge.pending {
        color: #8a5b00;
        background: #fff2cc;
    }

    .status-badge.processing {
        color: #245b91;
        background: #e4f1ff;
    }

    .status-badge.completed {
        color: #216b42;
        background: #e3f6e9;
    }

    .status-badge.cancelled {
        color: #9a3030;
        background: #fde7e7;
    }

    .order-body {
        padding: 25px;
    }

    .order-grid {
        display: grid;
        grid-template-columns: 1.4fr .8fr;
        gap: 25px;
    }

    .section-heading {
        margin: 0 0 15px;
        color: #3a271e;
        font-size: 15px;
        font-weight: 800;
    }

    .items-list {
        display: grid;
        gap: 12px;
    }

    .order-item {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 15px;
        align-items: center;
        padding: 14px 16px;
        background: #faf6f2;
        border: 1px solid #eee3da;
        border-radius: 13px;
    }

    .item-name {
        margin: 0 0 4px;
        color: #332119;
        font-size: 14px;
        font-weight: 750;
    }

    .item-meta {
        color: #8a7468;
        font-size: 12px;
    }

    .item-subtotal {
        color: #3b2418;
        font-size: 14px;
        font-weight: 800;
        white-space: nowrap;
    }

    .order-info {
        padding: 18px;
        background: #fbf7f3;
        border: 1px solid #eee3da;
        border-radius: 15px;
    }

    .info-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 15px;
        padding: 11px 0;
        border-bottom: 1px solid #eee5df;
    }

    .info-row:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .info-row:first-child {
        padding-top: 0;
    }

    .info-label {
        color: #8b7569;
        font-size: 12px;
    }

    .info-value {
        color: #352219;
        font-size: 13px;
        font-weight: 750;
        text-align: right;
    }

    .payment-badge {
        display: inline-flex;
        padding: 6px 10px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 800;
    }

    .payment-badge.gcash {
        color: #245a91;
        background: #e7f2ff;
    }

    .payment-badge.cash {
        color: #6c4b27;
        background: #f4e9d9;
    }

    .receipt-box {
        margin-top: 7px;
        padding: 9px 11px;
        color: #4c3528;
        background: #f2e9e1;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        word-break: break-all;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        margin-top: 18px;
        padding-top: 18px;
        border-top: 2px solid #e9ddd3;
    }

    .total-label {
        color: #6f5b50;
        font-size: 13px;
        font-weight: 700;
    }

    .total-amount {
        color: #9b5f38;
        font-size: 22px;
        font-weight: 900;
    }

    .order-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 16px 25px;
        border-top: 1px solid #eee4dc;
        background: #fffdfb;
    }

    .status-note {
        color: #8a7468;
        font-size: 12px;
    }

    .order-actions {
        display: flex;
        gap: 10px;
    }

    .btn-orders {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
        padding: 0 17px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 800;
        transition: .2s ease;
    }

    .btn-orders.primary {
        color: #fff;
        background: #5a3828;
    }

    .btn-orders.primary:hover {
        background: #3f271c;
        transform: translateY(-1px);
    }

    .btn-orders.secondary {
        color: #5a3828;
        background: #f2e7de;
    }

    .btn-orders.secondary:hover {
        background: #e8d8ca;
    }

    .empty-orders {
        padding: 70px 25px;
        text-align: center;
        background: #fff;
        border: 1px solid #eadfd6;
        border-radius: 20px;
        box-shadow: 0 12px 35px rgba(55, 35, 24, 0.07);
    }

    .empty-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 72px;
        height: 72px;
        margin: 0 auto 20px;
        color: #8e6044;
        background: #f3e7dd;
        border-radius: 50%;
        font-size: 30px;
    }

    .empty-orders h2 {
        margin: 0 0 10px;
        color: #342118;
        font-size: 25px;
    }

    .empty-orders p {
        margin: 0 auto 25px;
        max-width: 480px;
        color: #7d685d;
        line-height: 1.7;
    }

    @media (max-width: 800px) {
        .order-grid {
            grid-template-columns: 1fr;
        }

        .order-top,
        .order-footer {
            align-items: flex-start;
            flex-direction: column;
        }

        .order-actions {
            width: 100%;
        }

        .btn-orders {
            flex: 1;
        }
    }

    @media (max-width: 520px) {
        .orders-page {
            padding: 45px 14px 70px;
        }

        .order-body,
        .order-top,
        .order-footer {
            padding: 18px;
        }

        .order-item {
            grid-template-columns: 1fr;
        }

        .item-subtotal {
            text-align: left;
        }

        .info-row {
            flex-direction: column;
            gap: 4px;
        }

        .info-value {
            text-align: left;
        }

        .order-actions {
            flex-direction: column;
        }
    }
</style>

<main class="orders-page">
    <div class="orders-container">

        <section class="orders-hero">
            <span class="orders-label">Order History</span>
            <h1>My Orders</h1>
            <p>
                Track your coffee orders, payment details, and current order status
                all in one place.
            </p>
        </section>

        <?php if (empty($orders)): ?>

            <section class="empty-orders">
                <div class="empty-icon">☕</div>

                <h2>No Orders Yet</h2>

                <p>
                    You haven't placed an order yet. Browse our menu and enjoy
                    your favorite coffee from Acebes Coffee.
                </p>

                <a href="menu.php" class="btn-orders primary">
                    Browse Menu
                </a>
            </section>

        <?php else: ?>

            <div class="orders-list">

                <?php foreach ($orders as $order): ?>

                    <?php
                        $status = (string) $order["status"];
                        $payment = (string) $order["payment_method"];
                        $statusClass = status_class($status);
                    ?>

                    <article class="order-card">

                        <div class="order-top">
                            <div>
                                <h2 class="order-number">
                                    Order #<?= (int) $order["id"] ?>
                                </h2>

                                <div class="order-date">
                                    Placed <?= format_order_date($order["order_date"]) ?>
                                </div>
                            </div>

                            <span class="status-badge <?= e($statusClass) ?>">
                                <?= e($status) ?>
                            </span>
                        </div>

                        <div class="order-body">

                            <div class="order-grid">

                                <div>
                                    <h3 class="section-heading">
                                        Ordered Items
                                    </h3>

                                    <div class="items-list">

                                        <?php if (!empty($order["items"])): ?>

                                            <?php foreach ($order["items"] as $item): ?>

                                                <div class="order-item">
                                                    <div>
                                                        <p class="item-name">
                                                            <?= e($item["product_name"]) ?>
                                                        </p>

                                                        <div class="item-meta">
                                                            ₱<?= number_format($item["price"], 2) ?>
                                                            ×
                                                            <?= (int) $item["quantity"] ?>
                                                        </div>
                                                    </div>

                                                    <div class="item-subtotal">
                                                        ₱<?= number_format($item["subtotal"], 2) ?>
                                                    </div>
                                                </div>

                                            <?php endforeach; ?>

                                        <?php else: ?>

                                            <div class="order-item">
                                                <div>
                                                    <p class="item-name">
                                                        Order items unavailable
                                                    </p>
                                                </div>
                                            </div>

                                        <?php endif; ?>

                                    </div>
                                </div>

                                <div>
                                    <h3 class="section-heading">
                                        Payment Details
                                    </h3>

                                    <div class="order-info">

                                        <div class="info-row">
                                            <span class="info-label">
                                                Payment Method
                                            </span>

                                            <span class="info-value">
                                                <?php if (strcasecmp($payment, "GCash") === 0): ?>

                                                    <span class="payment-badge gcash">
                                                        GCash
                                                    </span>

                                                <?php else: ?>

                                                    <span class="payment-badge cash">
                                                        Cash
                                                    </span>

                                                <?php endif; ?>
                                            </span>
                                        </div>

                                        <?php if (strcasecmp($payment, "GCash") === 0): ?>

                                            <div class="info-row">
                                                <span class="info-label">
                                                    Receipt / Reference
                                                </span>

                                                <span class="info-value">
                                                    <?php if (!empty($order["gcash_receipt"])): ?>

                                                        <?= e($order["gcash_receipt"]) ?>

                                                    <?php else: ?>

                                                        Not provided

                                                    <?php endif; ?>
                                                </span>
                                            </div>

                                        <?php endif; ?>

                                        <div class="info-row">
                                            <span class="info-label">
                                                Order Status
                                            </span>

                                            <span class="info-value">
                                                <?= e($status) ?>
                                            </span>
                                        </div>

                                        <div class="total-row">
                                            <span class="total-label">
                                                Order Total
                                            </span>

                                            <span class="total-amount">
                                                ₱<?= number_format($order["total_amount"], 2) ?>
                                            </span>
                                        </div>

                                    </div>
                                </div>

                            </div>

                        </div>

                        <div class="order-footer">

                            <div class="status-note">
                                <?php if ($status === "Completed"): ?>
                                    ✓ Your order has been completed.
                                <?php elseif ($status === "Cancelled"): ?>
                                    This order has been cancelled.
                                <?php elseif ($status === "Processing"): ?>
                                    Your order is currently being prepared.
                                <?php else: ?>
                                    Your order is waiting for confirmation.
                                <?php endif; ?>
                            </div>

                            <div class="order-actions">
                                <a href="menu.php" class="btn-orders secondary">
                                    Order Again
                                </a>

                                <a href="profile.php" class="btn-orders primary">
                                    My Profile
                                </a>
                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>
</main>

<?php include __DIR__ . "/includes/footer.php"; ?>
