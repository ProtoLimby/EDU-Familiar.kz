<?php
$host = "MySQL-8.0";
$user = "root";      
$pass = "";         
$dbname = "edu_familiar";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Ошибка подключения к базе данных: " . $conn->connect_error);
}

// === ОБЯЗАТЕЛЬНО: Устанавливаем кодировку UTF-8 ===
$conn->set_charset("utf8mb4");
?>