<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

// Если не авторизован
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit;
}

$user_id = $_SESSION['user_id'];
$new_username = trim($_POST['new_username'] ?? '');
$new_full_name = trim($_POST['new_full_name'] ?? '');
$new_class = trim($_POST['new_class'] ?? '');

if ($new_username === '' || $new_full_name === '') {
    echo json_encode(['success' => false, 'message' => 'Поля не должны быть пустыми']);
    exit;
}

// Проверка соединения с БД
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к БД']);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, class=? WHERE id=?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Ошибка запроса: ' . $conn->error]);
    exit;
}

$stmt->bind_param("sssi", $new_username, $new_full_name, $new_class, $user_id);
$success = $stmt->execute();

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Данные успешно обновлены']);
} else {
    echo json_encode(['success' => false, 'message' => 'Ошибка при обновлении']);
}

$stmt->close();
$conn->close();
?>
