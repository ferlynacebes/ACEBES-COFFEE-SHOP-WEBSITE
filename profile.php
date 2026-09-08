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
   REQUIRE LOGIN
========================================================= */

if (empty($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}


$userId = (int) $_SESSION["user_id"];

$successMessage = "";
$errorMessage = "";


/* =========================================================
   GET CURRENT USER
========================================================= */

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        role,
        created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

$user = $result->fetch_assoc();

$stmt->close();


/* =========================================================
   USER NOT FOUND
========================================================= */

if (!$user) {

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit;
}


/* =========================================================
   UPDATE PROFILE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";


    /* =====================================================
       UPDATE PERSONAL INFORMATION
    ===================================================== */

    if ($action === "update_profile") {

        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");


        /* -----------------------------------------------
           VALIDATE NAME
        ------------------------------------------------ */

        if ($name === "") {

            $errorMessage = "Please enter your full name.";

        } elseif (strlen($name) < 2) {

            $errorMessage = "Your name must contain at least 2 characters.";

        }


        /* -----------------------------------------------
           VALIDATE EMAIL
        ------------------------------------------------ */

        elseif ($email === "") {

            $errorMessage = "Please enter your email address.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $errorMessage = "Please enter a valid email address.";

        }


        /* -----------------------------------------------
           CHECK EMAIL
        ------------------------------------------------ */

        if ($errorMessage === "") {

            $stmt = $conn->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                AND id != ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "si",
                $email,
                $userId
            );

            $stmt->execute();

            $emailResult = $stmt->get_result();

            if ($emailResult->num_rows > 0) {

                $errorMessage =
                    "That email address is already being used.";

            }

            $stmt->close();
        }


        /* -----------------------------------------------
           UPDATE DATABASE
        ------------------------------------------------ */

        if ($errorMessage === "") {

            $stmt = $conn->prepare("
                UPDATE users
                SET
                    name = ?,
                    email = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "ssi",
                $name,
                $email,
                $userId
            );


            if ($stmt->execute()) {

                /* Update session */

                $_SESSION["user_name"] = $name;
                $_SESSION["user_email"] = $email;

                /* Update displayed data */

                $user["name"] = $name;
                $user["email"] = $email;

                $successMessage =
                    "Your profile has been updated successfully.";

            } else {

                $errorMessage =
                    "Something went wrong while updating your profile.";

            }

            $stmt->close();
        }
    }


    /* =====================================================
       CHANGE PASSWORD
    ===================================================== */

    elseif ($action === "change_password") {

        $currentPassword =
            $_POST["current_password"] ?? "";

        $newPassword =
            $_POST["new_password"] ?? "";

        $confirmPassword =
            $_POST["confirm_password"] ?? "";


        /* -----------------------------------------------
           VALIDATION
        ------------------------------------------------ */

        if ($currentPassword === "") {

            $errorMessage =
                "Please enter your current password.";

        } elseif ($newPassword === "") {

            $errorMessage =
                "Please enter a new password.";

        } elseif (strlen($newPassword) < 6) {

            $errorMessage =
                "Your new password must contain at least 6 characters.";

        } elseif ($newPassword !== $confirmPassword) {

            $errorMessage =
                "The new passwords do not match.";

        }


        /* -----------------------------------------------
           GET PASSWORD
        ------------------------------------------------ */

        if ($errorMessage === "") {

            $stmt = $conn->prepare("
                SELECT password
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $userId
            );

            $stmt->execute();

            $passwordResult =
                $stmt->get_result();

            $passwordData =
                $passwordResult->fetch_assoc();

            $stmt->close();


            /* -------------------------------------------
               VERIFY CURRENT PASSWORD
            ------------------------------------------- */

            if (
                !$passwordData ||
                !password_verify(
                    $currentPassword,
                    $passwordData["password"]
                )
            ) {

                $errorMessage =
                    "Your current password is incorrect.";

            }

        }


        /* -----------------------------------------------
           SAVE NEW PASSWORD
        ------------------------------------------------ */

        if ($errorMessage === "") {

            $hashedPassword =
                password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );


            $stmt = $conn->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "si",
                $hashedPassword,
                $userId
            );


            if ($stmt->execute()) {

                $successMessage =
                    "Your password has been changed successfully.";

            } else {

                $errorMessage =
                    "Something went wrong while changing your password.";

            }

            $stmt->close();
        }
    }
}


