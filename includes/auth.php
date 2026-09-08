```php
<?php

/* =========================================================
   ACEBES COFFEE SHOP
   AUTHENTICATION HELPER
========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =========================================================
   CHECK IF USER IS LOGGED IN
========================================================= */

function isLoggedIn()
{
    return isset($_SESSION["user_id"]);
}


/* =========================================================
   CHECK IF ADMIN
========================================================= */

function isAdmin()
{
    return (
        isset($_SESSION["user_role"]) &&
        $_SESSION["user_role"] === "admin"
    );
}


/* =========================================================
   REQUIRE LOGIN
========================================================= */

function requireLogin()
{
    if (!isLoggedIn()) {

        header("Location: login.php");
        exit;

    }
}


/* =========================================================
   REQUIRE ADMIN
========================================================= */

function requireAdmin()
{
    if (!isLoggedIn() || !isAdmin()) {

        header("Location: login.php");
        exit;

    }
}

?>
```
