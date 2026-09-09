<?php

/* =========================================================
   START SESSION
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   DATABASE
========================================================= */

require_once "config/db.php";


/* =========================================================
   IF ALREADY LOGGED IN
========================================================= */

if (!empty($_SESSION["user_id"])) {

    if (($_SESSION["user_role"] ?? "") === "admin") {

        header("Location: admin/dashboard.php");
        exit;

    }

    header("Location: index.php");
    exit;
}


/* =========================================================
   VARIABLES
========================================================= */

$error = "";

$email = "";


/* =========================================================
   LOGIN PROCESS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";


    /* =====================================================
       VALIDATION
    ===================================================== */

    if ($email === "" || $password === "") {

        $error =
            "Please enter your email and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error =
            "Please enter a valid email address.";

    } else {


        /* =================================================
           CHECK USERS TABLE
           
           BOTH CUSTOMER AND ADMIN ACCOUNTS
           ARE ALLOWED HERE.
        ================================================= */

        $stmt = $conn->prepare("
            SELECT
                id,
                name,
                email,
                password,
                role
            FROM users
            WHERE email = ?
            LIMIT 1
        ");


        if (!$stmt) {

            $error =
                "Unable to process login. Please try again.";

        } else {

            $stmt->bind_param(
                "s",
                $email
            );

            $stmt->execute();

            $result =
                $stmt->get_result();


            /* =============================================
               ACCOUNT FOUND
            ============================================== */

            if ($result->num_rows === 1) {

                $user =
                    $result->fetch_assoc();


                /* =========================================
                   VERIFY PASSWORD
                ========================================== */

                if (
                    password_verify(
                        $password,
                        $user["password"]
                    )
                ) {


                    /* =====================================
                       PREVENT SESSION FIXATION
                    ====================================== */

                    session_regenerate_id(true);


                    /* =====================================
                       COMMON USER SESSION
                    ====================================== */

                    $_SESSION["user_id"] =
                        (int) $user["id"];

                    $_SESSION["user_name"] =
                        $user["name"];

                    $_SESSION["user_email"] =
                        $user["email"];

                    $_SESSION["user_role"] =
                        strtolower(
                            $user["role"]
                        );


                    /* =====================================
                       ADMIN SESSION COMPATIBILITY
                    ====================================== */

                    if (
                        strtolower(
                            $user["role"]
                        ) === "admin"
                    ) {

                        $_SESSION["admin_id"] =
                            (int) $user["id"];

                        $_SESSION["admin_name"] =
                            $user["name"];

                        $_SESSION["admin_email"] =
                            $user["email"];


                        $stmt->close();


                        /* ================================
                           ADMIN → DASHBOARD
                        ================================= */

                        header(
                            "Location: admin/dashboard.php"
                        );

                        exit;
                    }


                    /* =====================================
                       CUSTOMER → HOMEPAGE
                    ====================================== */

                    $stmt->close();

                    header(
                        "Location: index.php"
                    );

                    exit;


                } else {

                    $error =
                        "Incorrect email or password.";

                }


            } else {

                /* =========================================
                   OPTIONAL LEGACY ADMIN TABLE
                   
                   This allows existing admin accounts
                   inside admin_users to continue working.
                ========================================== */

                $stmt->close();


                $adminStmt = $conn->prepare("
                    SELECT
                        id,
                        name,
                        email,
                        password
                    FROM admin_users
                    WHERE email = ?
                    LIMIT 1
                ");


                if (!$adminStmt) {

                    $error =
                        "Incorrect email or password.";

                } else {

                    $adminStmt->bind_param(
                        "s",
                        $email
                    );

                    $adminStmt->execute();

                    $adminResult =
                        $adminStmt->get_result();


                    /* =====================================
                       ADMIN FOUND
                    ====================================== */

                    if (
                        $adminResult->num_rows === 1
                    ) {

                        $admin =
                            $adminResult->fetch_assoc();


                        /* =================================
                           VERIFY ADMIN PASSWORD
                        ================================== */

                        if (
                            password_verify(
                                $password,
                                $admin["password"]
                            )
                        ) {


                            /* =============================
                               NEW SESSION ID
                            ============================== */

                            session_regenerate_id(true);


                            /* =============================
                               COMMON SESSION
                            ============================== */

                            $_SESSION["user_id"] =
                                (int) $admin["id"];

                            $_SESSION["user_name"] =
                                $admin["name"];

                            $_SESSION["user_email"] =
                                $admin["email"];

                            $_SESSION["user_role"] =
                                "admin";


                            /* =============================
                               ADMIN SESSION
                            ============================== */

                            $_SESSION["admin_id"] =
                                (int) $admin["id"];

                            $_SESSION["admin_name"] =
                                $admin["name"];

                            $_SESSION["admin_email"] =
                                $admin["email"];


                            $adminStmt->close();


                            /* =============================
                               ADMIN → DASHBOARD
                            ============================== */

                            header(
                                "Location: admin/dashboard.php"
                            );

                            exit;


                        } else {

                            $error =
                                "Incorrect email or password.";

                        }


                    } else {

                        $error =
                            "Incorrect email or password.";

                    }


                    $adminStmt->close();

                }

            }

        }

    }

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
        Login | Acebes Coffee Shop
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


    <style>

        /* =====================================================
           RESET
        ===================================================== */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        /* =====================================================
           BODY
        ===================================================== */

        body {

            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 25px;

            background:
                linear-gradient(
                    135deg,
                    #2A1A13 0%,
                    #4B2E20 45%,
                    #C49A6C 100%
                );

            font-family:
                'Montserrat',
                sans-serif;

        }


        /* =====================================================
           LOGIN SHELL
        ===================================================== */

        .login-shell {

            width: 100%;
            max-width: 950px;

            min-height: 570px;

            display: grid;

            grid-template-columns:
                0.95fr
                1.05fr;

            overflow: hidden;

            background: #FFFFFF;

            border-radius: 26px;

            box-shadow:
                0 30px 80px
                rgba(0,0,0,0.25);

        }


        /* =====================================================
           BRAND PANEL
        ===================================================== */

        .brand-panel {

            position: relative;

            display: flex;
            align-items: center;

            padding: 55px;

            overflow: hidden;

            background:
                linear-gradient(
                    145deg,
                    #2A1A13,
                    #4B2E20
                );

            color: #FFFFFF;

        }


        .brand-panel::before {

            content: "";

            position: absolute;

            width: 300px;
            height: 300px;

            right: -130px;
            bottom: -130px;

            border-radius: 50%;

            border:
                50px solid
                rgba(196,154,108,0.12);

        }


        .brand-panel::after {

            content: "";

            position: absolute;

            width: 180px;
            height: 180px;

            left: -100px;
            top: -90px;

            border-radius: 50%;

            background:
                rgba(196,154,108,0.08);

        }


        .brand-content {

            position: relative;

            z-index: 2;

            width: 100%;

        }


        /* =====================================================
           LOGO
        ===================================================== */

       .brand-logo {
            display: inline-flex;
            align-items: center;

            margin-bottom: 75px;

            color: #FFFFFF;
            text-decoration: none;
        }

        /* LOGO IMAGE SIZE */
        .brand-logo img {
            width: 230px;
            height: auto;
            display: block;
            object-fit: contain;
        }


        /* =====================================================
           EYEBROW
        ===================================================== */

        .eyebrow {

            margin-bottom: 13px;

            color: #C49A6C;

            font-size: 10px;

            font-weight: 800;

            letter-spacing: 2px;

            text-transform: uppercase;

        }


        /* =====================================================
           BRAND TITLE
        ===================================================== */

        .brand-title {

            max-width: 400px;

            color: #FFFFFF;

            font-family:
                'Pacifico',
                cursive;

            font-size: 48px;

            font-weight: 400;

            line-height: 1.15;

        }


        /* =====================================================
           BRAND DESCRIPTION
        ===================================================== */

        .brand-description {

            max-width: 390px;

            margin-top: 22px;

            color: #D7C7BC;

            font-size: 12px;

            line-height: 1.8;

        }


        /* =====================================================
           COFFEE MARK
        ===================================================== */

        .coffee-mark {

            margin-top: 45px;

            color: #C49A6C;

            font-size: 50px;

            opacity: 0.8;

        }


        /* =====================================================
           LOGIN PANEL
        ===================================================== */

        .login-panel {

            display: flex;
            align-items: center;

            padding:
                55px
                65px;

            background: #FFFFFF;

        }


        .login-content {

            width: 100%;

            max-width: 390px;

            margin: 0 auto;

        }


        /* =====================================================
           LOGIN HEADER
        ===================================================== */

        .login-eyebrow {

            margin-bottom: 10px;

            color: #C49A6C;

            font-size: 10px;

            font-weight: 800;

            letter-spacing: 1.8px;

            text-transform: uppercase;

        }


        .login-title {

            color: #2A1A13;

            font-size: 38px;

            font-weight: 800;

        }


        .login-subtitle {

            margin-top: 8px;

            color: #8A7568;

            font-size: 12px;

            line-height: 1.6;

        }


        /* =====================================================
           ERROR
        ===================================================== */

        .error-box {

            display: flex;
            align-items: center;

            gap: 10px;

            margin-top: 25px;

            padding:
                12px
                14px;

            border-radius: 10px;

            background: #FFF0EE;

            border:
                1px solid
                #F1CCC7;

            color: #963E36;

            font-size: 11px;

            font-weight: 600;

            line-height: 1.5;

        }


        .error-icon {

            width: 22px;
            height: 22px;

            display: flex;
            align-items: center;
            justify-content: center;

            flex-shrink: 0;

            border-radius: 50%;

            background: #963E36;

            color: #FFFFFF;

            font-size: 11px;

            font-weight: 800;

        }


        /* =====================================================
           FORM
        ===================================================== */

        .login-form {

            margin-top: 30px;

        }


        .form-group {

            margin-bottom: 19px;

        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            color: #4B2E20;

            font-size: 10px;

            font-weight: 800;

            letter-spacing: 0.6px;

            text-transform: uppercase;

        }


        .form-group input {

            width: 100%;

            height: 50px;

            padding:
                0
                14px;

            border:
                1px solid
                #E2D7CE;

            border-radius: 10px;

            outline: none;

            background: #FFFCF9;

            color: #2A1A13;

            font-family:
                'Montserrat',
                sans-serif;

            font-size: 12px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;

        }


        .form-group input::placeholder {

            color: #B5A69D;

        }


        .form-group input:focus {

            border-color: #C49A6C;

            box-shadow:
                0 0 0 3px
                rgba(196,154,108,0.13);

        }


        /* =====================================================
           LOGIN BUTTON
        ===================================================== */

        .login-button {

            width: 100%;

            height: 50px;

            margin-top: 7px;

            border: none;

            border-radius: 10px;

            background: #4B2E20;

            color: #FFFFFF;

            font-family:
                'Montserrat',
                sans-serif;

            font-size: 11px;

            font-weight: 800;

            letter-spacing: 1px;

            cursor: pointer;

            transition:
                background 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;

        }


        .login-button:hover {

            background: #2A1A13;

            transform: translateY(-2px);

            box-shadow:
                0 8px 20px
                rgba(75,46,32,0.2);

        }


        /* =====================================================
           REGISTER
        ===================================================== */

        .register-text {

            margin-top: 24px;

            color: #8A7568;

            font-size: 11px;

            text-align: center;

        }


        .register-text a {

            margin-left: 4px;

            color: #4B2E20;

            font-weight: 800;

            text-decoration: none;

        }


        .register-text a:hover {

            color: #C49A6C;

        }


        /* =====================================================
           BACK HOME
        ===================================================== */

        .back-home {

            display: block;

            width: fit-content;

            margin:
                20px auto
                0;

            color: #8A7568;

            font-size: 10px;

            font-weight: 600;

            text-decoration: none;

            transition: color 0.2s ease;

        }


        .back-home:hover {

            color: #4B2E20;

        }


        /* =====================================================
           SECURITY NOTE
        ===================================================== */

        .secure-note {

            display: flex;

            justify-content: center;

            align-items: center;

            gap: 7px;

            margin-top: 23px;

            color: #A08F81;

            font-size: 9px;

            letter-spacing: 1px;

            text-transform: uppercase;

        }


        .secure-note span:first-child {

            color: #6C946F;

            font-size: 8px;

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 820px) {

            body {

                padding: 18px;

            }


            .login-shell {

                grid-template-columns: 1fr;

                max-width: 560px;

                min-height: auto;

            }


            .brand-panel {

                min-height: 280px;

                padding: 35px;

            }


            .brand-logo {

                margin-bottom: 40px;

                font-size: 25px;

            }


            .brand-title {

                font-size: 40px;

            }


            .brand-description,
            .coffee-mark {

                display: none;

            }


            .login-panel {

                padding:
                    40px
                    35px
                    45px;

            }

        }


        @media (max-width: 480px) {

            body {

                padding: 10px;

            }


            .login-shell {

                border-radius: 20px;

            }


            .brand-panel {

                min-height: 245px;

                padding:
                    28px
                    24px;

            }


            .brand-logo {

                font-size: 23px;

                margin-bottom: 32px;

            }


            .brand-title {

                font-size: 34px;

            }


            .login-panel {

                padding:
                    34px
                    23px
                    38px;

            }


            .login-title {

                font-size: 32px;

            }


            .form-group input,
            .login-button {

                height: 50px;

            }

        }


        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {

                transition: none !important;

            }

        }

    </style>

</head>


<body>


<main class="login-shell">


    <!-- =====================================================
         BRAND PANEL
    ====================================================== -->

    <section class="brand-panel">

        <div class="brand-content">


            <a
                href="index.php"
                class="brand-logo"
                aria-label="Acebes Coffee Shop Home"
            >
                <img
                    src="assets/images/logo.png"
                    alt="Acebes Coffee Shop Logo"
                >
            </a>

            <div class="eyebrow">
                Welcome to Acebes
            </div>


            <h1 class="brand-title">

                Your coffee moments
                start here.

            </h1>


            <p class="brand-description">

                Sign in to manage your account,
                explore our coffee menu, and enjoy
                a smoother ordering experience.

            </p>


            <div class="coffee-mark">
                ☕
            </div>

        </div>

    </section>



    <!-- =====================================================
         LOGIN PANEL
    ====================================================== -->

    <section class="login-panel">

        <div class="login-content">


            <!-- HEADER -->

            <div class="login-eyebrow">
                Account Access
            </div>


            <h2 class="login-title">
                Welcome Back
            </h2>


            <p class="login-subtitle">
                Enter your account details to continue.
            </p>



            <!-- =================================================
                 ERROR MESSAGE
            ================================================== -->

            <?php if ($error !== ""): ?>

                <div class="error-box">

                    <span class="error-icon">
                        !
                    </span>

                    <span>
                        <?php
                        echo htmlspecialchars(
                            $error
                        );
                        ?>
                    </span>

                </div>

            <?php endif; ?>



            <!-- =================================================
                 LOGIN FORM
            ================================================== -->

            <form
                method="POST"
                action="login.php"
                class="login-form"
                autocomplete="on"
            >


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php
                        echo htmlspecialchars(
                            $email
                        );
                        ?>"
                        placeholder="Enter your email"
                        autocomplete="email"
                        required
                    >

                </div>



                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                </div>



                <!-- LOGIN BUTTON -->

                <button
                    type="submit"
                    class="login-button"
                >
                    LOGIN
                </button>

            </form>



            <!-- =================================================
                 REGISTER
            ================================================== -->

            <p class="register-text">

                Don't have an account?

                <a href="register.php">
                    Create Account
                </a>

            </p>



            <!-- =================================================
                 BACK HOME
            ================================================== -->

            <a
                href="index.php"
                class="back-home"
            >
                ← Back to Home
            </a>



            <!-- =================================================
                 SECURITY
            ================================================== -->

            <div class="secure-note">

                <span>●</span>

                <span>
                    Secure account access
                </span>

            </div>

        </div>

    </section>

</main>


</body>

</html>