<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER["PHP_SELF"]);

$adminName = $_SESSION["user_name"]
    ?? $_SESSION["admin_name"]
    ?? "Administrator";

?>

<aside class="sidebar">

    <!-- BRAND / LOGO -->
    <div class="brand">

        <img
            src="../assets/images/logo2.png"
            alt="Acebes Coffee Logo"
            class="brand-logo"
        >

        <div class="brand-text">
            <h1>Acebes Coffee</h1>
            <p>ADMIN PANEL</p>
        </div>

    </div>


    <!-- ADMIN PROFILE -->
    <div class="admin-profile">

        <strong>
            <?= htmlspecialchars($adminName, ENT_QUOTES, "UTF-8") ?>
        </strong>

        <span>
            Administrator
        </span>

    </div>


    <!-- NAVIGATION -->
    <div class="nav-title">
        Management
    </div>

    <nav>

        <a
            href="dashboard.php"
            class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
        >
            <span class="nav-icon">📊</span>
            Dashboard
        </a>

        <a
            href="products.php"
            class="<?= $currentPage === 'products.php' ? 'active' : '' ?>"
        >
            <span class="nav-icon">☕</span>
            Products
        </a>

        <a
            href="orders.php"
            class="<?= $currentPage === 'orders.php' ? 'active' : '' ?>"
        >
            <span class="nav-icon">🛒</span>
            Orders
        </a>

        <a
            href="customers.php"
            class="<?= $currentPage === 'customers.php' ? 'active' : '' ?>"
        >
            <span class="nav-icon">👥</span>
            Customers
        </a>

        <a
            href="messages.php"
            class="<?= $currentPage === 'messages.php' ? 'active' : '' ?>"
        >
            <span class="nav-icon">✉️</span>
            Messages
        </a>

    </nav>


    <!-- BOTTOM LINKS -->
    <div class="sidebar-bottom">

        <a href="../index.php">
            <span class="nav-icon">🌐</span>
            View Website
        </a>

        <a href="../logout.php">
            <span class="nav-icon">↪</span>
            Logout
        </a>

    </div>

</aside>


<style>

/* =========================================================
   SIDEBAR — MATCHES THE DASHBOARD
========================================================= */

.sidebar {
    width: 225px;
    background: #2a1a13;
    color: #ffffff;
    padding: 26px 16px;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    z-index: 1000;
    overflow-y: auto;
    box-sizing: border-box;
}


/* BRAND */

.brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 2px 8px 27px;
    border-bottom: 1px solid rgba(255,255,255,.10);
    margin-bottom: 22px;
}

.brand-logo {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 20px;
    display: block;
    background: #4b2e20;
    margin-bottom: 14px;
}

.brand-text {
    width: 100%;
}

.brand h1 {
    margin: 0;
    color: #f5efe6;
    font-family: Georgia, serif;
    font-size: 20px;
    line-height: 1.1;
    font-weight: 700;
}

.brand p {
    margin: 7px 0 0;
    color: #c49a6c;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 2px;
}


/* ADMIN PROFILE */

.admin-profile {
    background: rgba(255,255,255,.07);
    padding: 13px;
    border-radius: 12px;
    margin-bottom: 23px;
}

.admin-profile strong {
    display: block;
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
}

.admin-profile span {
    display: block;
    color: #c49a6c;
    font-size: 12px;
    margin-top: 3px;
}


/* NAVIGATION */

.nav-title {
    color: #a99688;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    padding: 0 12px;
    margin-bottom: 8px;
    font-weight: 700;
}

.sidebar nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 14px;
    border-radius: 10px;
    margin-bottom: 5px;
    color: #ddd0c8;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: .2s ease;
}

.sidebar nav a:hover,
.sidebar nav a.active {
    background: #4b2e20;
    color: #ffffff;
}

.nav-icon {
    width: 20px;
    min-width: 20px;
    text-align: center;
    line-height: 1;
}


/* BOTTOM */

.sidebar-bottom {
    position: absolute;
    left: 16px;
    right: 16px;
    bottom: 22px;
}

.sidebar-bottom a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 10px;
    color: #d7c6bb;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    margin-top: 6px;
}

.sidebar-bottom a:hover {
    background: rgba(255,255,255,.07);
    color: #ffffff;
}


@media (max-width: 950px) {

    .sidebar {
        width: 210px;
    }

}


@media (max-width: 700px) {

    .sidebar {
        position: static;
        width: 100%;
        min-height: auto;
    }

    .brand {
        align-items: flex-start;
        text-align: left;
        flex-direction: row;
        gap: 14px;
    }

    .brand-logo {
        width: 58px;
        height: 58px;
        margin-bottom: 0;
    }

    .brand-text {
        padding-top: 4px;
    }

    .sidebar-bottom {
        position: static;
        margin-top: 20px;
    }

}

</style>
