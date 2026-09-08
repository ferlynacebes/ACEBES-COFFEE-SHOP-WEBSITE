<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_role"] ?? "") !== "admin") {
    header("Location: ../login.php");
    exit;
}

$adminName = $_SESSION["user_name"] ?? "Administrator";

/* Delete product */
if (isset($_GET["delete"])) {
    $id = (int) $_GET["delete"];
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: products.php?deleted=1");
        exit;
    }
}

/* Add product - stock is NOT entered by the admin.
   Every new product starts with 5 units. */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add") {
    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category = trim($_POST["category"] ?? "Coffee");
    $price = (float) ($_POST["price"] ?? 0);
    $stock = 5;
    $status = "Available";

    if ($name === "" || $price < 0) {
        header("Location: products.php?error=invalid");
        exit;
    }

    $imageName = "";
    if (!empty($_FILES["image"]["name"])) {
        $uploadDir = "../assets/images/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $originalName = basename($_FILES["image"]["name"]);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ["jpg", "jpeg", "png", "webp"];

        if (in_array($extension, $allowed, true) && is_uploaded_file($_FILES["image"]["tmp_name"])) {
            $safeName = preg_replace("/[^A-Za-z0-9._-]/", "_", $originalName);
            $imageName = time() . "_" . $safeName;
            move_uploaded_file($_FILES["image"]["tmp_name"], $uploadDir . $imageName);
        }
    }

    if ($imageName !== "") {
        $stmt = $conn->prepare("INSERT INTO products (name, description, category, price, stock, image, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdiss", $name, $description, $category, $price, $stock, $imageName, $status);
    } else {
        $stmt = $conn->prepare("INSERT INTO products (name, description, category, price, stock, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdis", $name, $description, $category, $price, $stock, $status);
    }

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: products.php?added=1");
        exit;
    }

    $stmt->close();
    header("Location: products.php?error=add_failed");
    exit;
}

$search = trim($_GET["search"] ?? "");

if ($search !== "") {
    $searchValue = "%" . $search . "%";
    $stmt = $conn->prepare("SELECT id,name,description,category,price,stock,image,status,created_at FROM products WHERE name LIKE ? OR category LIKE ? ORDER BY id DESC");
    $stmt->bind_param("ss", $searchValue, $searchValue);
    $stmt->execute();
    $products = $stmt->get_result();
} else {
    $products = $conn->query("SELECT id,name,description,category,price,stock,image,status,created_at FROM products ORDER BY id DESC");
}

