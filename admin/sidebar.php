<?php

/**
 * =========================================================
 * ACEBES COFFEE - ADMIN SIDEBAR
 * =========================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER["PHP_SELF"]);

$adminName = $_SESSION["user_name"]
    ?? $_SESSION["admin_name"]
    ?? "Administrator";


/*
|--------------------------------------------------------------------------
| FIND LOGO AUTOMATICALLY
|--------------------------------------------------------------------------
*/

$logoCandidates = [
    
    "logo2.png",
    
];

$logoFile = null;

foreach ($logoCandidates as $candidate) {

    $fullPath = __DIR__ . "/../assets/images/" . $candidate;

    if (file_exists($fullPath)) {
        $logoFile = $candidate;
        break;
    }
}


/*
|--------------------------------------------------------------------------
| LOGO URL
|--------------------------------------------------------------------------
*/

$logoUrl = $logoFile
    ? "../assets/images/" . $logoFile
    : "";

?>

<aside class="sidebar">

    <!-- =====================================================
         BRAND
    ====================================================== -->

    <div class="brand">

        <?php if ($logoUrl !== ""): ?>

            <div class="brand-logo-wrap">

                <img
                    src="<?= htmlspecialchars(
                        $logoUrl,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    alt="Acebes Coffee Logo"
                    class="brand-logo"
                >

            </div>

        <?php else: ?>

            <!-- Fallback if no logo file exists -->
            <div class="brand-logo-wrap logo-fallback">

                <span>AC</span>

            </div>

        <?php endif; ?>


        <div class="brand-text">

            <h1>
                Acebes Coffee
            </h1>

            <p>
                ADMIN PANEL
            </p>

        </div>

    </div>


    <!-- =====================================================
         ADMIN PROFILE
    ====================================================== -->

    <div class="admin-profile">

        <strong>
            <?= htmlspecialchars(
                $adminName,
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </strong>

        <span>
            Administrator
        </span>

    </div>


    <!-- =====================================================
         NAVIGATION
    ====================================================== -->

    <div class="nav-title">
        Management
    </div>


    <nav class="admin-nav">

        <a
            href="dashboard.php"
            class="<?= $currentPage === 'dashboard.php'
                ? 'active'
                : '' ?>"
        >

            <span class="nav-icon">
                📊
            </span>

            <span>
                Dashboard
            </span>

        </a>


        <a
            href="products.php"
            class="<?= $currentPage === 'products.php'
                ? 'active'
                : '' ?>"
        >

            <span class="nav-icon">
                ☕
            </span>

            <span>
                Products
            </span>

        </a>


        <a
            href="orders.php"
            class="<?= $currentPage === 'orders.php'
                ? 'active'
                : '' ?>"
        >

            <span class="nav-icon">
                🛒
            </span>

            <span>
                Orders
            </span>

        </a>


        <a
            href="customers.php"
            class="<?= $currentPage === 'customers.php'
                ? 'active'
                : '' ?>"
        >

            <span class="nav-icon">
                👥
            </span>

            <span>
                Customers
            </span>

        </a>

    </nav>


    <!-- =====================================================
         BOTTOM LINKS
    ====================================================== -->

    <div class="sidebar-bottom">

        <a href="../index.php">

            <span class="nav-icon">
                🌐
            </span>

            <span>
                View Website
            </span>

        </a>


        <a href="../logout.php">

            <span class="nav-icon">
                ↪
            </span>

            <span>
                Logout
            </span>

        </a>

    </div>

</aside>


<style>

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 250px;

    background: #2a1a13;

    color: white;

    padding: 28px 18px;

    position: fixed;

    left: 0;
    top: 0;
    bottom: 0;

    z-index: 100;

    overflow-y: auto;

}


/* =========================================================
   BRAND
========================================================= */

.brand {

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    text-align: center;

    padding: 0 12px 28px;

    border-bottom:
        1px solid
        rgba(255, 255, 255, .1);

    margin-bottom: 24px;

}


/* =========================================================
   LOGO
========================================================= */

