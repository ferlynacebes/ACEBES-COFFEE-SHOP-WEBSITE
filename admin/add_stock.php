<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

$productId = (int)($_GET["id"] ?? $_POST["product_id"] ?? 0);
if ($productId <= 0) {
    header("Location: products.php?error=invalid_product");
    exit;
}

$stmt = $conn->prepare("SELECT id, name, price, stock, status, image FROM products WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: products.php?error=invalid_product");
    exit;
}

$error = "";

if ((int)$product["stock"] !== 0) {
    $error = "Restocking is only allowed when the current stock is 0.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $error === "") {
    $quantity = (int)($_POST["quantity"] ?? 0);

    if ($quantity < 1 || $quantity > 5) {
        $error = "Restock quantity must be between 1 and 5 units.";
    } else {
        $conn->begin_transaction();
        try {
            $update = $conn->prepare("UPDATE products SET stock = stock + ?, status = 'Available', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND stock = 0");
            $update->bind_param("ii", $quantity, $productId);
            if (!$update->execute() || $update->affected_rows !== 1) {
                throw new Exception("Stock update failed.");
            }
            $update->close();
            $conn->commit();
            header("Location: products.php?stock_added=1");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $error = "Unable to add stock. Please try again.";
        }
    }
}

$adminName = $_SESSION["user_name"] ?? "Administrator";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<title>Add Stock | Acebes Coffee Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Montserrat',Arial,sans-serif;background:#f6f1eb;color:#2a1a13}.main{margin-left:250px;width:calc(100% - 250px);padding:35px;min-height:100vh}.stock-card{max-width:650px;background:#fff;border:1px solid #eadfd5;border-radius:20px;padding:30px;box-shadow:0 12px 30px rgba(42,26,19,.05)}.back{display:inline-block;margin-bottom:20px;color:#6f5a4d;font-size:13px;font-weight:700}.title{font-size:30px;margin-bottom:6px}.subtitle{font-size:13px;color:#806f64;margin-bottom:25px}.product-preview{display:flex;gap:16px;align-items:center;padding:15px;background:#fbf8f5;border-radius:14px;margin-bottom:25px}.product-preview img,.preview-icon{width:65px;height:65px;border-radius:12px;object-fit:cover;background:#f1e8df;display:flex;align-items:center;justify-content:center}.product-preview strong{display:block;font-size:16px}.product-preview span{display:block;margin-top:4px;color:#806f64;font-size:12px}.current-stock{margin-top:3px;color:#4b2e20!important;font-weight:700}.form-group{margin-bottom:18px}label{display:block;font-size:12px;font-weight:700;margin-bottom:7px;color:#5d493e}input{width:100%;border:1px solid #dfd2c7;border-radius:10px;padding:13px;font-family:inherit;font-size:15px;outline:none}input:focus{border-color:#c49a6c}.hint{font-size:11px;color:#806f64;margin-top:7px}.submit{width:100%;border:0;background:#4b2e20;color:#fff;padding:14px;border-radius:10px;font-family:inherit;font-weight:700;cursor:pointer}.submit:hover{background:#2a1a13}.alert{padding:12px 14px;border-radius:10px;background:#f8e7e5;color:#a13c32;font-size:12px;font-weight:600;margin-bottom:18px}@media(max-width:700px){.main{margin-left:0;width:100%;padding:20px}}
</style>
</head>
<body>
<div class="admin-layout">
<?php include __DIR__ . "/sidebar.php"; ?>
<main class="main">
<a href="products.php" class="back">← Back to Products</a>
<section class="stock-card">
<h1 class="title">Add Stock</h1>
<p class="subtitle">Restocking is available only when this product reaches 0 stock. You can add 1 to 5 units.</p>
<?php if ($error !== ""): ?><div class="alert"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<div class="product-preview">
<?php if (!empty($product["image"])): ?><img src="../assets/images/<?= htmlspecialchars($product["image"],ENT_QUOTES,'UTF-8') ?>" alt="<?= htmlspecialchars($product["name"],ENT_QUOTES,'UTF-8') ?>"><?php else: ?><div class="preview-icon">☕</div><?php endif; ?>
<div><strong><?= htmlspecialchars($product["name"],ENT_QUOTES,'UTF-8') ?></strong><span>₱<?= number_format((float)$product["price"],2) ?></span><span class="current-stock">Current stock: <?= (int)$product["stock"] ?></span></div>
</div>
<form method="POST">
<input type="hidden" name="product_id" value="<?= (int)$productId ?>">
<div class="form-group"><label for="quantity">Restock Quantity</label><input id="quantity" type="number" name="quantity" min="1" max="5" step="1" value="5" required><div class="hint">You can add 1, 2, 3, 4, or 5 units. Maximum restock is 5 units, and restocking is only allowed when current stock is 0.</div></div>
<?php if ((int)$product["stock"] === 0): ?>
<button type="submit" class="submit">Add Stock</button>
<?php else: ?>
<button type="button" class="submit" disabled style="opacity:.55;cursor:not-allowed;">Restock Unavailable</button>
<?php endif; ?>
</form>
</section>
</main>
</div>
</body>
</html>
