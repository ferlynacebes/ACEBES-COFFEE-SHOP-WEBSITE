<?php

require_once "config/db.php";

$name = "admin";
$email = "ferlyn@acebes.com";
$password = "admin123";

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO users (name, email, password, role)
    VALUES (?, ?, ?, 'admin')
");

$stmt->bind_param(
    "sss",
    $name,
    $email,
    $hashedPassword
);

if ($stmt->execute()) {
    echo "Admin created successfully!<br>";
    echo "Email: admin@acebes.com<br>";
    echo "Password: admin123<br><br>";
    echo "<strong>DELETE create_admin.php after this.</strong>";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();

?>