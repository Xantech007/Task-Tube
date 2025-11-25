<?php
$host = 'sql100.infinityfree.com'; // Database host
$dbname = 'if0_40505508_db'; // Database name
$username = 'if0_40505508'; // Database username
$password = 'UtVpyuQKAvO'; // Database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
