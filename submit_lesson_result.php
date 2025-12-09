<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Auth required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$lesson_id = intval($input['lesson_id'] ?? 0);
$percentage = intval($input['percentage'] ?? 0);
$total_score = intval($input['total_score'] ?? 0); // Набранные баллы
$time_spent = intval($input['time_spent'] ?? 0);   // Секунды

if ($lesson_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Lesson ID']);
    exit;
}

// 1. Получаем настройки урока (монеты)
$l_stmt = $conn->prepare("SELECT coins_reward, max_attempts FROM lessons WHERE id = ?");
$l_stmt->bind_param("i", $lesson_id);
$l_stmt->execute();
$lesson_info = $l_stmt->get_result()->fetch_assoc();

if (!$lesson_info) {
    echo json_encode(['status' => 'error', 'message' => 'Lesson not found']);
    exit;
}

// 2. Проверяем, это первая попытка или нет (для монет)
$check = $conn->query("SELECT id FROM lesson_completions WHERE user_id = $user_id AND lesson_id = $lesson_id");
$is_first_time = ($check->num_rows === 0);

// 3. Записываем результат
$stmt = $conn->prepare("INSERT INTO lesson_completions (user_id, lesson_id, score, percentage, time_spent) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiiii", $user_id, $lesson_id, $total_score, $percentage, $time_spent);

if ($stmt->execute()) {
    $coins_added = 0;
    $msg = "Результат сохранен.";

    // Начисляем монеты только за первый раз и если результат > 50% (опционально, но логично)
    if ($is_first_time && $percentage >= 50) {
        $coins = intval($lesson_info['coins_reward']);
        $conn->query("UPDATE users SET points = points + $coins WHERE id = $user_id");
        // Обновляем рекорд пользователя
        $conn->query("UPDATE users SET highest_score = points WHERE id = $user_id AND points > highest_score");
        $coins_added = $coins;
        $msg .= " Вы получили +$coins EF!";
    }

    // Сброс таймера сессии
    $session_key = 'lesson_start_time_' . $lesson_id . '_' . $user_id;
    if (isset($_SESSION[$session_key])) {
        unset($_SESSION[$session_key]);
    }

    echo json_encode([
        'status' => 'success', 
        'message' => $msg, 
        'coins_awarded' => $coins_added
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $conn->error]);
}
?>