/* =========================================================
   ACCOUNT INITIAL
========================================================= */

$profileInitial =
    strtoupper(
        substr(
            trim($user["name"]),
            0,
            1
        )
    );

if ($profileInitial === "") {
    $profileInitial = "U";
}


/* =========================================================
   ACCOUNT DATE
========================================================= */

$createdDate = "N/A";

if (!empty($user["created_at"])) {

    $timestamp =
        strtotime($user["created_at"]);

    if ($timestamp !== false) {

        $createdDate =
            date(
                "F d, Y",
                $timestamp
            );

    }
}


/* =========================================================
   PAGE
========================================================= */

include "includes/header.php";

?>

<style>

/* =========================================================
   PROFILE PAGE
========================================================= */

.profile-page {
    min-height: 80vh;

    padding:
        80px 20px
        100px;

    background:
        linear-gradient(
            180deg,
            #F5EFE6 0%,
            #FFFFFF 100%
        );
}


/* =========================================================
   CONTAINER
========================================================= */

.profile-container {
    width: 100%;
    max-width: 1050px;

    margin: 0 auto;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.profile-heading {
    text-align: center;

    margin-bottom: 45px;
}


.profile-heading .section-label {
    display: inline-block;

    margin-bottom: 10px;

    color: #C49A6C;

    font-size: 12px;
    font-weight: 800;

    letter-spacing: 2px;

    text-transform: uppercase;
}


.profile-heading h1 {
    margin: 0;

    color: #2A1A13;

    font-family: 'Montserrat', sans-serif;

    font-size: clamp(
        32px,
        5vw,
        48px
    );

    font-weight: 800;
}


.profile-heading p {
    max-width: 600px;

    margin:
        12px auto
        0;

    color: #806F63;

    font-size: 14px;

    line-height: 1.7;
}


/* =========================================================
   ALERTS
========================================================= */

.profile-alert {
    max-width: 850px;

    margin:
        0 auto
        25px;

    padding: 14px 18px;

    border-radius: 12px;

    font-size: 13px;
    font-weight: 600;
}


.profile-success {
    background: #EDF7EE;

    color: #2E6B35;

    border: 1px solid #CDE5D0;
}


.profile-error {
    background: #FFF0EE;

    color: #963E36;

    border: 1px solid #F0CCC7;
}


/* =========================================================
   PROFILE GRID
========================================================= */

.profile-grid {
    display: grid;

    grid-template-columns:
        330px
        1fr;

    gap: 25px;

    align-items: start;
}


/* =========================================================
   PROFILE CARD
========================================================= */

.profile-card {
    background: #FFFFFF;

    border:
        1px solid
        rgba(75, 46, 32, 0.08);

    border-radius: 20px;

    padding: 30px;

    box-shadow:
        0 15px 45px
        rgba(42, 26, 19, 0.08);
}


/* =========================================================
   PROFILE OVERVIEW
========================================================= */

.profile-overview {
    text-align: center;
}


.profile-avatar-large {
    width: 95px;
    height: 95px;

    margin:
        0 auto
        18px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            #4B2E20,
            #7A513B
        );

    color: #F5EFE6;

    font-size: 34px;
    font-weight: 800;

    box-shadow:
        0 10px 25px
        rgba(75, 46, 32, 0.2);
}


.profile-overview h2 {
    margin: 0;

    color: #2A1A13;

    font-size: 20px;
    font-weight: 800;
}


.profile-overview .email {
    margin-top: 6px;

    color: #8A7568;

    font-size: 12px;

    word-break: break-word;
}


/* =========================================================
   ROLE BADGE
========================================================= */

.role-badge {
    display: inline-flex;
    align-items: center;

    margin-top: 15px;

    padding:
        7px 14px;

    border-radius: 50px;

    background: #F5EFE6;

    color: #4B2E20;

    font-size: 10px;
    font-weight: 800;

    letter-spacing: 1px;

    text-transform: uppercase;
}