$totalProducts = 0;
$availableProducts = 0;
$lowStock = 0;
$countResult = $conn->query("SELECT COUNT(*) AS total, SUM(LOWER(TRIM(status)) = 'available') AS available, SUM(stock <= 5) AS low_stock FROM products");
if ($countResult) {
    $counts = $countResult->fetch_assoc();
    $totalProducts = (int) ($counts["total"] ?? 0);
    $availableProducts = (int) ($counts["available"] ?? 0);
    $lowStock = (int) ($counts["low_stock"] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<title>Products | Acebes Coffee Admin</title>
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

        /* =================================================
           LAYOUT
        ================================================= */

        .admin-layout {
            min-height: 100vh;
            display: flex;
        }

        /* =================================================
           MAIN
        ================================================= */

        .main {
            margin-left: 250px;
            width: calc(100% - 250px);
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

        .add-btn {
            border: 0;
            background: #4b2e20;
            color: white;
            padding: 13px 20px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
        }

        .add-btn:hover {
            background: #2a1a13;
            transform: translateY(-1px);
        }

        /* =================================================
           STATS
        ================================================= */

        .stats {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border: 1px solid #eadfd5;
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

        /* =================================================
           CONTENT CARD
        ================================================= */

        .content-card {
            background: white;
            border: 1px solid #eadfd5;
            border-radius: 18px;
            overflow: hidden;
        }

        .toolbar {
            padding: 18px;
            border-bottom: 1px solid #eee4dc;
            display: flex;
            justify-content: space-between;
            gap: 15px;
        }

        .search-box {
            display: flex;
            gap: 8px;
            width: min(430px, 100%);
        }

        .search-box input {
            flex: 1;
            border: 1px solid #dfd2c7;
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

        /* =================================================
           TABLE
        ================================================= */

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
            border-top: 1px solid #f0e7df;
            font-size: 14px;
        }

        .product-cell {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .product-image {
            width: 55px;
            height: 55px;
            border-radius: 11px;
            object-fit: cover;
            background: #f1e8df;
            border: 1px solid #e6d9ce;
        }

        .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .product-name {
            font-weight: 700;
        }

        .product-description {
            color: #927f73;
            font-size: 12px;
            margin-top: 3px;
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .category {
            color: #6f5a4d;
        }

        .price {
            font-weight: 700;
        }

        .stock {
            font-weight: 700;
        }

        .stock.low {
            color: #b36a35;
        }

        .stock.out {
            color: #a13c32;
        }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .status.available {
            background: #e7f3e9;
            color: #387346;
        }

        .status.unavailable {
            background: #eee8e4;
            color: #76685f;
        }

        .actions {
            display: flex;
            gap: 7px;
        }

        .action-btn {
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            transition: .2s;
        }

        .edit {
            background: #f0e7df;
            color: #4b2e20;
        }

        .edit:hover {
            background: #e4d5c8;
        }

        .delete {
            background: #f8e7e5;
            color: #a13c32;
        }

        .delete:hover {
            background: #f1d5d1;
        }

        /* =================================================
           MODAL
        ================================================= */

        .modal {
            position: fixed;
            inset: 0;
            background: rgba(30, 18, 12, .62);
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
            background: #fff;
            width: min(620px, 100%);
            max-height: 92vh;
            overflow-y: auto;
            border-radius: 20px;
            padding: 27px;
            box-shadow: 0 25px 70px rgba(0,0,0,.25);
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #5d493e;
        }

        input,
        textarea,
        select {
            width: 100%;
            border: 1px solid #dfd2c7;
            border-radius: 9px;
            padding: 11px 12px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #c49a6c;
        }

        .current-image {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 10px;
            margin-top: 8px;
        }

        .submit-btn {
            width: 100%;
            border: 0;
            background: #4b2e20;
            color: white;
            padding: 13px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            margin-top: 5px;
        }

        .submit-btn:hover {
            background: #2a1a13;
        }

        /* =================================================
           ALERT
        ================================================= */

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

        .alert.error {
            background: #f8e7e5;
            color: #a13c32;
        }

        /* =================================================
           EMPTY
        ================================================= */

        .empty {
            text-align: center;
            padding: 60px 20px;
            color: #8c7b70;
        }

        .empty-icon {
            font-size: 40px;
            margin-bottom: 12px;
        }

        .empty h3 {
            color: #4b2e20;
            font-family: inherit;
            margin-bottom: 5px;
        }

        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 950px) {

            .sidebar {
                width: 210px;
            }

            .main {
                margin-left: 210px;
                width: calc(100% - 210px);
                padding: 25px;
            }

            .stats {
                grid-template-columns: 1fr 1fr;
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

            .brand {
                justify-content: flex-start;
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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }
        }

    
/* Shared-sidebar-safe responsive layout */
@media (max-width:700px){
    .main{margin-left:0!important;width:100%!important;padding:20px!important}
    .page-header{align-items:flex-start!important;flex-direction:column!important}
    .stats{grid-template-columns:1fr!important}
    .form-grid{grid-template-columns:1fr!important}
    .form-group.full{grid-column:auto!important}
}
.stock-add{background:#e7f3e9;color:#387346}.stock-add:hover{background:#d7eadb}.stock-note{display:block;margin-top:8px;color:#806f64;font-size:11px;line-height:1.5}
</style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . "/sidebar.php"; ?>

    <main class="main">
        <div class="page-header">
            <div>
                <h2>Products</h2>
                <p>Manage products and inventory. Stock is handled separately.</p>
            </div>
            <button type="button" class="add-btn" onclick="openAddModal()">+ Add Product</button>
        </div>

        <?php if (isset($_GET["added"])): ?>
            <div class="alert success">Product added successfully. Initial stock is 5.</div>
        <?php endif; ?>
        <?php if (isset($_GET["stock_added"])): ?>
            <div class="alert success">Stock added successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET["deleted"])): ?>
            <div class="alert success">Product deleted successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET["error"])): ?>
            <div class="alert error">
                <?php
                $err = $_GET["error"] ?? "";
                echo $err === "add_failed" ? "Product could not be added. Please try again." : "Please check the product information and try again.";
                ?>
            </div>
        <?php endif; ?>

        <section class="stats">
            <div class="stat-card"><span>Total Products</span><strong><?= $totalProducts ?></strong></div>
            <div class="stat-card"><span>Available Products</span><strong><?= $availableProducts ?></strong></div>
            <div class="stat-card"><span>Low Stock</span><strong><?= $lowStock ?></strong></div>
        </section>

        <section class="content-card">
            <div class="toolbar">
                <form class="search-box" method="GET">
                    <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit">Search</button>
                </form>
            </div>

            <div class="table-wrap">
            <?php if ($products && $products->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <?php
                        $stockValue = (int)$product["stock"];
                        $stockClass = $stockValue === 0 ? "out" : ($stockValue <= 5 ? "low" : "");
                        $image = trim($product["image"] ?? "");
                        $isAvailable = strcasecmp(trim((string)$product["status"]), "Available") === 0 && $stockValue > 0;
                        ?>
                        <tr>
                            <td>
                                <div class="product-cell">
                                    <?php if ($image !== ""): ?>
                                        <img src="../assets/images/<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($product["name"], ENT_QUOTES, 'UTF-8') ?>" class="product-image" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                        <div class="product-image no-image" style="display:none;">☕</div>
                                    <?php else: ?>
                                        <div class="product-image no-image">☕</div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="product-name"><?= htmlspecialchars($product["name"], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="product-description"><?= htmlspecialchars($product["description"] ?: "No description", ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="category"><?= htmlspecialchars($product["category"] ?: "Coffee", ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="price">₱<?= number_format((float)$product["price"], 2) ?></span></td>
                            <td><span class="stock <?= $stockClass ?>"><?= $stockValue ?></span></td>
                            <td>
                                <?php if ($isAvailable): ?>
                                    <span class="status available">Available</span>
                                <?php else: ?>
                                    <span class="status unavailable">Unavailable</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <?php if ($stockValue === 0): ?>
                                    <a href="add_stock.php?id=<?= (int)$product["id"] ?>" class="action-btn stock-add">+ Stock</a>
                                    <?php endif; ?>
                                    <a href="products.php?delete=<?= (int)$product["id"] ?>" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty"><div class="empty-icon">☕</div><h3>No products found</h3><p>Add your first coffee product to get started.</p></div>
            <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<div class="modal" id="productModal">
    <div class="modal-box">
        <div class="modal-header"><h3>Add Product</h3><button type="button" class="close-btn" onclick="closeModal()">×</button></div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-group full"><label>Product Name</label><input type="text" name="name" required placeholder="e.g. Espresso"></div>
                <div class="form-group full"><label>Description</label><textarea name="description" placeholder="Describe the product..."></textarea></div>
                <div class="form-group"><label>Category</label><select name="category"><option>Coffee</option><option>Cold Coffee</option><option>Non-Coffee</option><option>Pastry</option><option>Other</option></select></div>
                <div class="form-group"><label>Price</label><input type="number" name="price" min="0" step="0.01" required placeholder="0.00"></div>
                <div class="form-group full">
                    <label>Product Image</label>
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
                    <small class="stock-note">Initial stock: <strong>5</strong> units. When stock reaches 0, use “+ Stock” to add 1 to 5 units.</small>
                </div>
            </div>
            <button type="submit" class="submit-btn">Add Product</button>
        </form>
    </div>
</div>
<script>
function openAddModal(){document.getElementById('productModal').classList.add('show');}
function closeModal(){document.getElementById('productModal').classList.remove('show');}
document.getElementById('productModal').addEventListener('click',function(e){if(e.target===this)closeModal();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeModal();});
</script>
</body>
</html>
