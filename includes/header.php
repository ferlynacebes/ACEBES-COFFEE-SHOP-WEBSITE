<?php

/* =========================================================
   START SESSION
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   CURRENT PAGE
========================================================= */

$currentPage = basename($_SERVER['PHP_SELF']);


/* =========================================================
   USER SESSION
========================================================= */

$isLoggedIn = !empty($_SESSION['user_id']);

$profileName = $_SESSION['user_name']
    ?? $_SESSION['full_name']
    ?? $_SESSION['name']
    ?? $_SESSION['username']
    ?? 'My Account';

$profileRole = $_SESSION['user_role']
    ?? $_SESSION['role']
    ?? 'Customer';

if (is_array($profileName)) {
    $profileName = 'My Account';
}

if (empty($profileName)) {
    $profileName = 'My Account';
}

$profileName = htmlspecialchars($profileName);
$profileRole = htmlspecialchars($profileRole);

$profileInitial = strtoupper(substr(strip_tags($profileName), 0, 1));

if (empty($profileInitial)) {
    $profileInitial = 'P';
}


/* =========================================================
   BASE PATH
========================================================= */

$projectPath = rtrim(
    dirname($_SERVER['SCRIPT_NAME']),
    '/'
);

if ($projectPath === '\\' || $projectPath === '.') {
    $projectPath = '';
}


/* =========================================================
   PAGE TITLE
========================================================= */