.brand-logo-wrap {

    width: 100px;

    height: 100px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 14px;

}


.brand-logo {
    width: 100px;
    height: 100px;
    flex-shrink: 0;

    object-fit: contain;

    background: #543525;

    padding: 16px;

    border-radius: 18px;

    display: block;
}


/* Fallback if no image exists */

.logo-fallback {

    width: 100px;

    height: 100px;

    border-radius: 18px;

    background: #4b2e20;

    color: #f5efe6;

    font-family:
        'Montserrat',
        Arial,
        sans-serif;

    font-size: 28px;

    font-weight: 800;

    letter-spacing: 1px;

}


/* =========================================================
   BRAND TEXT
========================================================= */

.brand-text {

    width: 100%;

    text-align: center;

}


.brand h1 {

    margin: 0;

    color: #f5efe6;

    font-family:
        'Montserrat',
        Arial,
        sans-serif;

    font-size: 19px;

    font-weight: 800;

    line-height: 1.2;

}


.brand p {

    margin: 6px 0 0;

    color: #c49a6c;

    font-family:
        'Montserrat',
        Arial,
        sans-serif;

    font-size: 9px;

    font-weight: 700;

    letter-spacing: 1.5px;

}


/* =========================================================
   ADMIN PROFILE
========================================================= */

.admin-profile {

    background:
        rgba(255, 255, 255, .07);

    padding: 14px;

    border-radius: 12px;

    margin-bottom: 24px;

}


.admin-profile strong {

    display: block;

    color: #ffffff;

    font-family:
        'Montserrat',
        Arial,
        sans-serif;

    font-size: 15px;

    font-weight: 700;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;

}


.admin-profile span {

    display: block;

    color: #c49a6c;

    font-family:
        'Montserrat',
        Arial,
        sans-serif;

    font-size: 12px;

    margin-top: 4px;

}


/* =========================================================
   MANAGEMENT
========================================================= */

.nav-title {

    color: #a99688;

    font-family:
        'Montserrat',
        Arial,
        sans-serif;

    font-size: 10px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 1.5px;

    padding: 0 14px;

    margin-bottom: 8px;

}


.admin-nav {

    display: block;

}


.admin-nav a {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 13px 14px;

    border-radius: 10px;

    margin-bottom: 5px;

    color: #ddd0c8;

    font-family:
        'Montserrat',
        Arial,
        sans-serif;

    font-size: 14px;

    font-weight: 500;

    text-decoration: none;

    transition:
        background .2s ease,
        color .2s ease,
        transform .2s ease;

}


.admin-nav a:hover {

    background: #4b2e20;

    color: #ffffff;

    transform: translateX(2px);

}


.admin-nav a.active {

    background: #4b2e20;

    color: #ffffff;

    font-weight: 700;

}


/* =========================================================
   ICON
========================================================= */

.nav-icon {

    width: 22px;

    min-width: 22px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    font-size: 16px;

    line-height: 1;

}


/* =========================================================
   BOTTOM
========================================================= */

.sidebar-bottom {

    position: absolute;

    left: 18px;
    right: 18px;

    bottom: 25px;

}


.sidebar-bottom a {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 12px 14px;

    border-radius: 10px;

    color: #d7c6bb;

    font-family:
        'Montserrat',
        Arial,
        sans-serif;

    font-size: 13px;

    font-weight: 500;

    text-decoration: none;

    margin-top: 6px;

    transition:
        background .2s ease,
        color .2s ease;

}


.sidebar-bottom a:hover {

    background:
        rgba(255, 255, 255, .07);

    color: #ffffff;

}


/* =========================================================
   TABLET
========================================================= */

@media (max-width: 950px) {

    .sidebar {

        width: 210px;

    }

}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 700px) {

    .sidebar {

        position: static;

        width: 100%;

        min-height: auto;

        overflow: visible;

    }


    .brand {

        justify-content: center;

    }


    .sidebar-bottom {

        position: static;

        margin-top: 20px;

    }

}

</style>