/* =========================================================
   ACCOUNT DETAILS
========================================================= */

.account-details {
    margin-top: 28px;

    padding-top: 22px;

    border-top:
        1px solid
        rgba(75, 46, 32, 0.08);
}


.account-detail {
    display: flex;

    justify-content: space-between;

    gap: 15px;

    padding: 10px 0;

    font-size: 12px;
}


.account-detail span:first-child {
    color: #8A7568;
}


.account-detail strong {
    color: #4B2E20;

    text-align: right;
}


/* =========================================================
   RIGHT SIDE
========================================================= */

.profile-settings {
    display: grid;

    gap: 25px;
}


/* =========================================================
   CARD HEADER
========================================================= */

.profile-card-header {
    margin-bottom: 25px;
}


.profile-card-header h2 {
    margin: 0;

    color: #2A1A13;

    font-size: 20px;
    font-weight: 800;
}


.profile-card-header p {
    margin:
        6px 0
        0;

    color: #8A7568;

    font-size: 12px;

    line-height: 1.6;
}


/* =========================================================
   FORM
========================================================= */

.profile-form-group {
    margin-bottom: 18px;
}


.profile-form-group label {
    display: block;

    margin-bottom: 7px;

    color: #4B2E20;

    font-size: 11px;
    font-weight: 800;

    letter-spacing: 0.4px;
}


.profile-form-group input {
    width: 100%;

    box-sizing: border-box;

    padding:
        13px 14px;

    border:
        1px solid
        #E4D8CC;

    border-radius: 10px;

    background: #FFFCF9;

    color: #2A1A13;

    font-family: 'Montserrat', sans-serif;

    font-size: 12px;

    outline: none;

    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}


.profile-form-group input:focus {
    border-color: #C49A6C;

    box-shadow:
        0 0 0 3px
        rgba(196, 154, 108, 0.12);
}


/* =========================================================
   BUTTON
========================================================= */

.profile-submit-btn {
    width: 100%;

    border: none;

    padding:
        14px 20px;

    border-radius: 10px;

    background: #4B2E20;

    color: #FFFFFF;

    font-family: 'Montserrat', sans-serif;

    font-size: 11px;
    font-weight: 800;

    letter-spacing: 0.8px;

    cursor: pointer;

    transition:
        background 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
}


.profile-submit-btn:hover {
    background: #2A1A13;

    transform: translateY(-2px);

    box-shadow:
        0 8px 20px
        rgba(75, 46, 32, 0.18);
}


/* =========================================================
   DIVIDER
========================================================= */

.profile-divider {
    height: 1px;

    margin:
        5px 0
        5px;

    background:
        rgba(75, 46, 32, 0.08);
}


/* =========================================================
   PASSWORD NOTE
========================================================= */