if ($currentPage == 'index.php') {

    $pageTitle = 'Acebes Coffee Shop';

} elseif ($currentPage == 'about.php') {

    $pageTitle = 'About Us | Acebes Coffee Shop';

} elseif ($currentPage == 'menu.php') {

    $pageTitle = 'Menu | Acebes Coffee Shop';

} elseif ($currentPage == 'why-us.php') {

    $pageTitle = 'Why Us | Acebes Coffee Shop';

} elseif ($currentPage == 'contact.php') {

    $pageTitle = 'Contact Us | Acebes Coffee Shop';

} elseif ($currentPage == 'cart.php') {

    $pageTitle = 'My Cart | Acebes Coffee Shop';

} elseif ($currentPage == 'checkout.php') {

    $pageTitle = 'Checkout | Acebes Coffee Shop';

} elseif ($currentPage == 'orders.php') {

    $pageTitle = 'My Orders | Acebes Coffee Shop';

} else {

    $pageTitle = 'Acebes Coffee Shop';

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?php echo htmlspecialchars($pageTitle); ?>
    </title>


    <!-- =====================================================
         GOOGLE FONTS
    ====================================================== -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Pacifico&display=swap"
        rel="stylesheet"
    >


    <!-- =====================================================
         MAIN WEBSITE CSS
    ====================================================== -->

    <link rel="stylesheet" href="css/style.css">


    <!-- =====================================================
         PROFILE DROPDOWN CSS
    ====================================================== -->

    <style>

        /* =====================================================
           PROFILE DROPDOWN
        ===================================================== */

        .profile-dropdown {
            position: relative;
            display: flex;
            align-items: center;
            margin-left: 8px;
        }

        .profile-toggle {
            display: flex;
            align-items: center;
            gap: 9px;

            background: #5B3928 !important;
            border: 1px solid rgba(255, 255, 255, 0.28) !important;

            padding: 7px 10px;

            color: #FFFFFF !important;

            font-family: 'Montserrat', sans-serif;
            font-size: 13px;
            font-weight: 700;

            cursor: pointer;
            border-radius: 30px;

            transition: none !important;
        }
     
        .profile-toggle:hover,
        .profile-toggle:focus,
        .profile-toggle:active,
        .profile-toggle[aria-expanded="true"] {
                background: #5B3928 !important;
                border: 1px solid rgba(255, 255, 255, 0.28) !important;
                color: #FFFFFF !important;
                box-shadow: none !important;
                outline: none !important;
    }


        .profile-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #C49A6C !important;
            color: #2A1A13 !important;

            border: 2px solid #FFFFFF !important;

            font-size: 14px;
            font-weight: 800;
        }

        .profile-name {
            max-width: 110px;

            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }


        .profile-chevron {
            width: 7px;
            height: 7px;

             border-right: 2px solid #FFFFFF;
             border-bottom: 2px solid #FFFFFF;

            transform: rotate(45deg);
            margin-top: -4px;

            transition: transform 0.25s ease;
        }


        .profile-dropdown.open .profile-chevron {
            transform: rotate(225deg);
            margin-top: 4px;
        }


        /* =====================================================
           DROPDOWN MENU
        ===================================================== */

        .profile-menu {
            position: absolute;

            top: calc(100% + 12px);
            right: 0;

            width: 245px;

            background: #FFFFFF;

            border-radius: 16px;

            padding: 8px;

            box-shadow:
                0 18px 45px rgba(42, 26, 19, 0.18);

            border: 1px solid rgba(75, 46, 32, 0.08);

            opacity: 0;
            visibility: hidden;

            transform:
                translateY(-8px)
                scale(0.98);

            transform-origin: top right;

            transition:
                opacity 0.2s ease,
                transform 0.2s ease,
                visibility 0.2s ease;

            z-index: 9999;
        }


        .profile-dropdown.open .profile-menu {
            opacity: 1;
            visibility: visible;

            transform:
                translateY(0)
                scale(1);
        }


        /* =====================================================
           DROPDOWN HEADER
        ===================================================== */

        .profile-menu-header {
            display: flex;
            align-items: center;

            gap: 11px;

            padding: 12px;

            margin-bottom: 5px;

            background: #F5EFE6;

            border-radius: 12px;
        }


        .profile-menu-avatar {
            width: 42px;
            height: 42px;

            flex-shrink: 0;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #4B2E20;
            color: #F5EFE6;

            font-size: 15px;
            font-weight: 800;
        }


        .profile-menu-user {
            min-width: 0;
        }


        .profile-menu-user strong {
            display: block;

            color: #2A1A13;

            font-size: 13px;
            font-weight: 800;

            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }


        .profile-menu-user span {
            display: block;

            margin-top: 3px;

            color: #8A7568;

            font-size: 11px;
            font-weight: 600;

            text-transform: capitalize;
        }


        /* =====================================================
           DROPDOWN ITEMS
        ===================================================== */

        .profile-menu-item {
            display: flex;
            align-items: center;

            gap: 11px;

            width: 100%;

            padding: 11px 12px;

            border-radius: 10px;

            text-decoration: none;

            color: #4B2E20;

            font-family: 'Montserrat', sans-serif;
            font-size: 12px;
            font-weight: 700;

            transition:
                background 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease;
        }


        .profile-menu-item:hover {
            background: #F5EFE6;
            color: #2A1A13;

            transform: translateX(3px);
        }


        .profile-menu-icon {
            width: 28px;
            height: 28px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 8px;

            background: rgba(196, 154, 108, 0.14);

            font-size: 14px;

            flex-shrink: 0;
        }


        .profile-menu-divider {
            height: 1px;

            background: rgba(75, 46, 32, 0.08);

            margin: 6px 5px;
        }


        /* =====================================================
           LOGOUT
        ===================================================== */

        .profile-menu-item.logout-item {
            color: #9B3A32;
        }


        .profile-menu-item.logout-item:hover {
            background: rgba(155, 58, 50, 0.08);
            color: #8A2F28;
        }


        /* =====================================================
           MOBILE PROFILE
        ===================================================== */

        .mobile-profile-box {
            display: none;
        }


        @media (max-width: 768px) {

            .profile-dropdown {
                display: none;
            }


            .mobile-profile-box {
                display: block;

                margin: 10px 0 12px;

                padding: 14px;

                background: rgba(245, 239, 230, 0.8);

                border-radius: 14px;
            }


            .mobile-profile-header {
                display: flex;
                align-items: center;

                gap: 11px;

                margin-bottom: 10px;
            }


            .mobile-profile-avatar {
                width: 40px;
                height: 40px;

                border-radius: 50%;

                display: flex;
                align-items: center;
                justify-content: center;

                background: #4B2E20;
                color: #F5EFE6;

                font-size: 14px;
                font-weight: 800;
            }


            .mobile-profile-info strong {
                display: block;

                color: #2A1A13;

                font-size: 13px;
                font-weight: 800;
            }


            .mobile-profile-info span {
                display: block;

                margin-top: 2px;

                color: #8A7568;

                font-size: 10px;
                font-weight: 600;

                text-transform: capitalize;
            }


            .mobile-profile-links {
                display: grid;

                grid-template-columns: 1fr 1fr;

                gap: 7px;
            }


            .mobile-profile-link {
                display: flex;
                align-items: center;
                justify-content: center;

                padding: 9px 7px;

                border-radius: 9px;

                background: #FFFFFF;

                color: #4B2E20;

                text-decoration: none;

                font-size: 10px;
                font-weight: 700;

                transition: 0.2s ease;
            }


            .mobile-profile-link:hover {
                background: #4B2E20;
                color: #FFFFFF;
            }


            .mobile-profile-link.logout {
                color: #9B3A32;
            }

        }


    </style>

