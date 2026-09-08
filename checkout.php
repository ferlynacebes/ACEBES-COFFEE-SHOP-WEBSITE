<?php

session_start();
require_once __DIR__ . "/config/db.php";

/* =========================================================
   CUSTOMER ACCESS
========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

$error = "";
$old = [
    "customer_name" => "",
    "customer_email" => "",
    "phone" => "",
    "address" => "",
    "payment_method" => "",
    "gcash_receipt" => ""
];

/* =========================================================
   DATABASE HELPERS
========================================================= */

function getCartProducts(mysqli $conn, int $user_id): array
{
    $products = [];

    $sql = "
        SELECT
            c.product_id,
            c.quantity,
            p.name,
            p.price,
            p.stock,
            p.image
        FROM cart AS c
        INNER JOIN products AS p
            ON p.id = c.product_id
        WHERE c.user_id = ?
          AND LOWER(p.status) IN ('available', 'active')
          AND c.quantity > 0
        ORDER BY c.id ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Unable to load the cart.");
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception("Unable to load the cart.");
    }

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row["product_id"] = (int) $row["product_id"];
        $row["quantity"] = (int) $row["quantity"];
        $row["price"] = (float) $row["price"];
        $row["stock"] = (int) ($row["stock"] ?? 0);
        $row["image"] = (string) ($row["image"] ?? "");
        $row["subtotal"] = $row["price"] * $row["quantity"];

        $products[] = $row;
    }

    $stmt->close();

    return $products;
}

function getCartCount(mysqli $conn, int $user_id): int
{
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(quantity), 0) AS cart_count
         FROM cart
         WHERE user_id = ?"
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    return (int) ($row["cart_count"] ?? 0);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

/* =========================================================
   GET CUSTOMER
========================================================= */

$stmt = $conn->prepare(
    "SELECT name, email
     FROM users
     WHERE id = ?
     LIMIT 1"
);

