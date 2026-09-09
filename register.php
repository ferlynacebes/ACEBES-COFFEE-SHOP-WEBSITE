<?php

session_start();
require_once "config/db.php";

/*
|--------------------------------------------------------------------------
| CUSTOMER REGISTRATION
|--------------------------------------------------------------------------
| Customer accounts are stored in the users table.
| Admin accounts use the separate admin_users table and are never created
| through this page.
*/

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if (isset($_SESSION["admin_id"])) {
    header("Location: admin/dashboard.php");
    exit();
}

$error = "";
$success = "";

$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($name === "" || $email === "" || $password === "" || $confirm_password === "") {

        $error = "Please complete all required fields.";

    } elseif (strlen($name) < 2) {

        $error = "Please enter your full name.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 6) {

        $error = "Password must be at least 6 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | CHECK CUSTOMER EMAIL
            |--------------------------------------------------------------------------
            */

            $check = $conn->prepare(
                "SELECT id
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            if (!$check) {
                throw new Exception("Unable to prepare email check.");
            }

            $check->bind_param("s", $email);
            $check->execute();

            $result = $check->get_result();

            if ($result->num_rows > 0) {

                $error = "This email address is already registered.";

                $check->close();

            } else {

                $check->close();

                /*
                |--------------------------------------------------------------------------
                | HASH PASSWORD
                |--------------------------------------------------------------------------
                */

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                /*
                |--------------------------------------------------------------------------
                | CREATE CUSTOMER
                |--------------------------------------------------------------------------
                */

                $stmt = $conn->prepare(
                    "INSERT INTO users
                        (name, email, password, role)
                     VALUES
                        (?, ?, ?, 'customer')"
                );

                if (!$stmt) {
                    throw new Exception("Unable to prepare registration.");
                }

                $stmt->bind_param(
                    "sss",
                    $name,
                    $email,
                    $hashed_password
                );

                if ($stmt->execute()) {

                    $stmt->close();

                    $success = "Account created successfully! You can now log in.";

                    /*
                    | Do not automatically log the customer in.
                    | This keeps registration separate from login.
                    */

                    $name = "";
                    $email = "";

                } else {

                    $stmt->close();

                    $error = "Registration could not be completed. Please try again.";
                }
            }

        } catch (Throwable $e) {

            /*
            | Keep database details hidden from customers.
            */

            $error = "Something went wrong while creating your account. Please try again.";
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

    <title>Create Account | Acebes Coffee</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>

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

            --success-bg: #eef7f0;
            --success: #39734b;

            --danger-bg: #fff0ec;
            --danger: #a94737;

            --border: rgba(91, 58, 39, .15);

            --shadow: 0 24px 70px rgba(36, 21, 15, .16);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;

            font-family: "DM Sans", Arial, sans-serif;
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

            border: 1px solid rgba(90, 56, 39, .10);
        }

        body::after {
            width: 420px;
            height: 420px;

            right: -220px;
            bottom: -210px;

            border: 1px solid rgba(90, 56, 39, .10);
        }

        /* =========================================================
           REGISTER SHELL
        ========================================================= */

        .register-shell {
            width: min(1060px, 100%);
            min-height: 650px;

            display: grid;
            grid-template-columns: 1.02fr .98fr;

            background: rgba(255, 253, 249, .72);

            border: 1px solid rgba(255, 255, 255, .72);
            border-radius: 30px;

            overflow: hidden;

            box-shadow: var(--shadow);

            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);

            position: relative;
            z-index: 1;
        }

        /* =========================================================
           LEFT BRAND PANEL
        ========================================================= */

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

            right: -120px;
            top: -90px;

            border: 1px solid rgba(255, 255, 255, .09);
            border-radius: 50%;
        }

        .brand-panel::after {
            content: "";

            position: absolute;

            width: 250px;
            height: 250px;

            left: -130px;
            bottom: -100px;

            border: 1px solid rgba(216, 163, 109, .15);
            border-radius: 50%;
        }

        .brand-content,
        .brand-footer {
            position: relative;
            z-index: 2;
        }

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

            font-family: "Playfair Display", Georgia, serif;

            font-size: clamp(42px, 5vw, 66px);

            line-height: .98;
            letter-spacing: -.035em;

            font-weight: 600;
        }

        .brand-title span {
            color: var(--gold);
        }

        .brand-description {
            max-width: 390px;

            margin-top: 24px;

            color: rgba(248, 242, 233, .72);

            font-size: 14px;
            line-height: 1.8;
        }

        .coffee-mark {
            width: 94px;
            height: 94px;

            margin-top: 48px;

            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 50%;

            display: grid;
            place-items: center;

            background: rgba(255, 255, 255, .06);

            box-shadow:
                inset 0 1px 0 rgba(255,255,255,.08);

            font-size: 36px;
        }

        .brand-footer {
            color: rgba(248, 242, 233, .52);

            font-size: 11px;

            letter-spacing: .08em;

            text-transform: uppercase;
        }

        /* =========================================================
           RIGHT REGISTER PANEL
        ========================================================= */

        .register-panel {
            padding: 54px clamp(32px, 5vw, 72px);

            display: flex;
            align-items: center;

            background: rgba(255, 253, 249, .92);
        }

        .register-content {
            width: 100%;
            max-width: 450px;

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
            font-family: "Playfair Display", Georgia, serif;

            font-size: clamp(34px, 4vw, 48px);

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

        /* =========================================================
           ALERTS
        ========================================================= */

        .alert {
            margin-top: 24px;

            padding: 13px 15px;

            border-radius: 12px;

            font-size: 13px;

            line-height: 1.5;
        }

        .alert-error {
            border: 1px solid rgba(169, 71, 55, .16);

            background: var(--danger-bg);

            color: var(--danger);
        }

        .alert-success {
            border: 1px solid rgba(57, 115, 75, .18);

            background: var(--success-bg);

            color: var(--success);
        }

        /* =========================================================
           FORM
        ========================================================= */

        form {
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 21px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;

            gap: 15px;
        }

        label {
            display: block;

            margin-bottom: 9px;

            color: var(--espresso);

            font-size: 12px;
            font-weight: 700;

            letter-spacing: .02em;
        }

        input {
            width: 100%;
            height: 54px;

            padding: 0 16px;

            border: 1px solid var(--border);
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
            border-color: rgba(90, 56, 39, .28);
        }

        input:focus {
            border-color: var(--caramel);

            box-shadow:
                0 0 0 4px rgba(185, 130, 82, .12);
        }

        /* =========================================================
           PASSWORD FIELD
        ========================================================= */

        .password-field {
            position: relative;
        }

        .password-field input {
            padding-right: 52px;
        }

        .toggle-password {
            position: absolute;

            right: 8px;
            top: 50%;

            transform: translateY(-50%);

            width: 38px;
            height: 38px;

            display: grid;
            place-items: center;

            border: 0;

            background: transparent;

            color: var(--caramel);

            cursor: pointer;

            font-size: 16px;

            border-radius: 8px;

            transition: .2s ease;
        }

        .toggle-password:hover {
            background: rgba(185, 130, 82, .09);
        }

        .toggle-password:focus-visible {
            outline: 2px solid var(--caramel);
            outline-offset: 2px;
        }

        .toggle-password svg {
            width: 19px;
            height: 19px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        /* =========================================================
           PASSWORD REQUIREMENT
        ========================================================= */

        .password-hint {
            margin-top: 7px;

            color: #958477;

            font-size: 11px;
        }

        /* =========================================================
           CREATE ACCOUNT BUTTON
        ========================================================= */

        .register-button {
            width: 100%;
            min-height: 54px;

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
                0 12px 25px rgba(36, 21, 15, .17);

            transition:
                transform .2s ease,
                background .2s ease,
                box-shadow .2s ease;
        }

        .register-button:hover {
            background: var(--coffee);

            transform: translateY(-2px);

            box-shadow:
                0 16px 30px rgba(36, 21, 15, .22);
        }

        .register-button:active {
            transform: translateY(0);
        }

        /* =========================================================
           LINKS
        ========================================================= */

        .login-text {
            margin-top: 24px;

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

        .back-home {
            display: block;

            width: fit-content;

            margin: 25px auto 0;

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

            margin-top: 27px;

            color: #9a887b;

            font-size: 11px;
        }

        .secure-note span:first-child {
            color: var(--gold);
        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 850px) {

            .register-shell {
                grid-template-columns: 1fr;

                min-height: auto;
            }

            .brand-panel {
                min-height: 360px;

                padding: 42px;
            }

            .brand-logo {
                margin-bottom: 35px;
            }

            .brand-logo img {
                width: 180px;
                max-height: 75px;
            }

            .brand-title {
                font-size: clamp(42px, 9vw, 58px);
            }

            .coffee-mark {
                margin-top: 30px;
            }

            .brand-footer {
                margin-top: 35px;
            }

            .register-panel {
                padding: 46px 36px;
            }
        }

        @media (max-width: 600px) {

            body {
                padding: 18px 12px;
            }

            .register-shell {
                border-radius: 22px;
            }

            .brand-panel {
                min-height: 330px;

                padding: 32px 25px;
            }

            .brand-logo {
                font-size: 29px;

                margin-bottom: 32px;
            }

            .brand-title {
                font-size: 42px;
            }

            .brand-description {
                font-size: 13px;
            }

            .coffee-mark {
                width: 72px;
                height: 72px;

                margin-top: 25px;

                font-size: 28px;
            }

            .register-panel {
                padding: 38px 24px;
            }

            .form-row {
                grid-template-columns: 1fr;

                gap: 0;
            }

            .register-title {
                font-size: 36px;
            }
        }

        @media (max-width: 400px) {

            .brand-panel {
                padding: 28px 20px;
            }

            .register-panel {
                padding: 32px 19px;
            }

            .register-title {
                font-size: 33px;
            }
        }

    </style>

</head>

<body>

    <main class="register-shell">

        <!-- =====================================================
             LEFT BRAND PANEL
        ====================================================== -->

        <section class="brand-panel">

            <div class="brand-content">

                <a href="index.php" class="brand-logo">
                    <img src="assets/images/logo.png" alt="Acebes Coffee">
                </a>

                <p class="eyebrow">
                    JOIN ACEBES COFFEE
                </p>

                <h1 class="brand-title">
                    Your next
                    <span>favorite</span>
                    cup starts here.
                </h1>

                <p class="brand-description">
                    Create your Acebes Coffee account and enjoy a smoother
                    ordering experience, from choosing your favorite
                    coffee to keeping track of your orders.
                </p>

                <div class="coffee-mark" aria-hidden="true">
                    ☕
                </div>

            </div>

            <p class="brand-footer">
                Coffee, treats, and moments worth remembering.
            </p>

        </section>


        <!-- =====================================================
             RIGHT REGISTER PANEL
        ====================================================== -->

        <section class="register-panel">

            <div class="register-content">

                <p class="register-kicker">
                    CUSTOMER ACCOUNT
                </p>

                <h2 class="register-title">
                    Create your account.
                </h2>

                <p class="register-subtitle">
                    Register once and make ordering at Acebes Coffee
                    simple and convenient.
                </p>


                <?php if ($error !== ""): ?>

                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>

                <?php endif; ?>


                <?php if ($success !== ""): ?>

                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    action=""
                    autocomplete="on"
                >

                    <!-- FULL NAME -->

                    <div class="form-group">

                        <label for="name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?php echo htmlspecialchars($name); ?>"
                            placeholder="Enter your full name"
                            autocomplete="name"
                            maxlength="100"
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
                            value="<?php echo htmlspecialchars($email); ?>"
                            placeholder="you@example.com"
                            autocomplete="email"
                            maxlength="150"
                            required
                        >

                    </div>


                    <!-- PASSWORD ROW -->

                    <div class="form-row">

                        <div class="form-group">

                            <label for="password">
                                Password
                            </label>

                            <div class="password-field">

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="At least 6 characters"
                                    autocomplete="new-password"
                                    minlength="6"
                                    required
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    data-target="password"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                    title="Show password"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path>
                                        <circle cx="12" cy="12" r="2.5"></circle>
                                    </svg>
                                </button>

                            </div>

                            <p class="password-hint">
                                Use at least 6 characters.
                            </p>

                        </div>


                        <!-- CONFIRM PASSWORD -->

                        <div class="form-group">

                            <label for="confirm_password">
                                Confirm Password
                            </label>

                            <div class="password-field">

                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    placeholder="Repeat your password"
                                    autocomplete="new-password"
                                    minlength="6"
                                    required
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    data-target="confirm_password"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                    title="Show password"
                                >
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path>
                                        <circle cx="12" cy="12" r="2.5"></circle>
                                    </svg>
                                </button>

                            </div>

                        </div>

                    </div>


                    <!-- SUBMIT -->

                    <button
                        type="submit"
                        class="register-button"
                    >
                        CREATE ACCOUNT
                    </button>

                </form>


                <p class="login-text">
                    Already have an account?
                    <a href="login.php">
                        Log In
                    </a>
                </p>


                <a
                    href="index.php"
                    class="back-home"
                >
                    ← Back to Home
                </a>


                <div class="secure-note">

                    <span>●</span>

                    <span>
                        Secure customer registration
                    </span>

                </div>

            </div>

        </section>

    </main>


    <script>

        /*
        |--------------------------------------------------------------------------
        | SHOW / HIDE PASSWORD
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(".toggle-password")
            .forEach(function (button) {

                button.addEventListener("click", function () {

                    const targetId = button.getAttribute("data-target");
                    const input = document.getElementById(targetId);

                    if (!input) {
                        return;
                    }

                    const isHidden = input.type === "password";

                    input.type = isHidden ? "text" : "password";

                    button.setAttribute(
                        "aria-label",
                        isHidden ? "Hide password" : "Show password"
                    );

                    button.setAttribute(
                        "title",
                        isHidden ? "Hide password" : "Show password"
                    );

                    button.setAttribute(
                        "aria-pressed",
                        isHidden ? "true" : "false"
                    );

                    button.innerHTML = isHidden
                        ? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.6 6.2A10.8 10.8 0 0 1 12 6c6.5 0 10 6 10 6a18.2 18.2 0 0 1-3.1 3.8"></path><path d="M6.5 8.2C3.8 10 2 12 2 12s3.5 6 10 6c1.6 0 3-.3 4.2-.8"></path><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"></path></svg>'
                        : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>';

                });

            });


        /*
        |--------------------------------------------------------------------------
        | CLIENT-SIDE PASSWORD MATCH CHECK
        |--------------------------------------------------------------------------
        */

        const registerForm = document.querySelector("form");
        const password = document.getElementById("password");
        const confirmPassword = document.getElementById("confirm_password");

        if (registerForm && password && confirmPassword) {

            registerForm.addEventListener("submit", function (event) {

                if (password.value !== confirmPassword.value) {

                    event.preventDefault();

                    confirmPassword.setCustomValidity(
                        "Passwords do not match."
                    );

                    confirmPassword.reportValidity();

                } else {

                    confirmPassword.setCustomValidity("");

                }

            });

            confirmPassword.addEventListener("input", function () {

                if (password.value === confirmPassword.value) {

                    confirmPassword.setCustomValidity("");

                } else {

                    confirmPassword.setCustomValidity(
                        "Passwords do not match."
                    );
                }

            });

        }

    </script>

</body>

</html>