</head>


<body>


<!-- =========================================================
     HEADER / NAVIGATION
========================================================= -->

<header class="site-header">

    <div class="container nav-container">


        <!-- =================================================
             LOGO
        ================================================== -->

        <a
            href="index.php"
            class="logo"
            aria-label="Acebes Coffee Shop Home"
        >

            <img
                src="assets/images/logo.jpg"
                alt="Acebes Coffee Shop Logo"
            >

        </a>



        <!-- =================================================
             DESKTOP NAVIGATION
        ================================================== -->

        <nav
            class="main-nav"
            aria-label="Main navigation"
        >

            <!-- HOME -->

            <a
                href="index.php"
                class="<?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>"
                <?php echo ($currentPage == 'index.php') ? 'aria-current="page"' : ''; ?>
            >
                Home
            </a>


            <!-- ABOUT -->

            <a
                href="about.php"
                class="<?php echo ($currentPage == 'about.php') ? 'active' : ''; ?>"
                <?php echo ($currentPage == 'about.php') ? 'aria-current="page"' : ''; ?>
            >
                About
            </a>


            <!-- MENU -->

            <a
                href="menu.php"
                class="<?php echo ($currentPage == 'menu.php') ? 'active' : ''; ?>"
                <?php echo ($currentPage == 'menu.php') ? 'aria-current="page"' : ''; ?>
            >
                Menu
            </a>


            <!-- WHY US -->

            <a
                href="why-us.php"
                class="<?php echo ($currentPage == 'why-us.php') ? 'active' : ''; ?>"
                <?php echo ($currentPage == 'why-us.php') ? 'aria-current="page"' : ''; ?>
            >
                Why Us
            </a>


            <!-- CONTACT -->

            <a
                href="contact.php"
                class="<?php echo ($currentPage == 'contact.php') ? 'active' : ''; ?>"
                <?php echo ($currentPage == 'contact.php') ? 'aria-current="page"' : ''; ?>
            >
                Contact
            </a>


            <!-- =================================================
                 PROFILE DROPDOWN
            ================================================== -->

            <div
                class="profile-dropdown"
                id="profileDropdown"
            >

                <button
                    type="button"
                    class="profile-toggle"
                    id="profileToggle"
                    aria-expanded="false"
                    aria-haspopup="true"
                >

                    <span class="profile-avatar">
                        <?php echo htmlspecialchars($profileInitial); ?>
                    </span>

                    <span class="profile-name">
                        <?php
                        echo $isLoggedIn
                            ? $profileName
                            : 'Profile';
                        ?>
                    </span>

                    <span class="profile-chevron"></span>

                </button>


                <div
                    class="profile-menu"
                    id="profileMenu"
                >

                    <!-- PROFILE HEADER -->

                    <div class="profile-menu-header">

                        <div class="profile-menu-avatar">
                            <?php echo htmlspecialchars($profileInitial); ?>
                        </div>

                        <div class="profile-menu-user">

                            <strong>
                                <?php
                                echo $isLoggedIn
                                    ? $profileName
                                    : 'Welcome!';
                                ?>
                            </strong>

                            <span>
                                <?php
                                echo $isLoggedIn
                                    ? $profileRole
                                    : 'Customer';
                                ?>
                            </span>

                        </div>

                    </div>


                    <?php if ($isLoggedIn): ?>

                        <a
                            href="profile.php"
                            class="profile-menu-item"
                        >
                            <span class="profile-menu-icon">👤</span>
                            My Profile
                        </a>

                        <a
                            href="orders.php"
                            class="profile-menu-item"
                        >
                            <span class="profile-menu-icon">📋</span>
                            My Orders
                        </a>

                    <?php else: ?>

                        <a
                            href="login.php"
                            class="profile-menu-item"
                        >
                            <span class="profile-menu-icon">🔐</span>
                            Login
                        </a>

                        <a
                            href="register.php"
                            class="profile-menu-item"
                        >
                            <span class="profile-menu-icon">✨</span>
                            Create Account
                        </a>

                    <?php endif; ?>


                    <a
                        href="cart.php"
                        class="profile-menu-item"
                    >
                        <span class="profile-menu-icon">🛒</span>
                        My Cart
                    </a>


                    <a
                        href="checkout.php"
                        class="profile-menu-item"
                    >
                        <span class="profile-menu-icon">💳</span>
                        Checkout
                    </a>


                    <?php if ($isLoggedIn): ?>

                        <div class="profile-menu-divider"></div>

                        <a
                            href="logout.php"
                            class="profile-menu-item logout-item"
                        >
                            <span class="profile-menu-icon">↪</span>
                            Logout
                        </a>

                    <?php endif; ?>

                </div>

            </div>


            <!-- ORDER NOW -->

            <a
                href="menu.php"
                class="nav-order-btn"
            >
                ORDER NOW
            </a>

        </nav>



        <!-- =================================================
             MOBILE MENU BUTTON
        ================================================== -->

        <button
            type="button"
            class="mobile-menu-btn"
            id="mobileMenuBtn"
            aria-label="Open navigation menu"
            aria-controls="mobileNav"
            aria-expanded="false"
        >

            <span></span>
            <span></span>
            <span></span>

        </button>

    </div>



    <!-- =====================================================
         MOBILE NAVIGATION
    ====================================================== -->

    <nav
        class="mobile-nav"
        id="mobileNav"
        aria-label="Mobile navigation"
    >


        <!-- MOBILE PROFILE -->

        <div class="mobile-profile-box">

            <div class="mobile-profile-header">

                <div class="mobile-profile-avatar">
                    <?php echo htmlspecialchars($profileInitial); ?>
                </div>

                <div class="mobile-profile-info">

                    <strong>
                        <?php
                        echo $isLoggedIn
                            ? $profileName
                            : 'Welcome!';
                        ?>
                    </strong>

                    <span>
                        <?php
                        echo $isLoggedIn
                            ? $profileRole
                            : 'Customer';
                        ?>
                    </span>

                </div>

            </div>


            <div class="mobile-profile-links">

                <?php if ($isLoggedIn): ?>

                    <a
                        href="profile.php"
                        class="mobile-profile-link"
                    >
                        👤 Profile
                    </a>

                    <a
                        href="orders.php"
                        class="mobile-profile-link"
                    >
                        📋 Orders
                    </a>

                    <a
                        href="cart.php"
                        class="mobile-profile-link"
                    >
                        🛒 Cart
                    </a>

                    <a
                        href="checkout.php"
                        class="mobile-profile-link"
                    >
                        💳 Checkout
                    </a>

                    <a
                        href="logout.php"
                        class="mobile-profile-link logout"
                    >
                        ↪ Logout
                    </a>

                <?php else: ?>

                    <a
                        href="login.php"
                        class="mobile-profile-link"
                    >
                        🔐 Login
                    </a>

                    <a
                        href="register.php"
                        class="mobile-profile-link"
                    >
                        ✨ Register
                    </a>

                    <a
                        href="cart.php"
                        class="mobile-profile-link"
                    >
                        🛒 Cart
                    </a>

                    <a
                        href="checkout.php"
                        class="mobile-profile-link"
                    >
                        💳 Checkout
                    </a>

                <?php endif; ?>

            </div>

        </div>



        <!-- HOME -->

        <a
            href="index.php"
            class="<?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>"
            <?php echo ($currentPage == 'index.php') ? 'aria-current="page"' : ''; ?>
        >
            Home
        </a>


        <!-- ABOUT -->

        <a
            href="about.php"
            class="<?php echo ($currentPage == 'about.php') ? 'active' : ''; ?>"
            <?php echo ($currentPage == 'about.php') ? 'aria-current="page"' : ''; ?>
        >
            About
        </a>


        <!-- MENU -->

        <a
            href="menu.php"
            class="<?php echo ($currentPage == 'menu.php') ? 'active' : ''; ?>"
            <?php echo ($currentPage == 'menu.php') ? 'aria-current="page"' : ''; ?>
        >
            Menu
        </a>


        <!-- WHY US -->

        <a
            href="why-us.php"
            class="<?php echo ($currentPage == 'why-us.php') ? 'active' : ''; ?>"
            <?php echo ($currentPage == 'why-us.php') ? 'aria-current="page"' : ''; ?>
        >
            Why Us
        </a>


        <!-- CONTACT -->

        <a
            href="contact.php"
            class="<?php echo ($currentPage == 'contact.php') ? 'active' : ''; ?>"
            <?php echo ($currentPage == 'contact.php') ? 'aria-current="page"' : ''; ?>
        >
            Contact
        </a>


        <!-- ORDER NOW -->

        <a
            href="menu.php"
            class="mobile-order-btn"
        >
            ORDER NOW
        </a>

    </nav>

