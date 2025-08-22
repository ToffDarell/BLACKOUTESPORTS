<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "computer_reservation";

try {
    $pdo = new PDO("mysql:host=$servername; dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    die ("Database Connection Failed: " . $e->getMessage());
}


?>
