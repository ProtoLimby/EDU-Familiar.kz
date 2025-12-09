<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit;
}

$user_id = $_SESSION['user_id'];
$uploadDir = 'img/avatar/';

// Проверка файла
if (empty($_FILES['avatar']['name'])) {
    echo json_encode(['success' => false, 'message' => 'Файл не выбран']);
    exit;
}

$fileName = basename($_FILES['avatar']['name']);
$targetPath = $uploadDir . $fileName;

// Попытка загрузки
if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
    $stmt = $conn->prepare("UPDATE users SET avatar=? WHERE id=?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Ошибка запроса: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("si", $fileName, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'avatar' => $fileName]);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка при сохранении файла']);
}

$conn->close();
?>
