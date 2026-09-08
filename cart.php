<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/db.php";

/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function e(?string $value): string
{
    return htmlspecialchars($value ?? "", ENT_QUOTES, "UTF-8");
}

function redirectCart(string $query = ""): void
{
    header("Location: cart.php" . ($query !== "" ? "?" . $query : ""));
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE / REMOVE CART ITEM
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";
    $cartId = (int) ($_POST["cart_id"] ?? 0);

    if ($cartId <= 0) {
        redirectCart("error=invalid");
    }

    if ($action === "remove") {

        $stmt = $conn->prepare("
            DELETE FROM cart
            WHERE id = ? AND user_id = ?
        ");

        if ($stmt) {
            $stmt->bind_param("ii", $cartId, $userId);
            $stmt->execute();
            $stmt->close();
        }

        redirectCart("updated=1");
    }

    if ($action === "update") {

        $requestedQuantity = (int) ($_POST["quantity"] ?? 1);
        $requestedQuantity = max(1, $requestedQuantity);

        /*
         * Lock/read the cart item together with the current product stock.
         */
        $stmt = $conn->prepare("
            SELECT
                c.id,
                c.quantity,
                p.name,
                p.stock,
                p.status
            FROM cart c
            INNER JOIN products p
                ON p.id = c.product_id
            WHERE c.id = ?
              AND c.user_id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            redirectCart("error=database");
        }

        $stmt->bind_param("ii", $cartId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();

        if (!$item) {
            redirectCart("error=notfound");
        }

        $stock = max(0, (int) $item["stock"]);

        if (
            strcasecmp((string) $item["status"], "Available") !== 0 ||
            $stock <= 0
        ) {
            /*
             * Product is no longer available.
             * Remove it from the cart so checkout cannot contain it.
             */
            $delete = $conn->prepare("
                DELETE FROM cart
                WHERE id = ? AND user_id = ?
            ");

            if ($delete) {
                $delete->bind_param("ii", $cartId, $userId);
                $delete->execute();
                $delete->close();
            }

            redirectCart("error=outofstock");
        }

        if ($requestedQuantity > $stock) {
            redirectCart(
                "error=stock&available=" . $stock .
                "&product=" . urlencode((string) $item["name"])
            );
        }

        $update = $conn->prepare("
            UPDATE cart
            SET quantity = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND user_id = ?
        ");

        if ($update) {
            $update->bind_param(
                "iii",
                $requestedQuantity,
                $cartId,
                $userId
            );
            $update->execute();
            $update->close();
        }

        redirectCart("updated=1");
    }

    redirectCart();
}

/*
|--------------------------------------------------------------------------
| LOAD CART
|--------------------------------------------------------------------------
*/
$cartItems = [];
$totalItems = 0;
$subtotal = 0.00;

$stmt = $conn->prepare("
    SELECT
        c.id AS cart_id,
        c.product_id,
        c.quantity,
        p.name,
        p.description,
        p.price,
        p.stock,
        p.status,
        p.image
    FROM cart c
    INNER JOIN products p
        ON p.id = c.product_id
    WHERE c.user_id = ?
    ORDER BY c.id DESC
");

if (!$stmt) {
    die("Unable to load cart.");
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $row["quantity"] = max(1, (int) $row["quantity"]);
    $row["stock"] = max(0, (int) $row["stock"]);
    $row["price"] = (float) $row["price"];

    /*
     * If stock changed while the customer was away,
     * display the cart safely but cap the usable quantity.
     */
    $row["is_available"] =
        strcasecmp((string) $row["status"], "Available") === 0 &&
        $row["stock"] > 0;

    $row["can_checkout"] =
        $row["is_available"] &&
        $row["quantity"] <= $row["stock"];

    $row["line_total"] =
        $row["price"] * $row["quantity"];

    $cartItems[] = $row;

    $totalItems += $row["quantity"];
    $subtotal += $row["line_total"];
}

$stmt->close();

$shipping = 0.00;
$grandTotal = $subtotal + $shipping;

include __DIR__ . "/includes/header.php";
?>

<section class="page-hero cart-page-hero">
    <div class="container">
        <span class="section-label">YOUR ORDER</span>

        <h1>Your Cart</h1>

        <p>
            Review your coffee selections before checkout.
        </p>
    </div>
</section>

<section class="section cart-section">

    <div class="container">

        <?php if (isset($_GET["updated"])): ?>
            <div class="cart-alert success">
                ✓ Your cart has been updated.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET["error"])): ?>

            <?php
            $errorType = $_GET["error"];
            $errorMessage = "We couldn't update your cart.";

            if ($errorType === "stock") {
                $available = max(0, (int) ($_GET["available"] ?? 0));
                $product = (string) ($_GET["product"] ?? "This product");

                $errorMessage =
                    "Only " . $available . " unit(s) of " .
                    $product . " are currently available.";
            } elseif ($errorType === "outofstock") {
                $errorMessage =
                    "A product in your cart is no longer available and was removed.";
            } elseif ($errorType === "notfound") {
                $errorMessage = "That cart item could not be found.";
            } elseif ($errorType === "database") {
                $errorMessage =
                    "A database error occurred. Please try again.";
            } elseif ($errorType === "invalid") {
                $errorMessage = "Invalid cart item.";
            }
            ?>

            <div class="cart-alert error">
                ⚠ <?= e($errorMessage) ?>
            </div>

        <?php endif; ?>


        <?php if (empty($cartItems)): ?>

            <div class="empty-cart">

                <div class="empty-cart-icon">
                    🛒
                </div>

                <span class="section-label">
                    YOUR CART IS EMPTY
                </span>

                <h2>
                    Nothing here yet.
                </h2>

                <p>
                    Choose one of our handcrafted coffees
                    and add it to your cart.
                </p>

                <a
                    href="menu.php"
                    class="btn btn-primary"
                >
                    BROWSE MENU
                </a>

            </div>

        <?php else: ?>

            <div class="cart-layout">

                <!-- =================================================
                     CART ITEMS
                ================================================== -->

                <div class="cart-items-panel">

                    <div class="cart-panel-header">

                        <div>
                            <span class="section-label">
                                SELECTED ITEMS
                            </span>

                            <h2>
                                Your Coffee
                            </h2>
                        </div>

                        <span class="cart-item-count">
                            <?= $totalItems ?>
                            item<?= $totalItems === 1 ? "" : "s" ?>
                        </span>

                    </div>


                    <div class="cart-items">

                        <?php foreach ($cartItems as $item): ?>

                            <?php
                            $image = trim((string) ($item["image"] ?? ""));
                            $imagePath = $image !== ""
                                ? "assets/images/" . $image
                                : "assets/images/logo.png";

                            $stock = (int) $item["stock"];
                            $quantity = (int) $item["quantity"];
                            $hasStockProblem = !$item["can_checkout"];
                            ?>

                            <article
                                class="cart-item <?= $hasStockProblem ? "cart-item-warning" : "" ?>"
                            >

                                <div class="cart-item-image">

                                    <img
                                        src="<?= e($imagePath) ?>"
                                        alt="<?= e((string) $item["name"]) ?>"
                                    >

                                </div>


                                <div class="cart-item-details">

                                    <div class="cart-item-top">

                                        <div>

                                            <span class="cart-item-category">
                                                COFFEE
                                            </span>

                                            <h3>
                                                <?= e((string) $item["name"]) ?>
                                            </h3>

                                        </div>

                                        <strong class="cart-item-price">
                                            ₱<?= number_format((float) $item["price"], 2) ?>
                                        </strong>

                                    </div>


                                    <?php if (!empty($item["description"])): ?>

                                        <p class="cart-item-description">
                                            <?= e((string) $item["description"]) ?>
                                        </p>

                                    <?php endif; ?>


                                    <?php if ($hasStockProblem): ?>

                                        <div class="stock-warning">
                                            ⚠

                                            <?php if ($stock <= 0): ?>
                                                This product is currently out of stock.
                                            <?php elseif ($quantity > $stock): ?>
                                                Only <?= $stock ?> unit<?= $stock === 1 ? "" : "s" ?> available.
                                                Please reduce your quantity.
                                            <?php else: ?>
                                                This product is currently unavailable.
                                            <?php endif; ?>

                                        </div>

                                    <?php else: ?>

                                        <div class="stock-available">
                                            ● <?= $stock ?> unit<?= $stock === 1 ? "" : "s" ?> available
                                        </div>

                                    <?php endif; ?>


                                    <div class="cart-item-bottom">

                                        <form
                                            method="POST"
                                            class="cart-quantity-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="update"
                                            >

                                            <input
                                                type="hidden"
                                                name="cart_id"
                                                value="<?= (int) $item["cart_id"] ?>"
                                            >

                                            <div class="cart-quantity-control">

                                                <button
                                                    type="button"
                                                    class="cart-qty-btn qty-minus"
                                                    data-target="qty-<?= (int) $item["cart_id"] ?>"
                                                    aria-label="Decrease quantity"
                                                    <?= $quantity <= 1 ? "disabled" : "" ?>
                                                >
                                                    −
                                                </button>

                                                <input
                                                    type="number"
                                                    class="cart-qty-input"
                                                    id="qty-<?= (int) $item["cart_id"] ?>"
                                                    name="quantity"
                                                    value="<?= $quantity ?>"
                                                    min="1"
                                                    max="<?= max(1, $stock) ?>"
                                                    data-stock="<?= $stock ?>"
                                                    aria-label="Quantity"
                                                >

                                                <button
                                                    type="button"
                                                    class="cart-qty-btn qty-plus"
                                                    data-target="qty-<?= (int) $item["cart_id"] ?>"
                                                    aria-label="Increase quantity"
                                                    <?= $quantity >= $stock || $stock <= 0 ? "disabled" : "" ?>
                                                >
                                                    +
                                                </button>

                                            </div>

                                            <button
                                                type="submit"
                                                class="update-cart-button"
                                            >
                                                UPDATE
                                            </button>

                                        </form>


                                        <form method="POST">

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="remove"
                                            >

                                            <input
                                                type="hidden"
                                                name="cart_id"
                                                value="<?= (int) $item["cart_id"] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="remove-cart-button"
                                            >
                                                REMOVE
                                            </button>

                                        </form>

                                    </div>

                                </div>


                                <div class="cart-item-subtotal">

                                    <span>
                                        Subtotal
                                    </span>

                                    <strong>
                                        ₱<?= number_format((float) $item["line_total"], 2) ?>
                                    </strong>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>


                    <div class="continue-shopping">

                        <a href="menu.php">
                            ← Continue Shopping
                        </a>

                    </div>

                </div>


                <!-- =================================================
                     ORDER SUMMARY
                ================================================== -->

                <aside class="cart-summary">

                    <span class="section-label">
                        ORDER SUMMARY
                    </span>

                    <h2>
                        Your Total
                    </h2>


                    <div class="summary-row">

                        <span>
                            Items
                        </span>

                        <strong>
                            <?= $totalItems ?>
                        </strong>

                    </div>


                    <div class="summary-row">

                        <span>
                            Subtotal
                        </span>

                        <strong>
                            ₱<?= number_format($subtotal, 2) ?>
                        </strong>

                    </div>


                    <div class="summary-row">

                        <span>
                            Delivery
                        </span>

                        <strong>
                            FREE
                        </strong>

                    </div>


                    <div class="summary-divider"></div>


                    <div class="summary-total">

                        <span>
                            Total
                        </span>

                        <strong>
                            ₱<?= number_format($grandTotal, 2) ?>
                        </strong>

                    </div>


                    <?php
                    $hasInvalidStock = false;

                    foreach ($cartItems as $item) {
                        if (!$item["can_checkout"]) {
                            $hasInvalidStock = true;
                            break;
                        }
                    }
                    ?>


                    <?php if ($hasInvalidStock): ?>

                        <div class="checkout-disabled-message">
                            ⚠ Please fix the stock quantities above
                            before continuing to checkout.
                        </div>

                        <button
                            type="button"
                            class="btn btn-primary checkout-button disabled"
                            disabled
                        >
                            PROCEED TO CHECKOUT
                        </button>

                    <?php else: ?>

                        <a
                            href="checkout.php"
                            class="btn btn-primary checkout-button"
                        >
                            PROCEED TO CHECKOUT
                        </a>

                    <?php endif; ?>


                    <p class="summary-note">
                        Your final stock availability is checked again
                        securely during checkout.
                    </p>

                </aside>

            </div>

        <?php endif; ?>

    </div>

</section>


<style>
/* =========================================================
   CART PAGE
========================================================= */

.cart-page-hero {
    text-align: center;
}

.cart-section {
    padding-top: 55px;
    padding-bottom: 85px;
}

.cart-alert {
    max-width: 1100px;
    margin: 0 auto 24px;
    padding: 15px 18px;
    border-radius: 12px;
    font-size: 0.92rem;
    font-weight: 600;
}

.cart-alert.success {
    background: #edf6e8;
    color: #527344;
    border: 1px solid #cfe1c5;
}

.cart-alert.error {
    background: #fff0ed;
    color: #8b4137;
    border: 1px solid #ebc7c0;
}

.cart-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 350px;
    gap: 28px;
    align-items: start;
}

.cart-items-panel,
.cart-summary {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 12px 35px rgba(42, 26, 19, 0.08);
}

.cart-items-panel {
    padding: 28px;
}

.cart-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
    padding-bottom: 22px;
    border-bottom: 1px solid #eee4dc;
}

.cart-panel-header h2,
.cart-summary h2 {
    margin: 5px 0 0;
    color: #2a1a13;
}

.cart-item-count {
    padding: 8px 12px;
    border-radius: 999px;
    background: #f5eee8;
    color: #6e4a37;
    font-size: 0.82rem;
    font-weight: 700;
}

.cart-item {
    display: grid;
    grid-template-columns: 105px minmax(0, 1fr) auto;
    gap: 20px;
    padding: 24px 0;
    border-bottom: 1px solid #eee4dc;
}

.cart-item-image {
    width: 105px;
    height: 105px;
    overflow: hidden;
    border-radius: 15px;
    background: #f3ece6;
}

.cart-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cart-item-top {
    display: flex;
    justify-content: space-between;
    gap: 20px;
}

.cart-item-category {
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    color: #b17d50;
}

.cart-item-details h3 {
    margin: 4px 0 0;
    color: #2a1a13;
    font-size: 1.15rem;
}

.cart-item-price {
    color: #7a5238;
    white-space: nowrap;
}

.cart-item-description {
    margin: 9px 0 7px;
    color: #75665e;
    font-size: 0.9rem;
    line-height: 1.6;
}

.stock-available,
.stock-warning {
    margin: 7px 0 13px;
    font-size: 0.78rem;
    font-weight: 600;
}

.stock-available {
    color: #638055;
}

.stock-warning {
    color: #a04d42;
}

.cart-item-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.cart-quantity-form {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cart-quantity-control {
    display: flex;
    align-items: center;
    height: 42px;
    border: 1px solid #ddd0c5;
    border-radius: 10px;
    overflow: hidden;
    background: #faf7f4;
}

.cart-qty-btn {
    width: 38px;
    height: 100%;
    border: 0;
    background: transparent;
    color: #4d3023;
    font-size: 1.2rem;
    cursor: pointer;
}

.cart-qty-btn:hover:not(:disabled) {
    background: #eee2d7;
}

.cart-qty-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.cart-qty-input {
    width: 48px;
    height: 100%;
    border: 0;
    border-left: 1px solid #ddd0c5;
    border-right: 1px solid #ddd0c5;
    background: #fff;
    text-align: center;
    color: #2a1a13;
    font-weight: 700;
    outline: none;
}

.cart-qty-input::-webkit-inner-spin-button,
.cart-qty-input::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.update-cart-button,
.remove-cart-button {
    border: 0;
    background: transparent;
    cursor: pointer;
    font-size: 0.73rem;
    font-weight: 800;
    letter-spacing: 0.06em;
}

.update-cart-button {
    color: #755039;
}

.remove-cart-button {
    color: #a04d42;
}

.update-cart-button:hover,
.remove-cart-button:hover {
    text-decoration: underline;
}

.cart-item-subtotal {
    min-width: 115px;
    text-align: right;
    align-self: center;
}

.cart-item-subtotal span {
    display: block;
    margin-bottom: 5px;
    color: #897970;
    font-size: 0.72rem;
}

.cart-item-subtotal strong {
    color: #2a1a13;
    font-size: 1.02rem;
}

.cart-item-warning {
    background: #fffaf8;
}

.continue-shopping {
    padding-top: 22px;
}

.continue-shopping a {
    color: #79543b;
    font-size: 0.9rem;
    font-weight: 700;
    text-decoration: none;
}

.continue-shopping a:hover {
    text-decoration: underline;
}


/* =========================================================
   SUMMARY
========================================================= */

.cart-summary {
    position: sticky;
    top: 95px;
    padding: 28px;
}

.cart-summary h2 {
    margin-bottom: 24px;
}

.summary-row,
.summary-total {
    display: flex;
    justify-content: space-between;
    gap: 15px;
}

.summary-row {
    margin-bottom: 14px;
    color: #76675f;
    font-size: 0.9rem;
}

.summary-row strong {
    color: #3a271f;
}

.summary-divider {
    height: 1px;
    margin: 20px 0;
    background: #e8ddd5;
}

.summary-total {
    align-items: center;
    margin-bottom: 24px;
    color: #4e3528;
    font-weight: 700;
}

.summary-total strong {
    color: #2a1a13;
    font-size: 1.35rem;
}

.checkout-button {
    width: 100%;
    text-align: center;
    text-decoration: none;
}

.checkout-button.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.checkout-disabled-message {
    margin-bottom: 14px;
    padding: 12px 13px;
    border-radius: 10px;
    background: #fff2ef;
    color: #8f453b;
    font-size: 0.78rem;
    line-height: 1.5;
}

.summary-note {
    margin: 15px 0 0;
    color: #8a7b72;
    font-size: 0.74rem;
    line-height: 1.55;
    text-align: center;
}


/* =========================================================
   EMPTY CART
========================================================= */

.empty-cart {
    max-width: 680px;
    margin: 0 auto;
    padding: 75px 25px;
    background: #fff;
    border-radius: 22px;
    box-shadow: 0 12px 35px rgba(42, 26, 19, 0.07);
    text-align: center;
}

.empty-cart-icon {
    display: flex;
    width: 82px;
    height: 82px;
    align-items: center;
    justify-content: center;
    margin: 0 auto 22px;
    border-radius: 50%;
    background: #f5eee8;
    font-size: 2rem;
}

.empty-cart h2 {
    margin: 10px 0;
    color: #2a1a13;
}

.empty-cart p {
    max-width: 470px;
    margin: 0 auto 28px;
    color: #776860;
    line-height: 1.7;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1000px) {

    .cart-layout {
        grid-template-columns: 1fr;
    }

    .cart-summary {
        position: static;
    }
}

@media (max-width: 720px) {

    .cart-items-panel,
    .cart-summary {
        padding: 20px;
        border-radius: 16px;
    }

    .cart-item {
        grid-template-columns: 78px minmax(0, 1fr);
        gap: 14px;
    }

    .cart-item-image {
        width: 78px;
        height: 78px;
    }

    .cart-item-subtotal {
        grid-column: 2;
        min-width: 0;
        text-align: left;
    }

    .cart-item-bottom {
        align-items: flex-start;
        flex-direction: column;
    }

    .cart-quantity-form {
        width: 100%;
        justify-content: space-between;
    }

    .cart-item-top {
        display: block;
    }

    .cart-item-price {
        display: block;
        margin-top: 8px;
    }
}

@media (max-width: 480px) {

    .cart-panel-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .cart-quantity-form {
        align-items: flex-start;
        flex-direction: column;
    }

    .cart-item {
        padding: 20px 0;
    }

    .cart-item-details h3 {
        font-size: 1rem;
    }
}
</style>


<script>
document.addEventListener("DOMContentLoaded", function () {

    const quantityInputs =
        document.querySelectorAll(".cart-qty-input");

    quantityInputs.forEach(function (input) {

        const form = input.closest("form");
        const minusButton =
            form.querySelector(".qty-minus");
        const plusButton =
            form.querySelector(".qty-plus");

        const stock =
            Number(input.dataset.stock || 0);

        function refreshButtons() {

            let value = Number(input.value || 1);

            if (stock > 0) {
                value = Math.max(1, Math.min(value, stock));
            } else {
                value = 1;
            }

            input.value = value;

            minusButton.disabled = value <= 1;
            plusButton.disabled =
                stock <= 0 || value >= stock;
        }

        minusButton.addEventListener(
            "click",
            function () {

                let value =
                    Number(input.value || 1);

                if (value > 1) {
                    input.value = value - 1;
                }

                refreshButtons();
            }
        );

        plusButton.addEventListener(
            "click",
            function () {

                let value =
                    Number(input.value || 1);

                if (value >= stock) {
                    alert(
                        "Only " +
                        stock +
                        " unit(s) are available for this product."
                    );

                    refreshButtons();
                    return;
                }

                input.value = value + 1;

                refreshButtons();
            }
        );

        input.addEventListener(
            "input",
            function () {

                let value =
                    Number(input.value || 1);

                if (stock > 0 && value > stock) {
                    value = stock;
                }

                if (value < 1 || Number.isNaN(value)) {
                    value = 1;
                }

                input.value = value;

                refreshButtons();
            }
        );

        refreshButtons();
    });

});
</script>


<?php
include __DIR__ . "/includes/footer.php";
?>
