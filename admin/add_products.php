<?php
session_start();

require_once "../config/db.php";

/* =========================================================
   ADMIN ACCESS
========================================================= */

if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$adminName = $_SESSION["user_name"] ?? "Administrator";

/* =========================================================
   PRODUCT STATUS OPTIONS
   The products table uses Available / Unavailable.
========================================================= */

$statusOptions = [
    "Available" => "Available",
    "Unavailable" => "Unavailable"
];

/* =========================================================
   DELETE PRODUCT
========================================================= */

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

/* =========================================================
   ADD / UPDATE PRODUCT
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $price = (float) ($_POST["price"] ?? 0);
    $stock = (int) ($_POST["stock"] ?? 0);
    $postedStatus = trim($_POST["status"] ?? "Available");

    /* Convert the form label to the exact database value. */
    if (strcasecmp($postedStatus, "Available") === 0) {
        $status = $statusOptions["Available"];
    } elseif (strcasecmp($postedStatus, "Unavailable") === 0) {
        $status = $statusOptions["Unavailable"];
    } else {
        header("Location: products.php?error=invalid_status");
        exit;
    }

    if ($name === "" || $price < 0 || $stock < 0) {
        header("Location: products.php?error=invalid");
        exit;
    }

    /* -----------------------------------------------------
       IMAGE
    ----------------------------------------------------- */

    $imageName = "";

    if (!empty($_FILES["image"]["name"])) {

        $uploadDir = "../assets/images/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $originalName = basename($_FILES["image"]["name"]);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $allowedExtensions = [
            "jpg",
            "jpeg",
            "png",
            "webp"
        ];

        if (in_array($extension, $allowedExtensions)) {

            $imageName = time() . "_" . preg_replace(
                "/[^A-Za-z0-9._-]/",
                "_",
                $originalName
            );

            move_uploaded_file(
                $_FILES["image"]["tmp_name"],
                $uploadDir . $imageName
            );
        }
    }

    /* =====================================================
       ADD PRODUCT
    ===================================================== */

    if ($action === "add") {

        if ($imageName !== "") {

            $stmt = $conn->prepare("
                INSERT INTO products
                (name, description, category, price, stock, image, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "sssdiss",
                $name,
                $description,
                $category,
                $price,
                $stock,
                $imageName,
                $status
            );

        } else {

            $stmt = $conn->prepare("
                INSERT INTO products
                (name, description, category, price, stock, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "sssdis",
                $name,
                $description,
                $category,
                $price,
                $stock,
                $status
            );
        }

        $stmt->execute();
        $stmt->close();

        header("Location: products.php?added=1");
        exit;
    }

    /* =====================================================
       UPDATE PRODUCT
    ===================================================== */

    if ($action === "update") {

        $id = (int) ($_POST["id"] ?? 0);

        if ($id <= 0) {
            header("Location: products.php?error=invalid");
            exit;
        }

        if ($imageName !== "") {

            $stmt = $conn->prepare("
                UPDATE products
                SET
                    name = ?,
                    description = ?,
                    category = ?,
                    price = ?,
                    stock = ?,
                    image = ?,
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $stmt->bind_param(
                "sssdissi",
                $name,
                $description,
                $category,
                $price,
                $stock,
                $imageName,
                $status,
                $id
            );

        } else {

            $stmt = $conn->prepare("
                UPDATE products
                SET
                    name = ?,
                    description = ?,
                    category = ?,
                    price = ?,
                    stock = ?,
                    status = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $stmt->bind_param(
                "sssdisi",
                $name,
                $description,
                $category,
                $price,
                $stock,
                $status,
                $id
            );
        }

        if ($stmt->execute()) {
            $stmt->close();

            header("Location: products.php?updated=1");
            exit;
        }

        $stmt->close();

        header("Location: products.php?error=update_failed");
        exit;
    }
}

/* =========================================================
   GET PRODUCT FOR EDIT
========================================================= */

$editProduct = null;

if (isset($_GET["edit"])) {

    $editId = (int) $_GET["edit"];

    if ($editId > 0) {

        $stmt = $conn->prepare("
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
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $editId);
        $stmt->execute();

        $result = $stmt->get_result();
        $editProduct = $result->fetch_assoc();

        $stmt->close();
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
            id,
            name,
            description,
            category,
            price,
            stock,
            image,
            status,
            created_at
        FROM products
        WHERE
            name LIKE ?
            OR category LIKE ?
        ORDER BY id DESC
    ");

    $stmt->bind_param(
        "ss",
        $searchValue,
        $searchValue
    );

    $stmt->execute();

    $products = $stmt->get_result();

} else {

    $products = $conn->query("
        SELECT
            id,
            name,
            description,
            category,
            price,
            stock,
            image,
            status,
            created_at
        FROM products
        ORDER BY id DESC
    ");
}

/* =========================================================
   COUNTS
========================================================= */

$totalProducts = 0;
$availableProducts = 0;
$lowStock = 0;

$countResult = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(LOWER(TRIM(status)) = 'available') AS available,
        SUM(stock <= 5) AS low_stock
    FROM products
");

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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
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
         MAIN CONTENT
    ====================================================== -->

    <main class="main">

        <div class="page-header">

            <div>

                <h2>Products</h2>

                <p>
                    Manage your coffee shop products and inventory.
                </p>

            </div>

            <button
                type="button"
                class="add-btn"
                onclick="openAddModal()"
            >
                + Add Product
            </button>

        </div>


        <!-- =================================================
             ALERTS
        ================================================== -->

        <?php if (isset($_GET["added"])): ?>

            <div class="alert success">
                Product added successfully.
            </div>

        <?php endif; ?>


        <?php if (isset($_GET["updated"])): ?>

            <div class="alert success">
                Product updated successfully.
            </div>

        <?php endif; ?>


        <?php if (isset($_GET["deleted"])): ?>

            <div class="alert success">
                Product deleted successfully.
            </div>

        <?php endif; ?>


        <?php if (isset($_GET["error"])): ?>

            <div class="alert error">
                <?php if (($_GET["error"] ?? "") === "invalid_status"): ?>
                    Invalid product status. Please choose Available or Unavailable.
                <?php elseif (($_GET["error"] ?? "") === "update_failed"): ?>
                    Product update failed. Please try again.
                <?php else: ?>
                    Please check the product information and try again.
                <?php endif; ?>
            </div>

        <?php endif; ?>


        <!-- =================================================
             STATS
        ================================================== -->

        <section class="stats">

            <div class="stat-card">

                <span>Total Products</span>

                <strong>
                    <?= $totalProducts ?>
                </strong>

            </div>


            <div class="stat-card">

                <span>Available Products</span>

                <strong>
                    <?= $availableProducts ?>
                </strong>

            </div>


            <div class="stat-card">

                <span>Low Stock</span>

                <strong>
                    <?= $lowStock ?>
                </strong>

            </div>

        </section>


        <!-- =================================================
             PRODUCT TABLE
        ================================================== -->

        <section class="content-card">

            <div class="toolbar">

                <form
                    class="search-box"
                    method="GET"
                >

                    <input
                        type="text"
                        name="search"
                        placeholder="Search products..."
                        value="<?= htmlspecialchars($search) ?>"
                    >

                    <button type="submit">
                        Search
                    </button>

                </form>

            </div>


            <div class="table-wrap">

                <?php if ($products && $products->num_rows > 0): ?>

                    <table>

                        <thead>

                        <tr>

                            <th>Product</th>

                            <th>Category</th>

                            <th>Price</th>

                            <th>Stock</th>

                            <th>Status</th>

                            <th>Actions</th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php while ($product = $products->fetch_assoc()): ?>

                            <?php

                            $stockClass = "";

                            if ((int)$product["stock"] === 0) {
                                $stockClass = "out";
                            } elseif ((int)$product["stock"] <= 5) {
                                $stockClass = "low";
                            }

                            $image = trim($product["image"] ?? "");

                            ?>

                            <tr>

                                <td>

                                    <div class="product-cell">

                                        <?php if ($image !== ""): ?>

                                            <img
                                                src="../assets/images/<?= htmlspecialchars($image) ?>"
                                                alt="<?= htmlspecialchars($product["name"]) ?>"
                                                class="product-image"
                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                            >

                                            <div
                                                class="product-image no-image"
                                                style="display:none;"
                                            >
                                                ☕
                                            </div>

                                        <?php else: ?>

                                            <div class="product-image no-image">
                                                ☕
                                            </div>

                                        <?php endif; ?>


                                        <div>

                                            <div class="product-name">
                                                <?= htmlspecialchars($product["name"]) ?>
                                            </div>

                                            <div class="product-description">
                                                <?= htmlspecialchars(
                                                    $product["description"] ?: "No description"
                                                ) ?>
                                            </div>

                                        </div>

                                    </div>

                                </td>


                                <td>

                                    <span class="category">
                                        <?= htmlspecialchars(
                                            $product["category"] ?: "Coffee"
                                        ) ?>
                                    </span>

                                </td>


                                <td>

                                    <span class="price">
                                        ₱<?= number_format(
                                            (float)$product["price"],
                                            2
                                        ) ?>
                                    </span>

                                </td>


                                <td>

                                    <span class="stock <?= $stockClass ?>">
                                        <?= (int)$product["stock"] ?>

                                        <?php if ((int)$product["stock"] === 0): ?>

                                            (Out)

                                        <?php elseif ((int)$product["stock"] <= 5): ?>

                                            (Low)

                                        <?php endif; ?>

                                    </span>

                                </td>


                                <td>

                                    <?php if (strcasecmp(trim((string)$product["status"]), "Available") === 0): ?>

                                        <span class="status available">
                                            Available
                                        </span>

                                    <?php else: ?>

                                        <span class="status unavailable">
                                            Unavailable
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <div class="actions">

                                        <a
                                            href="products.php?edit=<?= (int)$product["id"] ?>"
                                            class="action-btn edit"
                                        >
                                            Edit
                                        </a>

                                        <a
                                            href="products.php?delete=<?= (int)$product["id"] ?>"
                                            class="action-btn delete"
                                            onclick="return confirm('Are you sure you want to delete this product?');"
                                        >
                                            Delete
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                        </tbody>

                    </table>

                <?php else: ?>

                    <div class="empty">

                        <div class="empty-icon">
                            ☕
                        </div>

                        <h3>
                            No products found
                        </h3>

                        <p>
                            Add your first coffee product to get started.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>


<!-- =========================================================
     ADD / EDIT MODAL
========================================================= -->

<div
    class="modal <?= $editProduct ? 'show' : '' ?>"
    id="productModal"
>

    <div class="modal-box">

        <div class="modal-header">

            <h3 id="modalTitle">
                <?= $editProduct ? "Edit Product" : "Add Product" ?>
            </h3>

            <button
                type="button"
                class="close-btn"
                onclick="closeModal()"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            enctype="multipart/form-data"
            id="productForm"
        >

            <input
                type="hidden"
                name="action"
                id="formAction"
                value="<?= $editProduct ? 'update' : 'add' ?>"
            >


            <?php if ($editProduct): ?>

                <input
                    type="hidden"
                    name="id"
                    id="productId"
                    value="<?= (int)$editProduct["id"] ?>"
                >

            <?php endif; ?>


            <div class="form-grid">


                <div class="form-group full">

                    <label>
                        Product Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="productName"
                        required
                        placeholder="e.g. Espresso"
                        value="<?= htmlspecialchars(
                            $editProduct["name"] ?? ""
                        ) ?>"
                    >

                </div>


                <div class="form-group full">

                    <label>
                        Description
                    </label>

                    <textarea
                        name="description"
                        id="productDescription"
                        placeholder="Describe the product..."
                    ><?= htmlspecialchars(
                        $editProduct["description"] ?? ""
                    ) ?></textarea>

                </div>


                <div class="form-group">

                    <label>
                        Category
                    </label>

                    <select name="category" id="productCategory">

                        <?php

                        $selectedCategory =
                            $editProduct["category"] ?? "Coffee";

                        ?>

                        <option
                            value="Coffee"
                            <?= $selectedCategory === "Coffee"
                                ? "selected"
                                : "" ?>
                        >
                            Coffee
                        </option>

                        <option
                            value="Cold Coffee"
                            <?= $selectedCategory === "Cold Coffee"
                                ? "selected"
                                : "" ?>
                        >
                            Cold Coffee
                        </option>

                        <option
                            value="Non-Coffee"
                            <?= $selectedCategory === "Non-Coffee"
                                ? "selected"
                                : "" ?>
                        >
                            Non-Coffee
                        </option>

                        <option
                            value="Pastry"
                            <?= $selectedCategory === "Pastry"
                                ? "selected"
                                : "" ?>
                        >
                            Pastry
                        </option>

                        <option
                            value="Other"
                            <?= $selectedCategory === "Other"
                                ? "selected"
                                : "" ?>
                        >
                            Other
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Price
                    </label>

                    <input
                        type="number"
                        name="price"
                        id="productPrice"
                        min="0"
                        step="0.01"
                        required
                        placeholder="0.00"
                        value="<?= htmlspecialchars(
                            $editProduct["price"] ?? ""
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Stock
                    </label>

                    <input
                        type="number"
                        name="stock"
                        id="productStock"
                        min="0"
                        required
                        placeholder="0"
                        value="<?= htmlspecialchars(
                            $editProduct["stock"] ?? "0"
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <?php
                    $selectedStatus = trim(
                        (string) ($editProduct["status"] ?? "Available")
                    );
                    ?>

                    <select name="status" id="productStatus" required>

                        <option
                            value="Available"
                            <?= strcasecmp($selectedStatus, "Available") === 0
                                ? "selected"
                                : "" ?>
                        >
                            Available
                        </option>

                        <option
                            value="Unavailable"
                            <?= strcasecmp($selectedStatus, "Unavailable") === 0
                                ? "selected"
                                : "" ?>
                        >
                            Unavailable
                        </option>

                    </select>

                </div>


                <div class="form-group full">

                    <label>
                        Product Image
                    </label>

                    <input
                        type="file"
                        name="image"
                        id="productImage"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <?php if (
                        $editProduct &&
                        !empty($editProduct["image"])
                    ): ?>

                        <img
                            src="../assets/images/<?= htmlspecialchars(
                                $editProduct["image"]
                            ) ?>"
                            class="current-image"
                            id="currentProductImage"
                            alt="Current product image"
                        >

                    <?php endif; ?>

                </div>


            </div>


            <button
                type="submit"
                class="submit-btn"
                id="submitProductBtn"
            >

                <?= $editProduct
                    ? "Update Product"
                    : "Add Product" ?>

            </button>

        </form>

    </div>

</div>


<script>

    const productModal = document.getElementById("productModal");
    const productForm = document.getElementById("productForm");
    const formAction = document.getElementById("formAction");
    const productId = document.getElementById("productId");
    const modalTitle = document.getElementById("modalTitle");
    const submitProductBtn = document.getElementById("submitProductBtn");
    const productImage = document.getElementById("productImage");
    const currentProductImage = document.getElementById("currentProductImage");

    function openAddModal() {

        productForm.reset();

        formAction.value = "add";

        if (productId) {
            productId.remove();
        }

        document.getElementById("productName").value = "";
        document.getElementById("productDescription").value = "";
        document.getElementById("productCategory").value = "Coffee";
        document.getElementById("productPrice").value = "";
        document.getElementById("productStock").value = "0";
        document.getElementById("productStatus").value = "Available";

        if (productImage) {
            productImage.value = "";
        }

        if (currentProductImage) {
            currentProductImage.remove();
        }

        modalTitle.textContent = "Add Product";
        submitProductBtn.textContent = "Add Product";

        productModal.classList.add("show");

        window.history.replaceState(
            {},
            document.title,
            "products.php"
        );

        document.getElementById("productName").focus();
    }

    function closeModal() {

        productModal.classList.remove("show");

        window.history.replaceState(
            {},
            document.title,
            "products.php"
        );
    }

    productModal.addEventListener("click", function(event) {

        if (event.target === this) {
            closeModal();
        }

    });

    document.addEventListener("keydown", function(event) {

        if (event.key === "Escape" && productModal.classList.contains("show")) {
            closeModal();
        }

    });

    productForm.addEventListener("submit", function() {

        if (formAction.value === "add") {
            submitProductBtn.disabled = true;
            submitProductBtn.textContent = "Adding Product...";
        } else {
            submitProductBtn.disabled = true;
            submitProductBtn.textContent = "Updating Product...";
        }

    });

</script>

</body>
</html>