.password-note {
    margin-top: 8px;

    color: #9A877A;

    font-size: 10px;

    line-height: 1.5;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 800px) {

    .profile-grid {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 480px) {

    .profile-page {
        padding:
            60px 15px
            80px;
    }


    .profile-card {
        padding: 22px;
    }


    .profile-heading {
        margin-bottom: 30px;
    }


    .profile-overview {
        padding: 25px 20px;
    }

}

</style>


<!-- =========================================================
     PROFILE PAGE
========================================================= -->

<main class="profile-page">

    <div class="profile-container">


        <!-- =================================================
             PAGE HEADING
        ================================================== -->

        <div class="profile-heading">

            <span class="section-label">
                Your Account
            </span>

            <h1>
                My Profile
            </h1>

            <p>
                Manage your Acebes Coffee Shop account
                information and preferences.
            </p>

        </div>


        <!-- =================================================
             ALERTS
        ================================================== -->

        <?php if ($successMessage !== ""): ?>

            <div class="profile-alert profile-success">
                <?php
                echo htmlspecialchars($successMessage);
                ?>
            </div>

        <?php endif; ?>


        <?php if ($errorMessage !== ""): ?>

            <div class="profile-alert profile-error">
                <?php
                echo htmlspecialchars($errorMessage);
                ?>
            </div>

        <?php endif; ?>


        <!-- =================================================
             PROFILE GRID
        ================================================== -->

        <div class="profile-grid">


            <!-- =============================================
                 LEFT PROFILE OVERVIEW
            ============================================== -->

            <aside class="profile-card profile-overview">

                <div class="profile-avatar-large">

                    <?php
                    echo htmlspecialchars(
                        $profileInitial
                    );
                    ?>

                </div>


                <h2>

                    <?php
                    echo htmlspecialchars(
                        $user["name"]
                    );
                    ?>

                </h2>


                <div class="email">

                    <?php
                    echo htmlspecialchars(
                        $user["email"]
                    );
                    ?>

                </div>


                <div class="role-badge">

                    <?php
                    echo htmlspecialchars(
                        $user["role"]
                    );
                    ?>

                </div>


                <!-- ACCOUNT DETAILS -->

                <div class="account-details">

                    <div class="account-detail">

                        <span>
                            Account ID
                        </span>

                        <strong>
                            #<?php
                            echo (int) $user["id"];
                            ?>
                        </strong>

                    </div>


                    <div class="account-detail">

                        <span>
                            Member Since
                        </span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $createdDate
                            );
                            ?>
                        </strong>

                    </div>


                    <div class="account-detail">

                        <span>
                            Account Type
                        </span>

                        <strong>
                            <?php
                            echo htmlspecialchars(
                                ucfirst(
                                    $user["role"]
                                )
                            );
                            ?>
                        </strong>

                    </div>

                </div>

            </aside>



            <!-- =============================================
                 RIGHT SETTINGS
            ============================================== -->

            <div class="profile-settings">


                <!-- =========================================
                     PERSONAL INFORMATION
                ========================================== -->

                <section class="profile-card">

                    <div class="profile-card-header">

                        <h2>
                            Personal Information
                        </h2>

                        <p>
                            Update the name and email
                            connected to your account.
                        </p>

                    </div>


                    <form
                        method="POST"
                        action="profile.php"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="update_profile"
                        >


                        <!-- NAME -->

                        <div class="profile-form-group">

                            <label for="name">
                                FULL NAME
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="<?php
                                echo htmlspecialchars(
                                    $user["name"]
                                );
                                ?>"
                                required
                            >

                        </div>


                        <!-- EMAIL -->

                        <div class="profile-form-group">

                            <label for="email">
                                EMAIL ADDRESS
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?php
                                echo htmlspecialchars(
                                    $user["email"]
                                );
                                ?>"
                                required
                            >

                        </div>


                        <button
                            type="submit"
                            class="profile-submit-btn"
                        >
                            SAVE PROFILE
                        </button>

                    </form>

                </section>



                <!-- =========================================
                     CHANGE PASSWORD
                ========================================== -->

                <section class="profile-card">

                    <div class="profile-card-header">

                        <h2>
                            Change Password
                        </h2>

                        <p>
                            Keep your account secure by
                            using a strong password.
                        </p>

                    </div>


                    <form
                        method="POST"
                        action="profile.php"
                    >

                        <input
                            type="hidden"
                            name="action"
                            value="change_password"
                        >


                        <!-- CURRENT PASSWORD -->

                        <div class="profile-form-group">

                            <label for="current_password">
                                CURRENT PASSWORD
                            </label>

                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                required
                            >

                        </div>


                        <!-- NEW PASSWORD -->

                        <div class="profile-form-group">

                            <label for="new_password">
                                NEW PASSWORD
                            </label>

                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                minlength="6"
                                required
                            >

                        </div>


                        <!-- CONFIRM PASSWORD -->

                        <div class="profile-form-group">

                            <label for="confirm_password">
                                CONFIRM NEW PASSWORD
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                minlength="6"
                                required
                            >

                            <div class="password-note">
                                Password must contain at least
                                6 characters.
                            </div>

                        </div>


                        <button
                            type="submit"
                            class="profile-submit-btn"
                        >
                            CHANGE PASSWORD
                        </button>

                    </form>

                </section>

            </div>

        </div>

    </div>

</main>


<?php

/* =========================================================
   FOOTER
========================================================= */

include "includes/footer.php";

?>