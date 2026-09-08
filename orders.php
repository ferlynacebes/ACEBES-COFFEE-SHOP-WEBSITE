<?php
session_start();
require_once __DIR__ . "/config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function money($value): string
{
    return "₱" . number_format((float) $value, 2);
}

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
        o.updated_at
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.order_date DESC, o.id DESC
");

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row["items"] = [];
    $orders[(int) $row["id"]] = $row;
}
$stmt->close();

if (!empty($orders)) {
    $orderIds = array_keys($orders);
    $placeholders = implode(",", array_fill(0, count($orderIds), "?"));
    $types = str_repeat("i", count($orderIds));

    $itemStmt = $conn->prepare("
        SELECT
            oi.order_id,
            oi.product_id,
            oi.product_name,
            oi.price,
            oi.quantity,
            oi.subtotal,
            p.image
        FROM order_items oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.order_id DESC, oi.id ASC
    ");

    $bindValues = [$types];
    foreach ($orderIds as $key => $id) {
        $bindValues[] = &$orderIds[$key];
    }
    call_user_func_array([$itemStmt, "bind_param"], $bindValues);

    $itemStmt->execute();
    $itemResult = $itemStmt->get_result();

    while ($item = $itemResult->fetch_assoc()) {
        $orderId = (int) $item["order_id"];
        if (isset($orders[$orderId])) {
            $orders[$orderId]["items"][] = $item;
        }
    }
    $itemStmt->close();
}

$orders = array_values($orders);

$totalOrders = count($orders);
$completedOrders = 0;
$pendingOrders = 0;
$totalSpent = 0;

foreach ($orders as $order) {
    $status = strtolower((string) $order["status"]);
    if ($status === "completed") {
        $completedOrders++;
    }
    if ($status === "pending" || $status === "processing") {
        $pendingOrders++;
    }
    $totalSpent += (float) $order["total_amount"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Acebes Coffee</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --coffee: #2a1a13;
            --coffee-2: #4b2e20;
            --latte: #c49a6c;
            --cream: #f8f2eb;
            --white: #ffffff;
            --text: #2d211c;
            --muted: #796c64;
            --border: #eadfd5;
            --shadow: 0 12px 35px rgba(42, 26, 19, .08);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f7f2ed;
            color: var(--text);
            font-family: 'Montserrat', sans-serif;
        }

        .orders-page {
            min-height: 70vh;
            padding: 54px 6% 80px;
        }

        .orders-container {
            max-width: 1180px;
            margin: 0 auto;
        }

        .page-heading {
            margin-bottom: 28px;
        }

        .eyebrow {
            display: inline-block;
            color: var(--latte);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .page-heading h1 {
            margin: 0 0 8px;
            font-family: 'Playfair Display', serif;
            font-size: clamp(34px, 5vw, 48px);
            color: var(--coffee);
        }

        .page-heading p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 7px 22px rgba(42, 26, 19, .05);
        }

        .summary-label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .7px;
            text-transform: uppercase;
        }

        .summary-value {
            margin-top: 7px;
            color: var(--coffee);
            font-size: 25px;
            font-weight: 800;
        }

        .orders-list {
            display: grid;
            gap: 22px;
        }

        .order-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 22px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .order-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            padding: 22px 24px;
            background: #fffdfb;
            border-bottom: 1px solid var(--border);
        }

        .order-id {
            margin: 0 0 5px;
            color: var(--coffee);
            font-size: 16px;
            font-weight: 800;
        }

        .order-date {
            color: var(--muted);
            font-size: 12px;
        }

        .status-badge,
        .payment-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-pending { background: #fff4d8; color: #8a6400; }
        .status-processing { background: #e7f1ff; color: #225b9b; }
        .status-completed { background: #e5f7e9; color: #28733c; }
        .status-cancelled { background: #fbe8e7; color: #a43c38; }
        .status-default { background: #eee9e5; color: #62564e; }

        .order-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            padding: 18px 24px 0;
        }

        .payment-badge.cash {
            background: #f1ece8;
            color: var(--coffee-2);
        }

        .payment-badge.gcash {
            background: #e8f0ff;
            color: #315caa;
        }

        .receipt {
            width: 100%;
            margin-top: 5px;
            padding: 10px 12px;
            border: 1px dashed #d8c6b8;
            border-radius: 10px;
            color: var(--coffee-2);
            background: #fbf7f3;
            font-size: 12px;
        }

        .receipt strong { font-weight: 800; }

        .items {
            padding: 18px 24px 5px;
        }

        .item-row {
            display: grid;
            grid-template-columns: 58px 1fr auto;
            align-items: center;
            gap: 14px;
            padding: 13px 0;
            border-bottom: 1px solid #f0e8e2;
        }

        .item-row:last-child { border-bottom: 0; }

        .item-image {
            width: 58px;
            height: 58px;
            border-radius: 12px;
            overflow: hidden;
            background: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a28d7e;
            font-size: 22px;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-name {
            margin: 0 0 5px;
            color: var(--coffee);
            font-size: 14px;
            font-weight: 800;
        }

        .item-details {
            color: var(--muted);
            font-size: 11px;
        }

        .item-subtotal {
            color: var(--coffee);
            font-size: 14px;
            font-weight: 800;
        }

        .order-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-top: 10px;
            padding: 18px 24px 22px;
            border-top: 1px solid var(--border);
        }

        .order-note {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.6;
        }

        .order-total-label {
            color: var(--muted);
            font-size: 11px;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .order-total {
            margin-top: 4px;
            color: var(--coffee);
            font-size: 21px;
            font-weight: 800;
            text-align: right;
        }

        .empty-orders {
            padding: 65px 25px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 22px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .empty-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--cream);
            font-size: 30px;
        }

        .empty-orders h2 {
            margin: 0 0 8px;
            color: var(--coffee);
            font-family: 'Playfair Display', serif;
            font-size: 28px;
        }

        .empty-orders p {
            max-width: 500px;
            margin: 0 auto 22px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 19px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 800;
            transition: .2s ease;
        }

        .btn-primary {
            background: var(--coffee);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--coffee-2);
            transform: translateY(-1px);
        }

        .order-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 26px;
        }

        .order-actions .btn-secondary {
            border: 1px solid var(--border);
            color: var(--coffee);
            background: #fff;
        }

        @media (max-width: 900px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .orders-page { padding: 38px 18px 60px; }
            .summary-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .summary-card { padding: 15px; }
            .summary-value { font-size: 20px; }
            .order-top { padding: 18px; }
            .order-meta, .items { padding-left: 18px; padding-right: 18px; }
            .order-bottom { padding-left: 18px; padding-right: 18px; }
            .item-row { grid-template-columns: 48px 1fr; }
            .item-image { width: 48px; height: 48px; }
            .item-subtotal { grid-column: 2; }
            .order-top { flex-direction: column; }
            .order-total { font-size: 19px; }
        }

        @media (max-width: 430px) {
            .summary-grid { grid-template-columns: 1fr; }
            .order-bottom { align-items: flex-start; flex-direction: column; }
            .order-total-label, .order-total { text-align: left; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . "/includes/header.php"; ?>

<main class="orders-page">
    <div class="orders-container">
        <div class="page-heading">
            <span class="eyebrow">Your Coffee Journey</span>
            <h1>My Orders</h1>
            <p>Keep track of your Acebes Coffee orders, payment details, and order status.</p>
        </div>

        <?php if ($totalOrders > 0): ?>
            <section class="summary-grid" aria-label="Order summary">
                <div class="summary-card">
                    <div class="summary-label">Total Orders</div>
                    <div class="summary-value"><?= $totalOrders ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Pending</div>
                    <div class="summary-value"><?= $pendingOrders ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Completed</div>
                    <div class="summary-value"><?= $completedOrders ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Total Spent</div>
                    <div class="summary-value"><?= money($totalSpent) ?></div>
                </div>
            </section>

            <section class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <?php
                        $status = (string) ($order["status"] ?? "Pending");
                        $statusClass = strtolower($status);
                        $payment = (string) ($order["payment_method"] ?? "Cash");
                        $isGcash = strcasecmp($payment, "GCash") === 0;
                    ?>
                    <article class="order-card">
                        <div class="order-top">
                            <div>
                                <div class="order-id">Order #<?= (int) $order["id"] ?></div>
                                <div class="order-date">
                                    <?= e(date("F d, Y • h:i A", strtotime($order["order_date"]))) ?>
                                </div>
                            </div>

                            <?php if ($statusClass === "pending"): ?>
                                <span class="status-badge status-pending">● Pending</span>
                            <?php elseif ($statusClass === "processing"): ?>
                                <span class="status-badge status-processing">● Processing</span>
                            <?php elseif ($statusClass === "completed"): ?>
                                <span class="status-badge status-completed">✓ Completed</span>
                            <?php elseif ($statusClass === "cancelled"): ?>
                                <span class="status-badge status-cancelled">✕ Cancelled</span>
                            <?php else: ?>
                                <span class="status-badge status-default"><?= e($status) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="order-meta">
                            <?php if ($isGcash): ?>
                                <span class="payment-badge gcash">GCash</span>
                                <?php if (!empty($order["gcash_receipt"])): ?>
                                    <div class="receipt">
                                        <strong>GCash Receipt / Reference:</strong>
                                        <?= e($order["gcash_receipt"]) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="receipt">
                                        <strong>GCash Receipt / Reference:</strong> Not provided
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="payment-badge cash">Cash</span>
                            <?php endif; ?>
                        </div>

                        <div class="items">
                            <?php if (!empty($order["items"])): ?>
                                <?php foreach ($order["items"] as $item): ?>
                                    <?php
                                        $image = trim((string) ($item["image"] ?? ""));
                                        if ($image !== "") {
                                            if (preg_match('#^https?://#i', $image)) {
                                                $imageSrc = $image;
                                            } elseif (strpos($image, "/") !== false) {
                                                $imageSrc = "assets/images/" . ltrim($image, "/");
                                            } else {
                                                $imageSrc = "assets/images/" . $image;
                                            }
                                        } else {
                                            $imageSrc = "";
                                        }
                                    ?>
                                    <div class="item-row">
                                        <div class="item-image">
                                            <?php if ($imageSrc !== ""): ?>
                                                <img src="<?= e($imageSrc) ?>" alt="<?= e($item["product_name"]) ?>">
                                            <?php else: ?>
                                                ☕
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <p class="item-name"><?= e($item["product_name"]) ?></p>
                                            <div class="item-details">
                                                <?= (int) $item["quantity"] ?> × <?= money($item["price"]) ?>
                                            </div>
                                        </div>
                                        <div class="item-subtotal"><?= money($item["subtotal"]) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="item-details">No order items found.</div>
                            <?php endif; ?>
                        </div>

                        <div class="order-bottom">
                            <div class="order-note">
                                <?php if ($statusClass === "completed"): ?>
                                    Your order has been completed. Thank you for choosing Acebes Coffee!
                                <?php elseif ($statusClass === "cancelled"): ?>
                                    This order has been cancelled.
                                <?php else: ?>
                                    Your order status is updated by the Acebes Coffee admin.
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="order-total-label">Order Total</div>
                                <div class="order-total"><?= money($order["total_amount"]) ?></div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <div class="order-actions">
                <a href="menu.php" class="btn btn-primary">Order More Coffee</a>
                <a href="profile.php" class="btn btn-secondary">My Profile</a>
            </div>
        <?php else: ?>
            <section class="empty-orders">
                <div class="empty-icon">☕</div>
                <h2>No orders yet</h2>
                <p>
                    You haven't placed an order yet. Browse our menu and find your next favorite cup.
                </p>
                <a href="menu.php" class="btn btn-primary">Browse Menu</a>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . "/includes/footer.php"; ?>

</body>
</html>
