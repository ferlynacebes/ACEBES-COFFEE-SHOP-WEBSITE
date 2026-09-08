<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/db.php";

/* =========================================================
   AJAX: ADD TO DATABASE CART
========================================================= */
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "add_to_cart"
) {
    header("Content-Type: application/json; charset=UTF-8");

    if (!isset($_SESSION["user_id"])) {
        echo json_encode([
            "success" => false,
            "login_required" => true,
            "message" => "Please log in before adding items to your cart."
        ]);
        exit;
    }

    $userId = (int) $_SESSION["user_id"];
    $productId = (int) ($_POST["product_id"] ?? 0);
    $quantity = (int) ($_POST["quantity"] ?? 1);

    if ($productId <= 0 || $quantity <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid product or quantity."
        ]);
        exit;
    }

    try {
        $conn->begin_transaction();

        /* Lock the product while checking its real stock. */
        $productStmt = $conn->prepare("
            SELECT id, name, price, stock, status
            FROM products
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");

        if (!$productStmt) {
            throw new Exception("Unable to verify the product.");
        }

        $productStmt->bind_param("i", $productId);
        $productStmt->execute();

        $product = $productStmt
            ->get_result()
            ->fetch_assoc();

        $productStmt->close();

        if (!$product) {
            throw new Exception("Product not found.");
        }

        $stock = (int) $product["stock"];
        $status = (string) $product["status"];

        if (
            $stock <= 0 ||
            !in_array($status, ["Available", "Active"], true)
        ) {
            throw new Exception(
                $product["name"] . " is currently unavailable."
            );
        }

        if ($quantity > $stock) {
            throw new Exception(
                "Only " . $stock . " unit(s) of " .
                $product["name"] . " are available."
            );
        }

        /* Check the customer's existing cart quantity. */
        $cartStmt = $conn->prepare("
            SELECT id, quantity
            FROM cart
            WHERE user_id = ?
              AND product_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        if (!$cartStmt) {
            throw new Exception("Unable to check your cart.");
        }

        $cartStmt->bind_param(
            "ii",
            $userId,
            $productId
        );

        $cartStmt->execute();

        $existingCart = $cartStmt
            ->get_result()
            ->fetch_assoc();

        $cartStmt->close();

        $existingQuantity = $existingCart
            ? (int) $existingCart["quantity"]
            : 0;

        $newQuantity =
            $existingQuantity + $quantity;

        /* Never allow cart quantity above real stock. */
        if ($newQuantity > $stock) {
            throw new Exception(
                "You can only have up to " .
                $stock . " unit(s) of " .
                $product["name"] . " in your cart."
            );
        }

        if ($existingCart) {

            $cartId = (int) $existingCart["id"];

            $updateStmt = $conn->prepare("
                UPDATE cart
                SET quantity = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                  AND user_id = ?
            ");

            if (!$updateStmt) {
                throw new Exception("Unable to update your cart.");
            }

            $updateStmt->bind_param(
                "iii",
                $newQuantity,
                $cartId,
                $userId
            );

            if (!$updateStmt->execute()) {
                $updateStmt->close();
                throw new Exception("Unable to update your cart.");
            }

            $updateStmt->close();

        } else {

            $insertStmt = $conn->prepare("
                INSERT INTO cart
                    (user_id, product_id, quantity)
                VALUES
                    (?, ?, ?)
            ");

            if (!$insertStmt) {
                throw new Exception("Unable to add the item to your cart.");
            }

            $insertStmt->bind_param(
                "iii",
                $userId,
                $productId,
                $quantity
            );

            if (!$insertStmt->execute()) {
                $insertStmt->close();
                throw new Exception("Unable to add the item to your cart.");
            }

            $insertStmt->close();
        }

        /* Refresh total cart quantity. */
        $countStmt = $conn->prepare("
            SELECT COALESCE(SUM(quantity), 0) AS cart_count
            FROM cart
            WHERE user_id = ?
        ");

        if (!$countStmt) {
            throw new Exception("Unable to refresh cart count.");
        }

        $countStmt->bind_param("i", $userId);
        $countStmt->execute();

        $cartCountRow = $countStmt
            ->get_result()
            ->fetch_assoc();

        $countStmt->close();

        $cartCount =
            (int) ($cartCountRow["cart_count"] ?? 0);

        $conn->commit();

        echo json_encode([
            "success" => true,
            "product_name" => $product["name"],
            "quantity_added" => $quantity,
            "cart_quantity" => $newQuantity,
            "cart_count" => $cartCount,
            "stock" => $stock,
            "remaining" => max(
                0,
                $stock - $newQuantity
            ),
            "message" =>
                $product["name"] .
                " added to your cart."
        ]);

        exit;

    } catch (Throwable $e) {

        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }

        error_log(
            "Acebes menu add-to-cart error: " .
            $e->getMessage()
        );

        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);

        exit;
    }
}

/* =========================================================
   LOAD AVAILABLE PRODUCTS FROM DATABASE
========================================================= */
$products = [];

$productResult = $conn->query("
    SELECT
        id,
        name,
        description,
        category,
        price,
        stock,
        image,
        status
    FROM products
    WHERE stock > 0
      AND status IN ('Available', 'Active')
    ORDER BY id ASC
");

if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
}

/* =========================================================
   LOAD CURRENT CUSTOMER CART
========================================================= */
$userId = isset($_SESSION["user_id"])
    ? (int) $_SESSION["user_id"]
    : 0;

$cartQuantities = [];
$cartCount = 0;

if ($userId > 0) {

    $cartStmt = $conn->prepare("
        SELECT product_id, quantity
        FROM cart
        WHERE user_id = ?
    ");

    if ($cartStmt) {

        $cartStmt->bind_param(
            "i",
            $userId
        );

        $cartStmt->execute();

        $cartResult =
            $cartStmt->get_result();

        while ($cartRow =
            $cartResult->fetch_assoc()
        ) {
            $productId =
                (int) $cartRow["product_id"];

            $quantity =
                (int) $cartRow["quantity"];

            $cartQuantities[$productId] =
                $quantity;

            $cartCount += $quantity;
        }

        $cartStmt->close();
    }
}

/* =========================================================
   HELPERS
========================================================= */
function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function product_badge(string $name): string
{
    $badges = [
        "Espresso" => "CLASSIC",
        "Cappuccino" => "FAVORITE",
        "Caramel Latte" => "BEST SELLER",
        "Mocha" => "CHOCOLATE FAVORITE"
    ];

    return $badges[$name] ?? "FRESHLY MADE";
}

function product_image(string $image): string
{
    $image = trim($image);

    if ($image === "") {
        return "assets/images/espresso.jpg";
    }

    if (
        str_contains($image, "/") ||
        str_contains($image, "\\")
    ) {
        return $image;
    }

    return "assets/images/" . $image;
}

include __DIR__ . "/includes/header.php";
?>

<style>
    .menu-stock-info {
        min-height: 18px;
    }

    .add-to-cart-btn:disabled {
        opacity: .55;
        cursor: not-allowed;
        transform: none !important;
    }

    .quantity-btn:disabled {
        opacity: .45;
        cursor: not-allowed;
    }
</style>

<!-- =========================================================
     MENU PAGE HERO
========================================================= -->

<section class="page-hero menu-page-hero">
    <div class="container">

        <span class="section-label">
            OUR MENU
        </span>

        <h1>
            Our Menu
        </h1>

        <p>
            Handcrafted coffee made with care,
            passion, and quality ingredients.
        </p>

    </div>
</section>


<!-- =========================================================
     ORDERING MENU
========================================================= -->

<section class="section menu-section">

    <div class="container">

        <!-- SECTION HEADER -->

        <div class="section-header">

            <span class="section-label">
                ORDER YOUR FAVORITE
            </span>

            <h2 class="section-title">
                Choose Your Coffee
            </h2>

            <p class="section-description">
                Pick your favorite drink, choose your quantity,
                and add it to your cart.
            </p>

        </div>


        <!-- =================================================
             CART STATUS
        ================================================== -->

        <div class="menu-cart-status">

            <div class="menu-cart-status-text">

                <span
                    class="menu-cart-icon"
                    aria-hidden="true"
                >
                    🛒
                </span>

                <div>

                    <strong>
                        Your Cart
                    </strong>

                    <span>
                        <span id="menu-cart-count">0</span>
                        item(s)
                    </span>

                </div>

            </div>

            <a
                href="cart.php"
                class="btn btn-secondary menu-cart-button"
            >
                VIEW CART
            </a>

        </div>


        <!-- =================================================
             MENU GRID
        ================================================== -->

        <div class="menu-grid">

            <?php if (empty($products)): ?>

                <div
                    style="
                        grid-column:1 / -1;
                        text-align:center;
                        padding:60px 25px;
                        background:#fff;
                        border-radius:20px;
                        box-shadow:0 10px 35px rgba(0,0,0,.08);
                    "
                >
                    <div style="font-size:48px; margin-bottom:15px;">
                        ☕
                    </div>

                    <h3 style="margin-bottom:10px;">
                        No Coffee Available
                    </h3>

                    <p>
                        Our coffee menu is currently unavailable.
                        Please check back soon.
                    </p>
                </div>

            <?php else: ?>

                <?php foreach ($products as $product): ?>

                    <?php
                        $productId =
                            (int) $product["id"];

                        $stock =
                            (int) $product["stock"];

                        $inCart =
                            (int) (
                                $cartQuantities[$productId]
                                ?? 0
                            );

                        $remaining =
                            max(0, $stock - $inCart);

                        $outOfStock =
                            $remaining <= 0;
                    ?>

                    <article
                        class="menu-item order-menu-item"
                        data-product-id="<?= $productId ?>"
                        data-product="<?= e($product["name"]) ?>"
                        data-price="<?= e($product["price"]) ?>"
                        data-stock="<?= $stock ?>"
                        data-cart-quantity="<?= $inCart ?>"
                    >

                        <div class="menu-item-image">

                            <img
                                src="<?= e(product_image($product["image"])) ?>"
                                alt="<?= e($product["name"]) ?>"
                                loading="lazy"
                            >

                            <span class="menu-item-badge">
                                <?= e(product_badge($product["name"])) ?>
                            </span>

                        </div>

                        <div class="menu-item-content">

                            <div class="menu-item-header">

                                <div>

                                    <span class="menu-item-category">
                                        <?= e(
                                            $product["category"]
                                            ?: "COFFEE"
                                        ) ?>
                                    </span>

                                    <h3>
                                        <?= e($product["name"]) ?>
                                    </h3>

                                </div>

                                <span class="menu-item-price">
                                    ₱<?= number_format(
                                        (float) $product["price"],
                                        2
                                    ) ?>
                                </span>

                            </div>

                            <p>
                                <?= e(
                                    $product["description"]
                                    ?: "Freshly prepared coffee made with care."
                                ) ?>
                            </p>

                            <div
                                class="menu-stock-info"
                                style="
                                    margin:12px 0;
                                    font-size:12px;
                                    color:#7a6255;
                                    font-weight:600;
                                "
                            >

                                <?php if ($outOfStock): ?>

                                    <span style="color:#a33b32;">
                                        OUT OF STOCK
                                    </span>

                                <?php elseif ($inCart > 0): ?>

                                    <?= $remaining ?> available
                                    • <?= $inCart ?> already in cart

                                <?php else: ?>

                                    <?= $stock ?> available

                                <?php endif; ?>

                            </div>

                            <div class="menu-order-controls">

                                <div class="quantity-control">

                                    <button
                                        type="button"
                                        class="quantity-btn quantity-minus"
                                        aria-label="Decrease <?= e($product["name"]) ?> quantity"
                                        <?= $outOfStock ? "disabled" : "" ?>
                                    >
                                        −
                                    </button>

                                    <span class="quantity-value">
                                        <?= $outOfStock ? 0 : 1 ?>
                                    </span>

                                    <button
                                        type="button"
                                        class="quantity-btn quantity-plus"
                                        aria-label="Increase <?= e($product["name"]) ?> quantity"
                                        <?= $outOfStock ? "disabled" : "" ?>
                                    >
                                        +
                                    </button>

                                </div>

                                <button
                                    type="button"
                                    class="btn btn-primary add-to-cart-btn"
                                    <?= $outOfStock ? "disabled" : "" ?>
                                >
                                    <?= $outOfStock
                                        ? "OUT OF STOCK"
                                        : "ADD TO CART" ?>
                                </button>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</section>

<!-- =========================================================
     CART NOTIFICATION
========================================================= -->

<div
    class="menu-cart-notification"
    id="menu-cart-notification"
    role="status"
    aria-live="polite"
>

    <span
        class="menu-notification-icon"
        aria-hidden="true"
    >
        ✓
    </span>

    <div>

        <strong>
            Added to cart!
        </strong>

        <span id="menu-notification-text">
            Your drink has been added.
        </span>

    </div>

</div>


<!-- =========================================================
     COFFEE EXPERIENCE
========================================================= -->

<section class="section why-section">

    <div class="container">

        <div class="section-header">

            <span class="section-label">
                THE ACEBES EXPERIENCE
            </span>

            <h2 class="section-title">
                Every Cup Tells a Story
            </h2>

            <p class="section-description">
                From carefully selected beans to a warm
                and welcoming space, we want every visit
                to be special.
            </p>

        </div>


        <div class="features-grid">


            <!-- HANDCRAFTED -->

            <article class="feature-card">

                <div class="feature-icon">

                    <span aria-hidden="true">
                        ☕
                    </span>

                </div>

                <h3>
                    HANDCRAFTED
                </h3>

                <p>
                    Every cup is prepared with care
                    to give you a satisfying coffee
                    experience.
                </p>

            </article>


            <!-- QUALITY -->

            <article class="feature-card">

                <div class="feature-icon">

                    <span aria-hidden="true">
                        ✦
                    </span>

                </div>

                <h3>
                    QUALITY
                </h3>

                <p>
                    We focus on carefully selected
                    ingredients for every drink.
                </p>

            </article>


            <!-- COMFORT -->

            <article class="feature-card">

                <div class="feature-icon">

                    <span aria-hidden="true">
                        ♡
                    </span>

                </div>

                <h3>
                    COMFORT
                </h3>

                <p>
                    Enjoy your coffee in a warm,
                    welcoming environment.
                </p>

            </article>


            <!-- WITH HEART -->

            <article class="feature-card">

                <div class="feature-icon">

                    <span aria-hidden="true">
                        ✓
                    </span>

                </div>

                <h3>
                    WITH HEART
                </h3>

                <p>
                    We serve every customer with
                    genuine care and hospitality.
                </p>

            </article>


        </div>

    </div>

</section>


<!-- =========================================================
     ORDER CTA
========================================================= -->

<section class="section menu-cta-section">

    <div class="container">

        <div class="menu-cta-content">

            <span class="section-label">
                YOUR NEXT COFFEE
            </span>

            <h2 class="section-title">
                Find Your Favorite Cup
            </h2>

            <p class="section-description">
                Choose your favorite drink and make
                your next coffee moment special.
            </p>

            <a
                href="#"
                class="btn btn-primary menu-scroll-top"
            >
                BROWSE MENU
            </a>

        </div>

    </div>

</section>


<!-- =========================================================
     DATABASE CART + STOCK-AWARE ORDERING SCRIPT
========================================================= -->

<script>
document.addEventListener("DOMContentLoaded", function () {

    const menuItems =
        document.querySelectorAll(".order-menu-item");

    const cartCountElement =
        document.getElementById("menu-cart-count");

    const notification =
        document.getElementById("menu-cart-notification");

    const notificationText =
        document.getElementById("menu-notification-text");

    let currentCartCount =
        <?= (int) $cartCount ?>;


    /* =========================================================
       CART COUNT
    ========================================================= */

    function updateCartCount(count) {

        currentCartCount =
            Number(count || 0);

        if (cartCountElement) {
            cartCountElement.textContent =
                currentCartCount;
        }
    }


    /* =========================================================
       NOTIFICATION
    ========================================================= */

    function showNotification(
        message,
        success = true
    ) {

        if (!notification || !notificationText) {
            return;
        }

        notificationText.textContent =
            message;

        notification.classList.add("show");

        notification.style.borderColor =
            success ? "" : "#b84a42";

        clearTimeout(
            notification._hideTimer
        );

        notification._hideTimer =
            setTimeout(function () {
                notification.classList.remove("show");
            }, 2800);
    }


    /* =========================================================
       ADD TO DATABASE CART
    ========================================================= */

    async function addToCart(
        item,
        quantity,
        addButton
    ) {

        const productId =
            Number(item.dataset.productId);

        const productName =
            item.dataset.product || "Product";

        const stock =
            Number(item.dataset.stock || 0);

        const inCart =
            Number(item.dataset.cartQuantity || 0);

        const remaining =
            Math.max(
                0,
                stock - inCart
            );


        if (quantity <= 0) {
            return;
        }

        if (quantity > remaining) {

            showNotification(
                "Only " +
                remaining +
                " more " +
                productName +
                " can be added.",
                false
            );

            return;
        }


        addButton.disabled = true;
        addButton.textContent = "ADDING...";


        const formData =
            new FormData();

        formData.append(
            "action",
            "add_to_cart"
        );

        formData.append(
            "product_id",
            productId
        );

        formData.append(
            "quantity",
            quantity
        );


        try {

            const response =
                await fetch(
                    "menu.php",
                    {
                        method: "POST",
                        body: formData,
                        headers: {
                            "X-Requested-With":
                                "XMLHttpRequest"
                        }
                    }
                );

            const data =
                await response.json();


            if (data.login_required) {

                showNotification(
                    "Please log in to add items to your cart.",
                    false
                );

                setTimeout(function () {
                    window.location.href =
                        "login.php";
                }, 900);

                return;
            }


            if (!data.success) {

                showNotification(
                    data.message ||
                    "Unable to add the item to your cart.",
                    false
                );

                return;
            }


            /*
             * Server values are authoritative.
             */
            item.dataset.cartQuantity =
                data.cart_quantity;

            item.dataset.stock =
                data.stock;


            const quantityValue =
                item.querySelector(
                    ".quantity-value"
                );

            if (quantityValue) {
                quantityValue.textContent =
                    "1";
            }


            const stockInfo =
                item.querySelector(
                    ".menu-stock-info"
                );

            const remainingStock =
                Number(
                    data.remaining || 0
                );


            if (stockInfo) {

                if (remainingStock <= 0) {

                    stockInfo.innerHTML =
                        '<span style="color:#a33b32;">OUT OF STOCK</span>';

                } else {

                    stockInfo.textContent =
                        remainingStock +
                        " available • " +
                        data.cart_quantity +
                        " already in cart";
                }
            }


            updateCartCount(
                data.cart_count
            );


            if (remainingStock <= 0) {

                const plusButton =
                    item.querySelector(
                        ".quantity-plus"
                    );

                const minusButton =
                    item.querySelector(
                        ".quantity-minus"
                    );

                if (plusButton) {
                    plusButton.disabled = true;
                }

                if (minusButton) {
                    minusButton.disabled = true;
                }

                addButton.disabled = true;

                addButton.textContent =
                    "OUT OF STOCK";

            } else {

                addButton.textContent =
                    "ADDED ✓";

                addButton.classList.add(
                    "added"
                );

                showNotification(
                    data.quantity_added +
                    " × " +
                    data.product_name +
                    " added to your cart."
                );

                setTimeout(function () {

                    addButton.textContent =
                        "ADD TO CART";

                    addButton.classList.remove(
                        "added"
                    );

                    addButton.disabled =
                        false;

                }, 1200);
            }

        } catch (error) {

            console.error(
                "Add to cart error:",
                error
            );

            showNotification(
                "Something went wrong. Please try again.",
                false
            );

        } finally {

            if (
                addButton.textContent ===
                "ADDING..."
            ) {
                addButton.textContent =
                    "ADD TO CART";

                addButton.disabled =
                    false;
            }
        }
    }


    /* =========================================================
       PRODUCT CONTROLS
    ========================================================= */

    menuItems.forEach(function (item) {

        const minusButton =
            item.querySelector(
                ".quantity-minus"
            );

        const plusButton =
            item.querySelector(
                ".quantity-plus"
            );

        const quantityValue =
            item.querySelector(
                ".quantity-value"
            );

        const addButton =
            item.querySelector(
                ".add-to-cart-btn"
            );


        let quantity =
            Number(
                quantityValue.textContent
            ) || 1;


        function getRemainingStock() {

            const stock =
                Number(
                    item.dataset.stock || 0
                );

            const inCart =
                Number(
                    item.dataset.cartQuantity || 0
                );

            return Math.max(
                0,
                stock - inCart
            );
        }


        function refreshButtons() {

            const remaining =
                getRemainingStock();

            if (remaining <= 0) {

                quantity = 0;

                quantityValue.textContent =
                    "0";

                minusButton.disabled = true;
                plusButton.disabled = true;
                addButton.disabled = true;

                addButton.textContent =
                    "OUT OF STOCK";

                return;
            }


            if (quantity < 1) {
                quantity = 1;
            }

            if (quantity > remaining) {
                quantity = remaining;
            }


            quantityValue.textContent =
                quantity;

            minusButton.disabled =
                quantity <= 1;

            plusButton.disabled =
                quantity >= remaining;

            addButton.disabled =
                false;
        }


        /* DECREASE */

        minusButton.addEventListener(
            "click",
            function () {

                if (quantity > 1) {
                    quantity--;
                }

                refreshButtons();
            }
        );


        /* INCREASE */

        plusButton.addEventListener(
            "click",
            function () {

                const remaining =
                    getRemainingStock();

                if (quantity < remaining) {

                    quantity++;

                } else {

                    showNotification(
                        "Only " +
                        remaining +
                        " unit(s) are available.",
                        false
                    );
                }

                refreshButtons();
            }
        );


        /* ADD TO CART */

        addButton.addEventListener(
            "click",
            function () {

                const remaining =
                    getRemainingStock();

                if (remaining <= 0) {

                    showNotification(
                        "This product is out of stock.",
                        false
                    );

                    refreshButtons();

                    return;
                }

                if (quantity > remaining) {

                    quantity =
                        remaining;

                    refreshButtons();

                    showNotification(
                        "You cannot add more than the available stock.",
                        false
                    );

                    return;
                }

                addToCart(
                    item,
                    quantity,
                    addButton
                );
            }
        );


        refreshButtons();
    });


    /* =========================================================
       BROWSE MENU BUTTON
    ========================================================= */

    const scrollButton =
        document.querySelector(
            ".menu-scroll-top"
        );

    if (scrollButton) {

        scrollButton.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });
            }
        );
    }


    /* =========================================================
       INITIAL COUNT
    ========================================================= */

    updateCartCount(
        currentCartCount
    );

});
</script>


<!-- =========================================================
     FOOTER
========================================================= -->

<?php
include 'includes/footer.php';
?>