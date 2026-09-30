<?php
$host = "sql311.infinityfree.com";
$user = "if0_43049852";
$pass = "YOUR_MYSQL_PASSWORD";
$db   = "if0_43049852_verifyph";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed.");
}
