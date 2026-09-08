<?php

/*
|--------------------------------------------------------------------------
| ACEBES COFFEE DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

$host = "localhost";
$username = "root";
$password = "";
$database = "acebes_coffee";


$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);


/*
|--------------------------------------------------------------------------
| CHECK CONNECTION
|--------------------------------------------------------------------------
*/

if ($conn->connect_error) {

    die(
        "Database connection failed: "
        . $conn->connect_error
    );

}


/*
|--------------------------------------------------------------------------
| CHARACTER SET
|--------------------------------------------------------------------------
*/

$conn->set_charset("utf8mb4");

?>