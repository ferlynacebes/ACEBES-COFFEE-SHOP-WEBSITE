<?php

session_start();

require_once "config/db.php";


/* =========================================================
   IF ALREADY LOGGED IN
========================================================= */

if (isset($_SESSION["user_id"])) {

    if (($_SESSION["user_role"] ?? "") === "admin") {
        header("Location: admin/dashboard.php");
        exit();
    }

    header("Location: index.php");
    exit();
}


/* =========================================================
   VARIABLES
========================================================= */

$error = "";
$success = "";

$name = "";
$email = "";


/* =========================================================
   REGISTRATION PROCESS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";


    /* =====================================================
       VALIDATION
    ===================================================== */

    if ($name === "" || $email === "" || $password === "" || $confirm_password === "") {

        $error = "Please fill in all fields.";

    } elseif (strlen($name) < 2) {

        $error = "Please enter a valid name.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 6) {

        $error = "Password must be at least 6 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {


        /* =================================================
           CHECK IF EMAIL ALREADY EXISTS
        ================================================= */

        $checkStmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE email = ?
             LIMIT 1"
        );


        if (!$checkStmt) {

            $error = "Unable to process registration. Please try again.";

        } else {

            $checkStmt->bind_param("s", $email);

            $checkStmt->execute();

            $checkResult = $checkStmt->get_result();


            if ($checkResult->num_rows > 0) {

                $error = "An account with this email already exists.";

                $checkStmt->close();

            } else {

                $checkStmt->close();


                /* =========================================
                   HASH PASSWORD
                ========================================= */

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


                /* =========================================
                   CREATE CUSTOMER ACCOUNT
                ========================================= */

                $role = "customer";


                $insertStmt = $conn->prepare(
                    "INSERT INTO users
                    (name, email, password, role)
                    VALUES (?, ?, ?, ?)"
                );


                if (!$insertStmt) {

                    $error =
                        "Unable to create your account. Please try again.";

                } else {

                    $insertStmt->bind_param(
                        "ssss",
                        $name,
                        $email,
                        $hashedPassword,
                        $role
                    );


                    if ($insertStmt->execute()) {

                        $insertStmt->close();


                        /*
                        =========================================
                           SUCCESS
                        =========================================
                        */

                        $success =
                            "Account created successfully! You can now log in.";

                        $name = "";
                        $email = "";


                    } else {

                        if ($conn->errno === 1062) {

                            $error =
                                "An account with this email already exists.";

                        } else {

                            $error =
                                "Unable to create your account. Please try again.";
                        }


                        $insertStmt->close();
                    }
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

    <title>Register | Cafelia</title>


    <style>

        @import url(
            'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap'
        );


        :root {

            --espresso: #24150f;
            --espresso-2: #321d14;
            --coffee: #5a3827;
            --caramel: #b98252;
            --gold: #d8a36d;

            --cream: #f8f2e9;
            --cream-2: #efe4d4;
            --white: #fffdf9;

            --muted: #79695d;

            --danger-bg: #fff0ec;
            --danger: #a94737;

            --success-bg: #eef8ef;
            --success: #47734d;

            --border: rgba(91, 58, 39, .15);

            --shadow:
                0 24px 70px rgba(36, 21, 15, .16);
        }


        * {

            box-sizing: border-box;

            margin: 0;

            padding: 0;
        }


        body {

            min-height: 100vh;

            font-family:
                "DM Sans",
                Arial,
                sans-serif;

            color: var(--espresso);

            background:

                radial-gradient(
                    circle at 15% 15%,
                    rgba(216, 163, 109, .18),
                    transparent 28%
                ),

                radial-gradient(
                    circle at 85% 85%,
                    rgba(185, 130, 82, .13),
                    transparent 30%
                ),

                linear-gradient(
                    135deg,
                    #fbf6ee 0%,
                    #f1e6d8 48%,
                    #ead8c5 100%
                );

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 34px 20px;

            position: relative;

            overflow-x: hidden;
        }


        body::before,
        body::after {

            content: "";

            position: fixed;

            border-radius: 50%;

            pointer-events: none;

            filter: blur(2px);
        }


        body::before {

            width: 360px;

            height: 360px;

            left: -180px;

            top: -150px;

            border:
                1px solid
                rgba(90, 56, 39, .10);
        }


        body::after {

            width: 420px;

            height: 420px;

            right: -220px;

            bottom: -210px;

            border:
                1px solid
                rgba(90, 56, 39, .10);
        }


        /* =====================================================
           REGISTER SHELL
        ===================================================== */

        .register-shell {

            width: min(1060px, 100%);

            min-height: 680px;

            display: grid;

            grid-template-columns:
                1.02fr .98fr;

            background:
                rgba(255, 253, 249, .72);

            border:
                1px solid
                rgba(255, 255, 255, .72);

            border-radius: 30px;

            overflow: hidden;

            box-shadow: var(--shadow);

            backdrop-filter: blur(18px);

            -webkit-backdrop-filter: blur(18px);

            position: relative;

            z-index: 1;
        }


        /* =====================================================
           LEFT BRAND PANEL
        ===================================================== */

        .brand-panel {

            position: relative;

            padding: 54px;

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            color: var(--cream);

            background:
                linear-gradient(
                    145deg,
                    rgba(36, 21, 15, .98),
                    rgba(76, 45, 31, .97)
                );

            overflow: hidden;
        }


        .brand-panel::before {

            content: "";

            position: absolute;

            width: 310px;

            height: 310px;

            border:
                1px solid
                rgba(255, 255, 255, .09);

            border-radius: 50%;

            right: -120px;

            top: -90px;
        }


        .brand-panel::after {

            content: "";

            position: absolute;

            width: 250px;

            height: 250px;

            border:
                1px solid
                rgba(216, 163, 109, .15);

            border-radius: 50%;

            left: -130px;

            bottom: -100px;
        }


        .brand-content,
        .brand-footer {

            position: relative;

            z-index: 2;
        }


        .brand-logo {

            display: inline-block;

            width: fit-content;

            color: #f7eadb;

            text-decoration: none;

            font-family:
                "Playfair Display",
                Georgia,
                serif;

            font-size: 34px;

            font-weight: 700;

            letter-spacing: .13em;

            margin-bottom: 58px;
        }


        .eyebrow {

            font-size: 10px;

            font-weight: 700;

            letter-spacing: .24em;

            text-transform: uppercase;

            color: var(--gold);

            margin-bottom: 16px;
        }


        .brand-title {

            max-width: 430px;

            font-family:
                "Playfair Display",
                Georgia,
                serif;

            font-size:
                clamp(42px, 5vw, 66px);

            line-height: .98;

            letter-spacing: -.035em;

            font-weight: 600;
        }


        .brand-description {

            max-width: 390px;

            margin-top: 24px;

            color:
                rgba(248, 242, 233, .72);

            font-size: 14px;

            line-height: 1.8;
        }


        .coffee-mark {

            width: 94px;

            height: 94px;

            margin-top: 48px;

            border:
                1px solid
                rgba(255, 255, 255, .16);

            border-radius: 50%;

            display: grid;

            place-items: center;

            background:
                rgba(255, 255, 255, .06);

            box-shadow:
                inset 0 1px 0
                rgba(255,255,255,.08);

            font-size: 36px;
        }


        .brand-footer {

            color:
                rgba(248, 242, 233, .52);

            font-size: 11px;

            letter-spacing: .08em;

            text-transform: uppercase;
        }


        /* =====================================================
           RIGHT REGISTER PANEL
        ===================================================== */

        .register-panel {

            padding:
                50px
                clamp(32px, 5vw, 72px);

            display: flex;

            align-items: center;

            background:
                rgba(255, 253, 249, .92);
        }


        .register-content {

            width: 100%;

            max-width: 420px;

            margin: 0 auto;
        }


        .register-kicker {

            color: var(--caramel);

            font-size: 10px;

            font-weight: 800;

            letter-spacing: .22em;

            text-transform: uppercase;

            margin-bottom: 13px;
        }


        .register-title {

            font-family:
                "Playfair Display",
                Georgia,
                serif;

            font-size:
                clamp(34px, 4vw, 48px);

            line-height: 1.05;

            font-weight: 600;

            letter-spacing: -.025em;
        }


        .register-subtitle {

            color: var(--muted);

            margin-top: 12px;

            font-size: 14px;

            line-height: 1.7;
        }


        /* =====================================================
           MESSAGES
        ===================================================== */

        .message {

            margin-top: 22px;

            padding: 13px 15px;

            border-radius: 12px;

            font-size: 13px;

            line-height: 1.5;
        }


        .error-message {

            border:
                1px solid
                rgba(169, 71, 55, .16);

            background: var(--danger-bg);

            color: var(--danger);
        }


        .success-message {

            border:
                1px solid
                rgba(71, 115, 77, .16);

            background: var(--success-bg);

            color: var(--success);
        }


        /* =====================================================
           FORM
        ===================================================== */

        form {

            margin-top: 26px;
        }


        .form-group {

            margin-bottom: 17px;
        }


        label {

            display: block;

            margin-bottom: 8px;

            color: var(--espresso);

            font-size: 12px;

            font-weight: 700;

            letter-spacing: .02em;
        }


        input {

            width: 100%;

            height: 51px;

            padding: 0 16px;

            border:
                1px solid
                var(--border);

            border-radius: 12px;

            outline: none;

            background: #fffefa;

            color: var(--espresso);

            font-family: inherit;

            font-size: 14px;

            transition: .2s ease;
        }


        input::placeholder {

            color: #aa9a8d;
        }


        input:hover {

            border-color:
                rgba(90, 56, 39, .28);
        }


        input:focus {

            border-color: var(--caramel);

            box-shadow:
                0 0 0 4px
                rgba(185, 130, 82, .12);
        }


        /* =====================================================
           REGISTER BUTTON
        ===================================================== */

        .register-button {

            width: 100%;

            min-height: 53px;

            border: 0;

            border-radius: 12px;

            margin-top: 5px;

            background: var(--espresso);

            color: #fffaf3;

            font-family: inherit;

            font-size: 12px;

            font-weight: 800;

            letter-spacing: .16em;

            text-transform: uppercase;

            cursor: pointer;

            box-shadow:
                0 12px 25px
                rgba(36, 21, 15, .17);

            transition:
                transform .2s ease,
                background .2s ease,
                box-shadow .2s ease;
        }


        .register-button:hover {

            background: var(--coffee);

            transform: translateY(-2px);

            box-shadow:
                0 16px 30px
                rgba(36, 21, 15, .22);
        }


        .register-button:active {

            transform: translateY(0);
        }


        /* =====================================================
           LOGIN LINK
        ===================================================== */

        .login-text {

            margin-top: 21px;

            text-align: center;

            color: var(--muted);

            font-size: 13px;
        }


        .login-text a {

            color: var(--coffee);

            font-weight: 800;

            text-decoration: none;

            margin-left: 4px;
        }


        .login-text a:hover {

            color: var(--caramel);
        }


        /* =====================================================
           BACK HOME
        ===================================================== */

        .back-home {

            display: block;

            width: fit-content;

            margin: 20px auto 0;

            color: #8b786a;

            font-size: 12px;

            font-weight: 600;

            text-decoration: none;

            transition: color .2s ease;
        }


        .back-home:hover {

            color: var(--coffee);
        }


        .secure-note {

            display: flex;

            justify-content: center;

            gap: 7px;

            margin-top: 20px;

            color: #a08f81;

            font-size: 10px;

            letter-spacing: .08em;

            text-transform: uppercase;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 820px) {

            body {

                padding: 20px;
            }


            .register-shell {

                grid-template-columns: 1fr;

                max-width: 560px;

                min-height: auto;

                border-radius: 24px;
            }


            .brand-panel {

                min-height: 280px;

                padding: 34px;
            }


            .brand-logo {

                margin-bottom: 38px;

                font-size: 29px;
            }


            .brand-title {

                font-size: 43px;
            }


            .brand-description,
            .coffee-mark {

                display: none;
            }


            .brand-footer {

                margin-top: 40px;
            }


            .register-panel {

                padding:
                    40px
                    34px
                    44px;
            }
        }


        @media (max-width: 480px) {

            body {

                padding: 12px;
            }


            .register-shell {

                border-radius: 20px;
            }


            .brand-panel {

                min-height: 240px;

                padding:
                    28px
                    24px;
            }


            .brand-logo {

                font-size: 25px;

                margin-bottom: 32px;
            }


            .brand-title {

                font-size: 38px;
            }


            .register-panel {

                padding:
                    32px
                    23px
                    36px;
            }


            .register-title {

                font-size: 36px;
            }


            input,
            .register-button {

                height: 51px;
            }
        }


        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {

                scroll-behavior: auto !important;

                transition: none !important;
            }
        }

    </style>

</head>


<body>


<main class="register-shell">


    <!-- =====================================================
         BRAND PANEL
    ====================================================== -->

    <section class="brand-panel">

        <div class="brand-content">

            <a
                href="index.php"
                class="brand-logo"
                aria-label="Cafelia Home"
            >
                CAFELIA
            </a>


            <div class="eyebrow">
                Join Cafelia
            </div>


            <h1 class="brand-title">

                Make every coffee moment yours.

            </h1>


            <p class="brand-description">

                Create your Cafelia account and enjoy
                a smoother way to discover your favorites,
                manage your cart, and place your orders.

            </p>


            <div
                class="coffee-mark"
                aria-hidden="true"
            >
                ☕
            </div>

        </div>


        <div class="brand-footer">

            Coffee, treats, and good moments.

        </div>

    </section>



    <!-- =====================================================
         REGISTER PANEL
    ====================================================== -->

    <section class="register-panel">

        <div class="register-content">


            <div class="register-kicker">

                Cafelia Account

            </div>


            <h2 class="register-title">

                Create account.

            </h2>


            <p class="register-subtitle">

                Sign up to get started with Cafelia.

            </p>


            <!-- =================================================
                 ERROR MESSAGE
            ================================================= -->

            <?php if ($error !== ""): ?>

                <div class="message error-message">

                    <?php
                    echo htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 SUCCESS MESSAGE
            ================================================= -->

            <?php if ($success !== ""): ?>

                <div class="message success-message">

                    <?php
                    echo htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 REGISTER FORM
            ================================================= -->

            <form
                action="register.php"
                method="POST"
            >


                <!-- NAME -->

                <div class="form-group">

                    <label for="name">
                        Full Name
                    </label>


                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Enter your full name"
                        value="<?php
                            echo htmlspecialchars(
                                $name,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
                        autocomplete="name"
                        minlength="2"
                        required
                    >

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>


                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        value="<?php
                            echo htmlspecialchars(
                                $email,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                        ?>"
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
                        placeholder="Create a password"
                        autocomplete="new-password"
                        minlength="6"
                        required
                    >

                </div>


                <!-- CONFIRM PASSWORD -->

                <div class="form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>


                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm your password"
                        autocomplete="new-password"
                        minlength="6"
                        required
                    >

                </div>


                <!-- REGISTER BUTTON -->

                <button
                    type="submit"
                    class="register-button"
                >

                    Create Account

                </button>

            </form>


            <!-- =================================================
                 LOGIN LINK
            ================================================== -->

            <p class="login-text">

                Already have an account?

                <a href="login.php">
                    Login
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


            <div class="secure-note">

                <span>●</span>

                <span>
                    Secure account registration
                </span>

            </div>


        </div>

    </section>


</main>


</body>

</html>