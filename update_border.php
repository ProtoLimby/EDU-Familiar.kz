<?php
session_start();
require_once 'db_connect.php'; 
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$border_color = $_POST['border_color'] ?? '#ff8c42';
$border_style = $_POST['border_style'] ?? 'solid-default'; 

// === ИЗМЕНЕНИЕ: Динамическая проверка стилей ===
$is_valid_frame = false;
if (strpos($border_style, 'frame-') === 0) {
    
    // === ИЗМЕНЕНИЕ: Проверяем, что рамка существует И видима ===
    $stmt_check = $conn->prepare("SELECT id FROM ef_premium_frames WHERE style_key = ? AND is_visible = 1");
    
    if ($stmt_check) {
        $stmt_check->bind_param("s", $border_style);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            $is_valid_frame = true;
        }
        $stmt_check->close();
    }
}

// === Обновлена логика проверки ===

if ($border_style === 'solid-default') {
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $border_color)) {
        echo json_encode(['success' => false, 'message' => 'Invalid color format for solid border']);
        exit;
    }
} elseif ($border_style === 'rgb') {
    $border_color = 'rgb'; 
} elseif ($is_valid_frame) {
    // Для рамок, цвет 'frame'
    $border_color = 'frame'; 
} else {
    // Если стиль не 'solid-default', не 'rgb' и не валидная рамка, сбрасываем
    $border_style = 'solid-default';
    $border_color = '#ff8c42'; // Сброс на дефолтный цвет
}

// Обновляем оба поля в базе данных
$stmt = $conn->prepare("UPDATE users SET avatar_border_color = ?, border_style = ? WHERE id = ?");
if (!$stmt) {
     error_log("update_border.php: Ошибка подготовки запроса: " . $conn->error);
     echo json_encode(['success' => false, 'message' => 'Ошибка сервера (DB prepare)']);
     exit;
}
$stmt->bind_param("ssi", $border_color, $border_style, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Border updated successfully',
        'border_color' => $border_color,
        'border_style' => $border_style
    ]);
} else {
    error_log("update_border.php: Ошибка выполнения запроса: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Ошибка при обновлении данных']);
}

$stmt->close();
$conn->close();
?>