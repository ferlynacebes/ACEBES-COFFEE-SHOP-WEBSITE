<?php

/*
|--------------------------------------------------------------------------
| START SESSION
|--------------------------------------------------------------------------
| This must happen before includes/header.php so the profile
| dropdown can read the logged-in user's session information.
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| USER INFORMATION
|--------------------------------------------------------------------------
*/

$isLoggedIn = isset($_SESSION["user_id"]);

$userName = $_SESSION["user_name"] ?? "";

$userRole = $_SESSION["user_role"] ?? "customer";


/*
|--------------------------------------------------------------------------
| SAFE DISPLAY NAME
|--------------------------------------------------------------------------
*/

if ($userName === "") {

    $userName = "Coffee Lover";

}


include "includes/header.php";

?>


<!-- =========================================================
     HERO SECTION
========================================================= -->

<section class="hero">


    <!-- Hero Background -->

    <img
        src="assets/images/hero.jpg"
        alt="Freshly brewed coffee at Acebes Coffee Shop"
        class="hero-image"
    >


    <!-- Dark Overlay -->

    <div class="hero-overlay"></div>


    <!-- Hero Content -->

    <div class="container">

        <div class="hero-content">


            <?php if ($isLoggedIn): ?>

                <p class="hero-small-title">

                    Welcome back,
                    <?php
                    echo htmlspecialchars(
                        $userName,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>!

                </p>

            <?php else: ?>

                <p class="hero-small-title">
                    Welcome to Acebes Coffee Shop
                </p>

            <?php endif; ?>


            <h1>

                Brewed with

                <span class="hero-script">
                    Comfort &amp; Tradition
                </span>

            </h1>


            <p class="hero-description">

                Every cup tells a story.
                Experience handcrafted coffee made
                with passion and served with heart.

            </p>


            <div class="hero-buttons">


                <a
                    href="menu.php"
                    class="btn btn-primary"
                >
                    SHOP NOW
                </a>


                <a
                    href="contact.php"
                    class="btn btn-secondary"
                >
                    VISIT US
                </a>


            </div>


        </div>

    </div>

</section>



<!-- =========================================================
     LOGGED-IN WELCOME
========================================================= -->

<?php if ($isLoggedIn): ?>

<section
    class="section"
    style="
        padding-bottom:40px;
        padding-top:40px;
    "
>

    <div class="container">

        <div
            style="
                max-width:850px;
                margin:0 auto;
                padding:25px 30px;
                border-radius:18px;
                background:var(--cream);
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:20px;
                flex-wrap:wrap;
            "
        >


            <div>

                <span
                    style="
                        display:block;
                        font-size:12px;
                        font-weight:bold;
                        letter-spacing:2px;
                        color:var(--latte);
                        margin-bottom:6px;
                    "
                >
                    YOUR ACCOUNT
                </span>


                <h3
                    style="
                        margin:0 0 5px;
                        color:var(--espresso);
                    "
                >

                    Hello,
                    <?php
                    echo htmlspecialchars(
                        $userName,
                        ENT_QUOTES,
                        "UTF-8"
                    );
                    ?>! ☕

                </h3>


                <p
                    style="
                        margin:0;
                        color:#777;
                    "
                >
                    Ready for your next coffee moment?
                </p>

            </div>


            <a
                href="menu.php"
                class="btn btn-primary"
            >
                ORDER COFFEE
            </a>


        </div>

    </div>

</section>

<?php endif; ?>



<!-- =========================================================
     OUR STORY
========================================================= -->

<section class="section story-section">

    <div class="container">


        <div class="story-grid">


            <!-- Story Image -->

            <div class="story-image">

                <img
                    src="assets/images/our-story.jpg"
                    alt="Coffee being prepared at Acebes Coffee Shop"
                    loading="lazy"
                >

            </div>


            <!-- Story Content -->

            <div class="story-content">


                <span class="section-label">
                    Our Story
                </span>


                <h2 class="section-title">
                    More Than Just Coffee
                </h2>


                <p>

                    At Acebes Coffee Shop, we believe coffee is
                    more than just a drink, it's an experience.

                </p>


                <p>

                    From carefully selected beans to a warm and
                    welcoming space, we are here to make every
                    moment special.

                </p>


                <a
                    href="about.php"
                    class="btn btn-primary"
                >
                    LEARN MORE
                </a>


            </div>


        </div>


    </div>

</section>



<!-- =========================================================
     FEATURED DRINKS
========================================================= -->

<section class="section drinks-section">

    <div class="container">


        <div class="section-header">


            <span class="section-label">
                Our Featured Drinks
            </span>


            <h2 class="section-title">
                Crafted for Every Coffee Moment
            </h2>


            <p class="section-description">

                Enjoy handcrafted coffee made with carefully
                selected ingredients and served with heart.

            </p>


        </div>



        <div class="drinks-grid">


            <!-- =================================================
                 ESPRESSO
            ================================================== -->

            <article class="drink-card">


                <div class="drink-image">

                    <img
                        src="assets/images/espresso.jpg"
                        alt="Espresso coffee"
                        loading="lazy"
                    >

                </div>


                <div class="drink-content">


                    <h3>
                        Espresso
                    </h3>


                    <p>
                        Bold and strong.
                        Pure coffee perfection.
                    </p>


                    <div class="drink-bottom">


                        <span class="drink-price">
                            ₱90
                        </span>


                        <a
                            href="menu.php"
                            class="drink-order"
                        >
                            Order Now
                        </a>


                    </div>


                </div>


            </article>



            <!-- =================================================
                 CAPPUCCINO
            ================================================== -->

            <article class="drink-card">


                <div class="drink-image">

                    <img
                        src="assets/images/cappuccino.jpg"
                        alt="Cappuccino coffee"
                        loading="lazy"
                    >

                </div>


                <div class="drink-content">


                    <h3>
                        Cappuccino
                    </h3>


                    <p>

                        A perfect balance of espresso,
                        steamed milk and foam.

                    </p>


                    <div class="drink-bottom">


                        <span class="drink-price">
                            ₱110
                        </span>


                        <a
                            href="menu.php"
                            class="drink-order"
                        >
                            Order Now
                        </a>


                    </div>


                </div>


            </article>



            <!-- =================================================
                 CARAMEL LATTE
            ================================================== -->

            <article class="drink-card">


                <div class="drink-image">

                    <img
                        src="assets/images/caramel-latte.jpg"
                        alt="Caramel latte"
                        loading="lazy"
                    >

                </div>


                <div class="drink-content">


                    <h3>
                        Caramel Latte
                    </h3>


                    <p>

                        Smooth espresso with caramel
                        and steamed milk.

                    </p>


                    <div class="drink-bottom">


                        <span class="drink-price">
                            ₱120
                        </span>


                        <a
                            href="menu.php"
                            class="drink-order"
                        >
                            Order Now
                        </a>


                    </div>


                </div>


            </article>



            <!-- =================================================
                 MOCHA
            ================================================== -->

            <article class="drink-card">


                <div class="drink-image">

                    <img
                        src="assets/images/mocha.jpg"
                        alt="Mocha coffee"
                        loading="lazy"
                    >

                </div>


                <div class="drink-content">


                    <h3>
                        Mocha
                    </h3>


                    <p>

                        Rich chocolate, espresso,
                        and steamed milk.

                    </p>


                    <div class="drink-bottom">


                        <span class="drink-price">
                            ₱125
                        </span>


                        <a
                            href="menu.php"
                            class="drink-order"
                        >
                            Order Now
                        </a>


                    </div>


                </div>


            </article>


        </div>


    </div>

</section>



<!-- =========================================================
     WHY ACEBES
========================================================= -->

<section class="section why-section">

    <div class="container">


        <div class="section-header">


            <span class="section-label">
                THE ACEBES EXPERIENCE
            </span>


            <h2 class="section-title">
                Every Cup Tells a Story
            </h2>


            <p class="section-description">

                From carefully selected beans to a warm
                and welcoming space, we want every visit
                to be special.

            </p>


        </div>



        <div class="features-grid">


            <!-- Quality -->

            <article class="feature-card">


                <div class="feature-icon">
                    ☕
                </div>


                <h3>
                    HANDCRAFTED
                </h3>


                <p>

                    Every cup is prepared with care
                    to give you a satisfying coffee
                    experience.

                </p>


            </article>



            <!-- Ingredients -->

            <article class="feature-card">


                <div class="feature-icon">
                    ✦
                </div>


                <h3>
                    QUALITY
                </h3>


                <p>

                    We focus on carefully selected
                    ingredients for every drink.

                </p>


            </article>



            <!-- Comfort -->

            <article class="feature-card">


                <div class="feature-icon">
                    ♡
                </div>


                <h3>
                    COMFORT
                </h3>


                <p>

                    Enjoy your coffee in a warm,
                    welcoming environment.

                </p>


            </article>



            <!-- Care -->

            <article class="feature-card">


                <div class="feature-icon">
                    ✓
                </div>


                <h3>
                    WITH HEART
                </h3>


                <p>

                    We serve every customer with
                    genuine care and hospitality.

                </p>


            </article>


        </div>


    </div>

</section>



<!-- =========================================================
     CALL TO ACTION
========================================================= -->

<section
    class="section cta-section"
>

    <div class="container">


        <div
            style="
                text-align:center;
                max-width:700px;
                margin:0 auto;
            "
        >


            <span class="section-label">

                Your Coffee Moment Awaits

            </span>


            <h2 class="section-title">

                Ready for Your Next Cup?

            </h2>


            <p class="section-description">

                Visit Acebes Coffee Shop and enjoy
                handcrafted coffee in a cozy atmosphere.

            </p>


            <a
                href="menu.php"
                class="btn btn-primary"
            >
                ORDER NOW
            </a>


        </div>


    </div>

</section>



<?php

include "includes/footer.php";

?>