<?php
// config/database.php
$host = "localhost";
$dbname = "trading_db";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("डाटाबेस कनेक्शन असफल: " . $e->getMessage() . "<br>कृपया डाटाबेस सेटअप गर्नुहोस्।");
}
?>