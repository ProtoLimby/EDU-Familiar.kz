<?php
// get_points.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    // В случае ошибки или отсутствия пользователя возвращаем 0
    echo json_encode(['points' => 0, 'highest_score' => 0]);
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT points, highest_score FROM users WHERE id = ?"); // <- ИЗМЕНЕНИЕ: Запрашиваем highest_score
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$current_points = (int)$row['points'];
$highest_score = (int)$row['highest_score'];


// === ДОБАВЛЕНИЕ ЛОГИКИ ОБНОВЛЕНИЯ РЕКОРДА ===
if ($current_points > $highest_score) {
    $update_stmt = $conn->prepare("UPDATE users SET highest_score = ? WHERE id = ?");
    $update_stmt->bind_param("ii", $current_points, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    $highest_score = $current_points; // Обновляем переменную для ответа
}
// ============================================

$stmt->close();

// Возвращаем оба значения
echo json_encode(['points' => $current_points, 'highest_score' => $highest_score]); // <- ИЗМЕНЕНИЕ: Возвращаем highest_score
?>