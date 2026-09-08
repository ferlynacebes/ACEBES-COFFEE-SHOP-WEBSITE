/* =========================================================
   ACEBES COFFEE SHOP
   MAIN JAVASCRIPT
   ========================================================= */


/* =========================================================
   1. MOBILE NAVIGATION
========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    const mobileMenuBtn = document.getElementById("mobileMenuBtn");
    const mobileNav = document.getElementById("mobileNav");

    if (mobileMenuBtn && mobileNav) {

        mobileMenuBtn.addEventListener("click", function () {

            mobileNav.classList.toggle("open");

            const isOpen = mobileNav.classList.contains("open");

            mobileMenuBtn.setAttribute(
                "aria-expanded",
                isOpen
            );

        });


        /* Close mobile menu when a link is clicked */

        const mobileLinks =
            mobileNav.querySelectorAll("a");

        mobileLinks.forEach(function (link) {

            link.addEventListener("click", function () {

                mobileNav.classList.remove("open");

                mobileMenuBtn.setAttribute(
                    "aria-expanded",
                    "false"
                );

            });

        });

    }


    /* =====================================================
       2. CLOSE MOBILE MENU WHEN CLICKING OUTSIDE
    ===================================================== */

    document.addEventListener("click", function (event) {

        if (!mobileMenuBtn || !mobileNav) {
            return;
        }

        const clickedInsideMenu =
            mobileNav.contains(event.target);

        const clickedButton =
            mobileMenuBtn.contains(event.target);

        if (
            !clickedInsideMenu &&
            !clickedButton
        ) {

            mobileNav.classList.remove("open");

            mobileMenuBtn.setAttribute(
                "aria-expanded",
                "false"
            );

        }

    });


    /* =====================================================
       3. ESCAPE KEY CLOSES MOBILE MENU
    ===================================================== */

    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape") {

            if (mobileNav) {
                mobileNav.classList.remove("open");
            }

            if (mobileMenuBtn) {
                mobileMenuBtn.setAttribute(
                    "aria-expanded",
                    "false"
                );
            }

        }

    });


    /* =====================================================
       4. SIMPLE FADE-UP ANIMATION
    ===================================================== */

    const animatedElements =
        document.querySelectorAll(".fade-up");

    animatedElements.forEach(function (element) {

        element.classList.add("fade-up");

    });


    /* =====================================================
       5. CURRENT YEAR
    ===================================================== */

    const yearElements =
        document.querySelectorAll(".current-year");

    yearElements.forEach(function (element) {

        element.textContent =
            new Date().getFullYear();

    });

});