if (!$stmt) {
    die("Database error. Please try again later.");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

$stmt->close();

if (!$user) {
    $_SESSION = [];
    session_destroy();

    header("Location: login.php");
    exit();
}

/* =========================================================
   INITIAL FORM VALUES
========================================================= */

$old["customer_name"] = (string) ($user["name"] ?? "");
$old["customer_email"] = (string) ($user["email"] ?? "");

/* =========================================================
   LOAD CART
========================================================= */

try {
    $cart_products = getCartProducts($conn, $user_id);
} catch (Exception $e) {
    error_log("Acebes Coffee checkout cart error: " . $e->getMessage());
    $cart_products = [];
    $error = "We couldn't load your cart. Please return to your cart and try again.";
}

$grand_total = 0.00;

foreach ($cart_products as $product) {
    $grand_total += (float) $product["subtotal"];
}

$cart_count = getCartCount($conn, $user_id);

/* =========================================================
   REDIRECT IF CART IS EMPTY
========================================================= */

if (empty($cart_products) && $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: cart.php");
    exit();
}

/* =========================================================
   PLACE ORDER
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $old["customer_name"] = trim($_POST["customer_name"] ?? "");
    $old["customer_email"] = trim($_POST["customer_email"] ?? "");
    $old["phone"] = trim($_POST["phone"] ?? "");
    $old["address"] = trim($_POST["address"] ?? "");
    $old["payment_method"] = trim($_POST["payment_method"] ?? "");
    $old["gcash_receipt"] = trim($_POST["gcash_receipt"] ?? "");

    /* -------------------------
       VALIDATION
    ------------------------- */

    if (
        $old["customer_name"] === "" ||
        $old["customer_email"] === "" ||
        $old["phone"] === "" ||
        $old["address"] === "" ||
        $old["payment_method"] === ""
    ) {
        $error = "Please complete all required checkout fields.";

    } elseif (!filter_var($old["customer_email"], FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } elseif (strlen($old["customer_name"]) < 2) {
        $error = "Please enter your complete name.";

    } elseif (!preg_match("/^[0-9+()\\-\\s]{7,20}$/", $old["phone"])) {
        $error = "Please enter a valid phone number.";

    } elseif (!in_array(
        $old["payment_method"],
        ["Cash on Delivery", "GCash"],
        true
    )) {
        $error = "Please select a valid payment method.";

    } elseif (
        $old["payment_method"] === "GCash" &&
        $old["gcash_receipt"] === ""
    ) {
        $error = "Please enter your GCash receipt/reference number.";

    } elseif (
        $old["payment_method"] === "GCash" &&
        !preg_match('/^[A-Za-z0-9\-]{5,100}$/', $old["gcash_receipt"])
    ) {
        $error = "Please enter a valid GCash receipt/reference number.";

    } else {

        /*
         * Re-read the cart immediately before checkout.
         * This prevents an outdated checkout page from creating
         * an order using old quantities.
         */
        try {
            $cart_products = getCartProducts($conn, $user_id);
        } catch (Exception $e) {
            error_log("Acebes Coffee checkout refresh error: " . $e->getMessage());
            $cart_products = [];
            $error = "We couldn't verify your cart. Please try again.";
        }

        $grand_total = 0.00;

        foreach ($cart_products as $product) {
            $grand_total += (float) $product["subtotal"];
        }

        if (empty($cart_products)) {

            $error = "Your cart is empty. Please add an item before checking out.";

        } elseif ($grand_total <= 0) {

            $error = "Your cart total is invalid. Please return to your cart.";

        } else {

            $transaction_started = false;

            try {

                /* =================================================
                   START TRANSACTION
                ================================================= */

                $conn->begin_transaction();
                $transaction_started = true;

                /* =================================================
                   FINAL STOCK VALIDATION
                   Lock each product row so stock cannot change while
                   this order is being created.
                ================================================= */

                foreach ($cart_products as $product) {
                    $product_id = (int) $product["product_id"];
                    $quantity = (int) $product["quantity"];

                    $verify_stmt = $conn->prepare(
                        "SELECT name, stock, status
                         FROM products
                         WHERE id = ?
                         LIMIT 1
                         FOR UPDATE"
                    );

                    if (!$verify_stmt) {
                        throw new Exception(
                            "Unable to verify product stock: " . $conn->error
                        );
                    }

                    $verify_stmt->bind_param("i", $product_id);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->get_result();
                    $verify_product = $verify_result->fetch_assoc();
                    $verify_stmt->close();

                    if (!$verify_product) {
                        throw new Exception("A product in your cart no longer exists.");
                    }

                    $current_stock = (int) $verify_product["stock"];
                    $current_status = strtolower((string) $verify_product["status"]);

                    if ($current_stock <= 0 || !in_array($current_status, ["available", "active"], true)) {
                        throw new Exception(
                            (string) $verify_product["name"] . " is currently unavailable."
                        );
                    }

                    if ($quantity > $current_stock) {
                        throw new Exception(
                            "Only " . $current_stock . " unit(s) of " .
                            (string) $verify_product["name"] . " are available."
                        );
                    }
                }

                /* =================================================
                   CREATE ORDER
                ================================================= */

                $status = "Pending";

                $order_stmt = $conn->prepare(
                    "INSERT INTO orders
                    (
                        user_id,
                        customer_name,
                        customer_email,
                        phone,
                        address,
                        total_amount,
                        payment_method,
                        gcash_receipt,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                if (!$order_stmt) {
                    throw new Exception(
                        "Unable to prepare the order record: " . $conn->error
                    );
                }

                $order_stmt->bind_param(
                    "issssdsss",
                    $user_id,
                    $old["customer_name"],
                    $old["customer_email"],
                    $old["phone"],
                    $old["address"],
                    $grand_total,
                    $old["payment_method"],
                    $old["gcash_receipt"],
                    $status
                );

                if (!$order_stmt->execute()) {
                    $db_error = $order_stmt->error;
                    $order_stmt->close();

                    throw new Exception(
                        "Order insert failed: " . $db_error
                    );
                }

                $order_id = (int) $conn->insert_id;

                $order_stmt->close();

                if ($order_id <= 0) {
                    throw new Exception("The order ID could not be created.");
                }

                /* =================================================
                   CREATE ORDER ITEMS
                ================================================= */

                $item_stmt = $conn->prepare(
                    "INSERT INTO order_items
                    (
                        order_id,
                        product_id,
                        product_name,
                        price,
                        quantity,
                        subtotal
                    )
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                if (!$item_stmt) {
                    throw new Exception(
                        "Unable to prepare order items: " . $conn->error
                    );
                }

                foreach ($cart_products as $product) {

                    $product_id = (int) $product["product_id"];
                    $product_name = (string) $product["name"];
                    $price = (float) $product["price"];
                    $quantity = (int) $product["quantity"];
                    $subtotal = (float) $product["subtotal"];

                    if ($product_id <= 0 || $quantity <= 0) {
                        throw new Exception("Invalid product in cart.");
                    }

                    $item_stmt->bind_param(
                        "iisdid",
                        $order_id,
                        $product_id,
                        $product_name,
                        $price,
                        $quantity,
                        $subtotal
                    );

                    if (!$item_stmt->execute()) {
                        $db_error = $item_stmt->error;
                        $item_stmt->close();

                        throw new Exception(
                            "Order item insert failed: " . $db_error
                        );
                    }
                }

                $item_stmt->close();

                /* =================================================
                   REDUCE PRODUCT STOCK
                ================================================= */
                $stock_stmt = $conn->prepare(
                    "UPDATE products
                     SET stock = stock - ?
                     WHERE id = ?
                       AND stock >= ?"
                );

                if (!$stock_stmt) {
                    throw new Exception(
                        "Unable to prepare stock update: " . $conn->error
                    );
                }

                foreach ($cart_products as $product) {
                    $product_id = (int) $product["product_id"];
                    $quantity = (int) $product["quantity"];

                    if ($product_id <= 0 || $quantity <= 0) {
                        throw new Exception("Invalid product quantity in cart.");
                    }

                    $stock_stmt->bind_param(
                        "iii",
                        $quantity,
                        $product_id,
                        $quantity
                    );

                    if (!$stock_stmt->execute()) {
                        $db_error = $stock_stmt->error;
                        $stock_stmt->close();
                        throw new Exception(
                            "Stock update failed: " . $db_error
                        );
                    }

                    if ($stock_stmt->affected_rows !== 1) {
                        $stock_stmt->close();
                        throw new Exception(
                            "Not enough stock available for " .
                            (string) $product["name"] . "."
                        );
                    }
                }

                $stock_stmt->close();

                /* =================================================
                   AUTO MARK SOLD-OUT PRODUCTS INACTIVE
                ================================================= */
                $conn->query(
                    "UPDATE products
                     SET status = 'Unavailable'
                     WHERE stock <= 0"
                );

                /* =================================================
                   CLEAR DATABASE CART
                ================================================= */

                $clear_stmt = $conn->prepare(
                    "DELETE FROM cart
                     WHERE user_id = ?"
                );

                if (!$clear_stmt) {
                    throw new Exception(
                        "Unable to prepare cart cleanup: " . $conn->error
                    );
                }

                $clear_stmt->bind_param("i", $user_id);

                if (!$clear_stmt->execute()) {
                    $db_error = $clear_stmt->error;
                    $clear_stmt->close();

                    throw new Exception(
                        "Cart cleanup failed: " . $db_error
                    );
                }

                $clear_stmt->close();

                /* =================================================
                   COMMIT EVERYTHING
                ================================================= */

                if (!$conn->commit()) {
                    throw new Exception("The order could not be committed.");
                }

                $transaction_started = false;

                /*
                 * The order is now permanently stored in:
                 *   orders
                 *   order_items
                 *
                 * Its initial status is Pending, so the admin
                 * Orders page can display and manage it.
                 */
                header("Location: orders.php?success=1&order=" . $order_id);
                exit();

            } catch (Throwable $e) {

                if ($transaction_started) {
                    $conn->rollback();
                }

                error_log(
                    "Acebes Coffee checkout error for user {$user_id}: " .
                    $e->getMessage()
                );

                $error =
                    "We couldn't place your order right now. " .
                    "Please check your information and try again.";
            }
        }
    }
}

/* =========================================================
   RELOAD CART COUNT AFTER POST FAILURE
========================================================= */

$cart_count = getCartCount($conn, $user_id);

?>

<?php include 'includes/header.php'; ?>

<style>
    :root {
        --checkout-espresso: #2a1a13;
        --checkout-dark: #1d0f0a;
        --checkout-coffee: #4b2e20;
        --checkout-caramel: #c49a6c;
        --checkout-cream: #f6f0e8;
        --checkout-paper: #fffdf9;
        --checkout-text: #2a1a13;
        --checkout-muted: #8a7568;
        --checkout-border: #e6d8ca;
        --checkout-success: #39704e;
        --checkout-danger: #a34d47;
    }

    .checkout-page,
    .checkout-page * {
        box-sizing: border-box;
    }

    .checkout-page {
        color: var(--checkout-text);
        background:
            radial-gradient(circle at 8% 10%, rgba(196,154,108,.09), transparent 28%),
            radial-gradient(circle at 94% 35%, rgba(75,46,32,.07), transparent 30%),
            var(--checkout-cream);
        min-height: 0;
        font-family: "Montserrat", sans-serif;
    }

    .checkout-page a {
        text-decoration: none;
        color: inherit;
    }

    .checkout-hero {
        position: relative;
        overflow: hidden;
        padding: 68px 24px 58px;
        background:
            linear-gradient(120deg,
                rgba(42,26,19,.99),
                rgba(75,46,32,.96));
        color: #fff;
    }

    .checkout-hero::before,
    .checkout-hero::after {
        content: "";
        position: absolute;
        border: 1px solid rgba(255,255,255,.10);
        border-radius: 50%;
        pointer-events: none;
    }

    .checkout-hero::before {
        width: 360px;
        height: 360px;
        right: -120px;
        top: -190px;
    }

    .checkout-hero::after {
        width: 220px;
        height: 220px;
        left: -110px;
        bottom: -150px;
    }

    .checkout-hero-inner {
        position: relative;
        z-index: 1;
        width: min(1180px, 100%);
        margin: 0 auto;
    }

    .checkout-eyebrow {
        margin-bottom: 10px;
        color: var(--checkout-caramel);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .20em;
        text-transform: uppercase;
    }

    .checkout-hero h1 {
        margin: 0;
        color: #fff;
        font-family: "Pacifico", cursive;
        font-size: clamp(42px, 5vw, 62px);
        font-weight: 400;
        line-height: 1.05;
    }

    .checkout-hero p {
        max-width: 600px;
        margin: 14px 0 0;
        color: rgba(255,255,255,.72);
        font-size: 14px;
        line-height: 1.7;
    }

    .checkout-wrap {
        position: relative;
        z-index: 2;
        width: min(1200px, calc(100% - 40px));
        margin: -30px auto 80px;
    }

    .checkout-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 24px;
        align-items: start;
    }

    /* Keep both checkout boxes centered on the cream background. */
    .checkout-wrap {
        left: 0;
        right: 0;
        margin-left: auto;
        margin-right: auto;
    }

    .checkout-panel {
        overflow: hidden;
        border: 1px solid var(--checkout-border);
        border-radius: 24px;
        background: rgba(255,255,255,.96);
        box-shadow: 0 24px 70px rgba(42,26,19,.11);
    }

    .checkout-panel-head {
        padding: 28px 30px 22px;
        border-bottom: 1px solid var(--checkout-border);
    }

    .checkout-kicker {
        margin-bottom: 6px;
        color: var(--checkout-caramel);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .16em;
        text-transform: uppercase;
    }

    .checkout-panel-head h2,
    .checkout-summary-head h2 {
        margin: 0;
        color: var(--checkout-espresso);
        font-family: Georgia, "Times New Roman", serif;
        font-weight: 600;
    }

    .checkout-panel-head h2 {
        font-size: 26px;
    }

    .checkout-panel-head p {
        margin: 7px 0 0;
        color: var(--checkout-muted);
        font-size: 12px;
        line-height: 1.6;
    }

    .checkout-form-body {
        padding: 28px 30px 32px;
    }

    .checkout-alert {
        display: flex;
        gap: 10px;
        margin-bottom: 22px;
        padding: 13px 15px;
        border: 1px solid #ecd0cc;
        border-radius: 12px;
        background: #fcf0ef;
        color: var(--checkout-danger);
        font-size: 11px;
        line-height: 1.5;
    }

    .checkout-section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 3px 0 18px;
        color: var(--checkout-espresso);
        font-size: 11px;
        font-weight: 800;
    }

    .checkout-section-number {
        display: grid;
        width: 25px;
        height: 25px;
        place-items: center;
        border-radius: 8px;
        background: #eee2d4;
        color: var(--checkout-coffee);
        font-size: 10px;
    }

    .checkout-form-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 17px;
        align-items: start;
    }

    .checkout-form-group {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .checkout-form-group.full {
        grid-column: 1 / -1;
    }

    .checkout-form-group {
        min-width: 0;
    }

    .checkout-form-group label {
        color: #5b4a40;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .checkout-required {
        color: var(--checkout-caramel);
    }

    .checkout-form-group input,
    .checkout-form-group textarea {
        width: 100%;
        border: 1px solid #e4d7ca;
        border-radius: 12px;
        outline: none;
        background: #fffdfa;
        color: var(--checkout-text);
        font-family: "Montserrat", sans-serif;
        font-size: 12px;
        transition: .2s ease;
    }

    .checkout-form-group input {
        height: 48px;
        padding: 0 14px;
    }

    .checkout-form-group textarea {
        min-height: 112px;
        padding: 13px 14px;
        resize: vertical;
        line-height: 1.5;
    }

    .checkout-form-group input:focus,
    .checkout-form-group textarea:focus {
        border-color: var(--checkout-caramel);
        box-shadow: 0 0 0 4px rgba(196,154,108,.12);
        background: #fff;
    }

    .checkout-form-group input::placeholder,
    .checkout-form-group textarea::placeholder {
        color: #b3a59a;
    }

    .checkout-hint {
        color: #9b8b7e;
        font-size: 9px;
        line-height: 1.4;
    }

    .checkout-payment-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .checkout-payment-option {
        position: relative;
    }

    .checkout-payment-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .checkout-payment-option > label {
        display: flex;
        align-items: center;
        gap: 11px;
        min-height: 65px;
        padding: 11px 13px;
        border: 1px solid #e4d7ca;
        border-radius: 13px;
        background: #fffdfa;
        cursor: pointer;
        transition: .2s ease;
    }

    .checkout-payment-option > label:hover {
        border-color: #d4b89b;
        transform: translateY(-1px);
    }

    .checkout-payment-option input:checked + label {
        border-color: var(--checkout-caramel);
        background: #fff8f0;
        box-shadow: 0 0 0 3px rgba(196,154,108,.10);
    }

    .checkout-payment-icon {
        display: grid;
        width: 35px;
        height: 35px;
        flex: 0 0 35px;
        place-items: center;
        border-radius: 10px;
        background: #eee2d4;
        color: var(--checkout-coffee);
        font-size: 14px;
        font-weight: 800;
    }

    .checkout-payment-copy strong {
        display: block;
        font-size: 11px;
    }

    .checkout-payment-copy span {
        display: block;
        margin-top: 3px;
        color: var(--checkout-muted);
        font-size: 9px;
    }

    .checkout-gcash-field {
        display: none;
        margin-top: 13px;
        padding: 15px;
        border: 1px solid rgba(196,154,108,.20);
        border-radius: 13px;
        background: rgba(196,154,108,.06);
    }

    .checkout-gcash-field.visible {
        display: block;
    }

    .checkout-gcash-field label {
        display: block;
        margin-bottom: 7px;
        color: #5b4a40;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .checkout-gcash-field input {
        width: 100%;
        height: 47px;
        padding: 0 13px;
        border: 1px solid #dccbb9;
        border-radius: 10px;
        outline: none;
        background: #fffdfa;
        font-family: "Montserrat", sans-serif;
        font-size: 12px;
    }

    .checkout-gcash-field input:focus {
        border-color: var(--checkout-caramel);
        box-shadow: 0 0 0 3px rgba(196,154,108,.10);
    }

    .checkout-form-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin-top: 27px;
        padding-top: 21px;
        border-top: 1px solid var(--checkout-border);
    }

    .checkout-secure-note {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #8e7d70;
        font-size: 9px;
        line-height: 1.5;
    }

    .checkout-secure-icon {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        border-radius: 9px;
        background: #f4ece3;
        color: var(--checkout-success);
        font-weight: 800;
    }

    .checkout-place-btn {
        min-height: 48px;
        padding: 0 25px;
        border: 0;
        border-radius: 12px;
        background: var(--checkout-coffee);
        color: #fff;
        cursor: pointer;
        font-family: "Montserrat", sans-serif;
        font-size: 11px;
        font-weight: 800;
        transition: .2s ease;
        box-shadow: 0 10px 25px rgba(75,46,32,.18);
    }

    .checkout-place-btn:hover {
        background: #3b2419;
        transform: translateY(-1px);
    }

    .checkout-place-btn:disabled {
        opacity: .72;
        cursor: wait;
        transform: none;
    }

    .checkout-summary {
        position: sticky;
        top: 105px;
    }

    .checkout-summary-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 23px 24px;
        border-bottom: 1px solid var(--checkout-border);
    }

    .checkout-summary-head h2 {
        font-size: 22px;
    }

    .checkout-item-count {
        padding: 6px 9px;
        border-radius: 9px;
        background: #f4ece3;
        color: var(--checkout-coffee);
        font-size: 9px;
        font-weight: 800;
    }

    .checkout-summary-items {
        padding: 6px 24px;
    }

    .checkout-summary-item {
        display: grid;
        grid-template-columns: 58px minmax(0, 1fr) auto;
        gap: 12px;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid #f0e7de;
    }

    .checkout-summary-item:last-child {
        border-bottom: 0;
    }

    .checkout-item-image {
        width: 58px;
        height: 58px;
        overflow: hidden;
        border-radius: 13px;
        background: #f0e4d7;
    }

    .checkout-item-image img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .checkout-item-fallback {
        display: grid;
        width: 100%;
        height: 100%;
        place-items: center;
        color: var(--checkout-coffee);
        font-size: 19px;
    }

    .checkout-item-details {
        min-width: 0;
    }

    .checkout-item-details strong {
        display: block;
        overflow: hidden;
        color: var(--checkout-espresso);
        font-size: 11px;
        font-weight: 800;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .checkout-item-details span {
        display: block;
        margin-top: 4px;
        color: var(--checkout-muted);
        font-size: 9px;
    }

    .checkout-item-price {
        color: var(--checkout-espresso);
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .checkout-summary-bottom {
        padding: 19px 24px 24px;
        border-top: 1px solid var(--checkout-border);
        background: #fcf8f3;
    }

    .checkout-summary-row {
        display: flex;
        justify-content: space-between;
        gap: 15px;
        margin-bottom: 10px;
        color: var(--checkout-muted);
        font-size: 10px;
    }

    .checkout-summary-row strong {
        color: var(--checkout-text);
    }

    .checkout-total {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 15px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px dashed #dbcabb;
    }

    .checkout-total span {
        color: var(--checkout-espresso);
        font-size: 12px;
        font-weight: 800;
    }

    .checkout-total strong {
        color: var(--checkout-espresso);
        font-family: Georgia, "Times New Roman", serif;
        font-size: 28px;
    }

    .checkout-back-cart {
        display: inline-flex;
        margin-top: 17px;
        color: var(--checkout-coffee);
        font-size: 10px;
        font-weight: 800;
    }

    .checkout-back-cart:hover {
        color: var(--checkout-caramel);
    }

    .checkout-trust {
        display: grid;
        gap: 10px;
        margin: 18px 24px 23px;
        padding-top: 18px;
        border-top: 1px solid var(--checkout-border);
    }

    .checkout-trust-row {
        display: flex;
        gap: 9px;
        align-items: center;
        color: #8b796c;
        font-size: 9px;
    }

    .checkout-trust-row b {
        color: var(--checkout-success);
        font-size: 11px;
    }

    @media (max-width: 1100px) {
        .checkout-grid {
            grid-template-columns: 1fr;
        }

        .checkout-summary {
            position: static;
            order: -1;
        }

        .checkout-form-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 700px) {
        .checkout-hero {
            padding: 54px 20px 68px;
        }

        .checkout-wrap {
            width: calc(100% - 24px);
            margin-top: -32px;
        }

        .checkout-form-grid {
            grid-template-columns: 1fr;
        }

        .checkout-form-group.full {
            grid-column: auto;
        }

        .checkout-payment-options {
            grid-template-columns: 1fr;
        }

        .checkout-panel-head,
        .checkout-form-body {
            padding-left: 20px;
            padding-right: 20px;
        }

        .checkout-form-footer {
            align-items: stretch;
            flex-direction: column;
        }

        .checkout-place-btn {
            width: 100%;
        }
    }

    @media (max-width: 430px) {
        .checkout-hero h1 {
            font-size: 43px;
        }

        .checkout-wrap {
            width: calc(100% - 14px);
        }

        .checkout-summary-head,
        .checkout-summary-items,
        .checkout-summary-bottom {
            padding-left: 17px;
            padding-right: 17px;
        }

        .checkout-trust {
            margin-left: 17px;
            margin-right: 17px;
        }

        .checkout-summary-item {
            grid-template-columns: 50px minmax(0, 1fr) auto;
        }

        .checkout-item-image {
            width: 50px;
            height: 50px;
        }
    }
</style>

<div class="checkout-page">

    <section class="checkout-hero">
        <div class="checkout-hero-inner">
            <div class="checkout-eyebrow">ACEBES COFFEE</div>
            <h1>Checkout</h1>
            <p>
                You're almost there. Confirm your details, choose your payment
                method, and we'll prepare your order with care.
            </p>
        </div>
    </section>

    <main class="checkout-wrap">
        <div class="checkout-grid">

            <section class="checkout-panel">

                <div class="checkout-panel-head">
                    <div class="checkout-kicker">Order Details</div>
                    <h2>Complete your order</h2>
                    <p>
                        Your account information is already filled in.
                        Please confirm the details below.
                    </p>
                </div>

                <div class="checkout-form-body">

                    <?php if ($error !== ""): ?>
                        <div class="checkout-alert">
                            <strong>!</strong>
                            <span><?php echo e($error); ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="checkout.php" method="POST" id="checkoutForm">

                        <div class="checkout-section-title">
                            <span class="checkout-section-number">1</span>
                            Customer Information
                        </div>

                        <div class="checkout-form-grid">

                            <div class="checkout-form-group">
                                <label for="customer_name">
                                    Full Name <span class="checkout-required">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="customer_name"
                                    name="customer_name"
                                    value="<?php echo e($old["customer_name"]); ?>"
                                    placeholder="Your full name"
                                    maxlength="100"
                                    autocomplete="name"
                                    required
                                >
                            </div>

                            <div class="checkout-form-group">
                                <label for="customer_email">
                                    Email Address <span class="checkout-required">*</span>
                                </label>
                                <input
                                    type="email"
                                    id="customer_email"
                                    name="customer_email"
                                    value="<?php echo e($old["customer_email"]); ?>"
                                    placeholder="you@example.com"
                                    maxlength="150"
                                    autocomplete="email"
                                    required
                                >
                            </div>

                            <div class="checkout-form-group">
                                <label for="phone">
                                    Phone Number <span class="checkout-required">*</span>
                                </label>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    value="<?php echo e($old["phone"]); ?>"
                                    placeholder="09XXXXXXXXX"
                                    maxlength="20"
                                    autocomplete="tel"
                                    inputmode="tel"
                                    required
                                >
                                <span class="checkout-hint">Example: 09123456789</span>
                            </div>

                            <div class="checkout-form-group full">
                                <label for="address">
                                    Delivery Address <span class="checkout-required">*</span>
                                </label>
                                <textarea
                                    id="address"
                                    name="address"
                                    placeholder="House number, street, barangay, municipality/city, province"
                                    maxlength="500"
                                    autocomplete="street-address"
                                    required
                                ><?php echo e($old["address"]); ?></textarea>
                                <span class="checkout-hint">
                                    Please provide enough detail so your order can be delivered correctly.
                                </span>
                            </div>

                        </div>

                        <div class="checkout-section-title" style="margin-top:30px;">
                            <span class="checkout-section-number">2</span>
                            Payment Method
                        </div>

                        <div class="checkout-payment-options">

                            <div class="checkout-payment-option">
                                <input
                                    type="radio"
                                    id="payment_cod"
                                    name="payment_method"
                                    value="Cash on Delivery"
                                    <?php echo $old["payment_method"] === "Cash on Delivery" ? "checked" : ""; ?>
                                    required
                                >
                                <label for="payment_cod">
                                    <span class="checkout-payment-icon">₱</span>
                                    <span class="checkout-payment-copy">
                                        <strong>Cash on Delivery</strong>
                                        <span>Pay when your order arrives.</span>
                                    </span>
                                </label>
                            </div>

                            <div class="checkout-payment-option">
                                <input
                                    type="radio"
                                    id="payment_gcash"
                                    name="payment_method"
                                    value="GCash"
                                    <?php echo $old["payment_method"] === "GCash" ? "checked" : ""; ?>
                                >
                                <label for="payment_gcash">
                                    <span class="checkout-payment-icon">G</span>
                                    <span class="checkout-payment-copy">
                                        <strong>GCash</strong>
                                        <span>Pay using GCash.</span>
                                    </span>
                                </label>

                                <div
                                    class="checkout-gcash-field"
                                    id="gcashReceiptField"
                                    <?php echo $old["payment_method"] === "GCash" ? 'style="display:block;"' : ""; ?>
                                >
                                    <label for="gcash_receipt">
                                        GCash Reference Number
                                        <span class="checkout-required">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        id="gcash_receipt"
                                        name="gcash_receipt"
                                        value="<?php echo e($old["gcash_receipt"]); ?>"
                                        maxlength="100"
                                        inputmode="numeric"
                                        pattern="[0-9]+"
                                        placeholder="Enter GCash reference number"
                                        autocomplete="off"
                                    >

                                    <span class="checkout-hint">
                                        Numbers only. Enter the reference number shown after your GCash payment.
                                    </span>
                                </div>
                            </div>

                        </div>

                        <div class="checkout-form-footer">

                            <div class="checkout-secure-note">
                                <span class="checkout-secure-icon">✓</span>
                                <span>
                                    Your order will be saved securely to your
                                    <strong>Acebes Coffee</strong> account.
                                </span>
                            </div>

                            <button
                                type="submit"
                                class="checkout-place-btn"
                                id="placeOrderBtn"
                            >
                                Place Order · ₱<?php echo number_format($grand_total, 2); ?>
                            </button>

                        </div>

                    </form>
                </div>
            </section>

            <aside class="checkout-panel checkout-summary">

                <div class="checkout-summary-head">
                    <h2>Your Order</h2>
                    <span class="checkout-item-count">
                        <?php echo $cart_count; ?>
                        <?php echo $cart_count === 1 ? "item" : "items"; ?>
                    </span>
                </div>

                <div class="checkout-summary-items">

                    <?php foreach ($cart_products as $product): ?>
                        <?php
                            $imageName = trim((string)($product["image"] ?? ""));
                            $imagePath = "";

                            if ($imageName !== "") {
                                $cleanImage = ltrim($imageName, "/\\");
                                if (strpos($cleanImage, "/") !== false || strpos($cleanImage, "\\") !== false) {
                                    $imagePath = $cleanImage;
                                } else {
                                    $imagePath = "assets/images/" . $cleanImage;
                                }
                            }
                        ?>

                        <div class="checkout-summary-item">

                            <div class="checkout-item-image">
                                <?php if ($imagePath !== ""): ?>
                                    <img
                                        src="<?php echo e($imagePath); ?>"
                                        alt="<?php echo e((string)$product["name"]); ?>"
                                    >
                                <?php else: ?>
                                    <div class="checkout-item-fallback">☕</div>
                                <?php endif; ?>
                            </div>

                            <div class="checkout-item-details">
                                <strong><?php echo e((string)$product["name"]); ?></strong>
                                <span>
                                    Qty <?php echo (int)$product["quantity"]; ?>
                                    × ₱<?php echo number_format((float)$product["price"], 2); ?>
                                </span>
                            </div>

                            <div class="checkout-item-price">
                                ₱<?php echo number_format((float)$product["subtotal"], 2); ?>
                            </div>

                        </div>
                    <?php endforeach; ?>

                </div>

                <div class="checkout-summary-bottom">

                    <div class="checkout-summary-row">
                        <span>Subtotal</span>
                        <strong>₱<?php echo number_format($grand_total, 2); ?></strong>
                    </div>

                    <div class="checkout-summary-row">
                        <span>Delivery</span>
                        <strong>₱0.00</strong>
                    </div>

                    <div class="checkout-total">
                        <span>Total</span>
                        <strong>₱<?php echo number_format($grand_total, 2); ?></strong>
                    </div>

                    <a href="cart.php" class="checkout-back-cart">
                        ← Back to Cart
                    </a>

                </div>

                <div class="checkout-trust">
                    <div class="checkout-trust-row">
                        <b>✓</b>
                        Your order is connected to your account.
                    </div>

                    <div class="checkout-trust-row">
                        <b>✓</b>
                        Order status starts as Pending.
                    </div>

                    <div class="checkout-trust-row">
                        <b>✓</b>
                        You can track the order from My Orders.
                    </div>
                </div>

            </aside>

        </div>
    </main>

</div>

<?php include 'includes/footer.php'; ?>

<script>
    const paymentInputs = document.querySelectorAll('input[name="payment_method"]');
    const gcashReceiptField = document.getElementById('gcashReceiptField');
    const gcashReceiptInput = document.getElementById('gcash_receipt');

    function updateGcashReceiptField() {
        const selected = document.querySelector('input[name="payment_method"]:checked');
        const isGcash = selected && selected.value === 'GCash';

        if (gcashReceiptField) {
            gcashReceiptField.style.display = isGcash ? 'block' : 'none';
        }

        if (gcashReceiptInput) {
            gcashReceiptInput.required = !!isGcash;

            if (!isGcash) {
                gcashReceiptInput.value = '';
            }
        }
    }

    paymentInputs.forEach(function(input) {
        input.addEventListener('change', updateGcashReceiptField);
    });

    if (gcashReceiptInput) {
        gcashReceiptInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    }

    updateGcashReceiptField();

    const checkoutForm = document.getElementById('checkoutForm');
    const placeOrderBtn = document.getElementById('placeOrderBtn');

    if (checkoutForm && placeOrderBtn) {
        checkoutForm.addEventListener('submit', function() {
            placeOrderBtn.disabled = true;
            placeOrderBtn.textContent = 'Placing Order...';
        });
    }
</script>