</header>



<!-- =========================================================
     PROFILE DROPDOWN + MOBILE MENU SCRIPT
========================================================= -->

<script>

document.addEventListener('DOMContentLoaded', function () {


    /* =====================================================
       PROFILE DROPDOWN
    ===================================================== */

    const profileDropdown =
        document.getElementById('profileDropdown');

    const profileToggle =
        document.getElementById('profileToggle');

    const profileMenu =
        document.getElementById('profileMenu');


    if (
        profileDropdown &&
        profileToggle &&
        profileMenu
    ) {

        profileToggle.addEventListener(
            'click',
            function (event) {

                event.stopPropagation();

                const isOpen =
                    profileDropdown.classList.toggle('open');

                profileToggle.setAttribute(
                    'aria-expanded',
                    isOpen ? 'true' : 'false'
                );

            }
        );


        document.addEventListener(
            'click',
            function (event) {

                if (
                    !profileDropdown.contains(event.target)
                ) {

                    profileDropdown.classList.remove('open');

                    profileToggle.setAttribute(
                        'aria-expanded',
                        'false'
                    );

                }

            }
        );


        document.addEventListener(
            'keydown',
            function (event) {

                if (event.key === 'Escape') {

                    profileDropdown.classList.remove('open');

                    profileToggle.setAttribute(
                        'aria-expanded',
                        'false'
                    );

                }

            }
        );

    }



    /* =====================================================
       MOBILE MENU
    ===================================================== */

    const mobileMenuBtn =
        document.getElementById('mobileMenuBtn');

    const mobileNav =
        document.getElementById('mobileNav');


    if (!mobileMenuBtn || !mobileNav) {
        return;
    }


    mobileMenuBtn.addEventListener(
        'click',
        function () {

            const isOpen =
                mobileNav.classList.toggle('open');

            mobileMenuBtn.classList.toggle(
                'active',
                isOpen
            );


            mobileMenuBtn.setAttribute(
                'aria-expanded',
                isOpen ? 'true' : 'false'
            );


            mobileMenuBtn.setAttribute(
                'aria-label',
                isOpen
                    ? 'Close navigation menu'
                    : 'Open navigation menu'
            );

        }
    );


    /* =====================================================
       CLOSE MOBILE MENU AFTER LINK CLICK
    ===================================================== */

    const mobileLinks =
        mobileNav.querySelectorAll('a');


    mobileLinks.forEach(function (link) {

        link.addEventListener(
            'click',
            function () {

                mobileNav.classList.remove('open');

                mobileMenuBtn.classList.remove('active');

                mobileMenuBtn.setAttribute(
                    'aria-expanded',
                    'false'
                );

                mobileMenuBtn.setAttribute(
                    'aria-label',
                    'Open navigation menu'
                );

            }
        );

    });


    /* =====================================================
       RESET MOBILE MENU ON RESIZE
    ===================================================== */

    window.addEventListener(
        'resize',
        function () {

            if (window.innerWidth > 768) {

                mobileNav.classList.remove('open');

                mobileMenuBtn.classList.remove('active');

                mobileMenuBtn.setAttribute(
                    'aria-expanded',
                    'false'
                );

                mobileMenuBtn.setAttribute(
                    'aria-label',
                    'Open navigation menu'
                );

            }

        }
    );

});

</script>