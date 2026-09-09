<?php

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
| Save contact form submissions into the Acebes Coffee database.
|--------------------------------------------------------------------------
*/
require_once __DIR__ . "/config/db.php";

include __DIR__ . "/includes/header.php";

$message = "";
$message_type = "";

$name = "";
$email = "";
$subject = "";
$user_message = "";


/*
|--------------------------------------------------------------------------
| CONTACT FORM PROCESSING
|--------------------------------------------------------------------------
| Validate the form and save valid submissions to contact_messages.
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $subject = trim($_POST["subject"] ?? "");
    $user_message = trim($_POST["message"] ?? "");

    if (
        $name === "" ||
        $email === "" ||
        $subject === "" ||
        $user_message === ""
    ) {

        $message = "Please complete all fields.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";

    } else {

        $stmt = $conn->prepare("
            INSERT INTO contact_messages
                (name, email, subject, message)
            VALUES
                (?, ?, ?, ?)
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ssss",
                $name,
                $email,
                $subject,
                $user_message
            );

            if ($stmt->execute()) {

                $message = "Thank you, " .
                    htmlspecialchars($name, ENT_QUOTES, "UTF-8") .
                    "! Your message has been sent successfully.";

                $message_type = "success";

                // Clear fields after successful submission.
                $name = "";
                $email = "";
                $subject = "";
                $user_message = "";

            } else {

                $message = "Sorry, your message could not be sent right now. Please try again.";
                $message_type = "error";
            }

            $stmt->close();

        } else {

            $message = "Sorry, the message service is temporarily unavailable.";
            $message_type = "error";
        }
    }
}
?>


<!-- =========================================================
     PAGE HERO
========================================================= -->

<section class="page-hero">

    <div class="container">

        <span class="section-label">
            GET IN TOUCH
        </span>

        <h1>
            Contact Us
        </h1>

        <p>
            We'd love to hear from you.
            Visit Acebes Coffee Shop or
            send us a message.
        </p>

    </div>

</section>


<!-- =========================================================
     CONTACT SECTION
========================================================= -->

<section class="section contact-section">

    <div class="container">

        <div class="contact-grid">


            <!-- =================================================
                 CONTACT INFORMATION
            ================================================== -->

            <div class="contact-info">

                <span class="section-label">
                    GET IN TOUCH
                </span>

                <h2>
                    Visit Acebes
                </h2>

                <p>
                    Whether you're looking for your
                    favorite cup of coffee or simply
                    want a comfortable place to relax,
                    we'd be happy to welcome you.
                </p>


                <!-- =================================================
                     CONTACT DETAILS
                ================================================== -->

                <div class="contact-details">


                    <!-- LOCATION -->

                    <div class="contact-detail">

                        <div
                            class="contact-detail-icon"
                            aria-hidden="true"
                        >
                            📍
                        </div>

                        <div>

                            <h3>
                                LOCATION
                            </h3>

                            <p>
                                Acebes Coffee Shop
                            </p>

                            <p>
                                Your local coffee destination
                            </p>

                        </div>

                    </div>


                    <!-- PHONE -->

                    <div class="contact-detail">

                        <div
                            class="contact-detail-icon"
                            aria-hidden="true"
                        >
                            ☎
                        </div>

                        <div>

                            <h3>
                                PHONE
                            </h3>

                            <p>
                                Contact us for inquiries
                                and orders.
                            </p>

                        </div>

                    </div>


                    <!-- EMAIL -->

                    <div class="contact-detail">

                        <div
                            class="contact-detail-icon"
                            aria-hidden="true"
                        >
                            ✉
                        </div>

                        <div>

                            <h3>
                                EMAIL
                            </h3>

                            <p>
                                hello@acebescoffee.com
                            </p>

                        </div>

                    </div>


                    <!-- OPENING HOURS -->

                    <div class="contact-detail">

                        <div
                            class="contact-detail-icon"
                            aria-hidden="true"
                        >
                            🕐
                        </div>

                        <div>

                            <h3>
                                OPENING HOURS
                            </h3>

                            <p>
                                Monday – Sunday
                            </p>

                            <p>
                                8:00 AM – 8:00 PM
                            </p>

                        </div>

                    </div>


                </div>

            </div>


            <!-- =================================================
                 CONTACT FORM
            ================================================== -->

            <div class="contact-form">

                <span class="section-label">
                    SEND A MESSAGE
                </span>

                <h2>
                    We'd Love to Hear From You
                </h2>


                <!-- =================================================
                     FORM MESSAGE
                ================================================== -->

                <?php if (!empty($message)): ?>

                    <div
                        class="form-message <?php echo $message_type; ?>"
                        role="alert"
                    >

                        <?php echo htmlspecialchars($message); ?>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     FORM
                ================================================== -->

                <form
                    action="contact.php"
                    method="POST"
                >


                    <!-- NAME -->

                    <div class="form-group">

                        <label for="name">
                            Name
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            placeholder="Your name"
                            value="<?php echo htmlspecialchars($name, ENT_QUOTES, "UTF-8"); ?>"
                            autocomplete="name"
                            required
                        >

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label for="email">
                            Email
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="you@example.com"
                            value="<?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?>"
                            autocomplete="email"
                            required
                        >

                    </div>


                    <!-- SUBJECT -->

                    <div class="form-group">

                        <label for="subject">
                            Subject
                        </label>

                        <input
                            type="text"
                            id="subject"
                            name="subject"
                            placeholder="What is this about?"
                            value="<?php echo htmlspecialchars($subject, ENT_QUOTES, "UTF-8"); ?>"
                            required
                        >

                    </div>


                    <!-- MESSAGE -->

                    <div class="form-group">

                        <label for="message">
                            Message
                        </label>

                        <textarea
                            id="message"
                            name="message"
                            placeholder="Write your message here..."
                            required
                        ><?php echo htmlspecialchars($user_message, ENT_QUOTES, "UTF-8"); ?></textarea>

                    </div>


                    <!-- SUBMIT -->

                    <button
                        type="submit"
                        class="btn btn-primary form-submit"
                    >
                        SEND MESSAGE
                    </button>


                </form>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     VISIT CTA
========================================================= -->

<section class="section contact-cta-section">

    <div class="container">

        <div class="contact-cta-content">

            <span class="section-label">
                YOUR COFFEE MOMENT
            </span>

            <h2 class="section-title">
                Come and Stay Awhile
            </h2>

            <p class="section-description">
                Great coffee tastes even better
                when enjoyed in a warm and
                welcoming place.
            </p>

            <a
                href="menu.php"
                class="btn btn-primary"
            >
                VIEW OUR MENU
            </a>

        </div>

    </div>

</section>


<!-- =========================================================
     FOOTER
========================================================= -->

<?php
include 'includes/footer.php';
?>