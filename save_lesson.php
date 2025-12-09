<?php

ob_start();

session_start();
require_once 'db_connect.php';


ini_set('display_errors', 0);
error_reporting(E_ALL);


function sendJson($data) {

    if (ob_get_length()) {
        ob_clean(); 
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    

    exit;
}

if (!isset($_SESSION['user_id'])) {
    sendJson(['success' => false, 'message' => 'Ошибка авторизации']);
}

$user_id = $_SESSION['user_id'];
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input || !isset($input['blocks'])) {
    sendJson(['success' => false, 'message' => 'Нет данных для сохранения']);
}

$lesson_id = isset($input['id']) && $input['id'] != 'null' ? intval($input['id']) : null;
$blocksJson = json_encode($input['blocks'], JSON_UNESCAPED_UNICODE); 
$meta = isset($input['meta']) ? $input['meta'] : [];


$title = $meta['title'] ?? 'Без названия';
$subject = $meta['subject'] ?? 'General';
$grade = intval($meta['grade'] ?? 1);
$avatar = $meta['lesson_avatar'] ?? '';
$achName = $meta['achievement_name'] ?? '';
$achIcon = $meta['achievement_icon'] ?? 'fa-star';
$coins = intval($meta['coins'] ?? 150);
$short_desc = $meta['short_description'] ?? '';
$full_desc = $meta['full_description'] ?? '';
$language = $meta['language'] ?? 'ru';

// Настройки
$max_attempts = isset($meta['max_attempts']) ? intval($meta['max_attempts']) : 0;
$privacy = $meta['privacy'] ?? 'public';
$is_hidden = !empty($meta['is_hidden']) ? 1 : 0;

// Обработка NULL
$class_id = (!empty($meta['class_id']) && $meta['class_id'] !== 'undefined') ? intval($meta['class_id']) : NULL;
$section_id = (!empty($meta['section_id']) && $meta['section_id'] !== 'undefined') ? intval($meta['section_id']) : NULL;

// === ЛОГИКА СОХРАНЕНИЯ ПРИВЯЗКИ ===
if ($lesson_id) {
    $original_stmt = $conn->prepare("SELECT class_id, section_id FROM lessons WHERE id = ?");
    $original_stmt->bind_param("i", $lesson_id);
    $original_stmt->execute();
    $res = $original_stmt->get_result();
    $original_res = $res->fetch_assoc();
    
    // Восстанавливаем старые значения, если новые не пришли
    if ($class_id === NULL && $original_res && $original_res['class_id'] !== NULL) {
        $class_id = $original_res['class_id'];
        $section_id = $original_res['section_id'];
    }
}

// Если публичный — очищаем
if ($privacy === 'public') {
    $class_id = NULL;
    $section_id = NULL;
}

// Проверка прав
if ($lesson_id) {
    $check = $conn->query("SELECT user_id FROM lessons WHERE id = $lesson_id");
    if ($check->num_rows > 0) {
        $row = $check->fetch_assoc();
        if ($row['user_id'] != $user_id) {
            sendJson(['success' => false, 'message' => 'Вы не автор этого урока']);
        }
    }
}

try {
    if ($lesson_id) {
        // UPDATE
        $stmt = $conn->prepare("UPDATE lessons SET content_json=?, title=?, subject=?, grade=?, cover_image=?, achievement_name=?, achievement_icon=?, coins_reward=?, short_description=?, full_description=?, max_attempts=?, language=?, privacy=?, is_hidden=?, class_id=?, section_id=? WHERE id=? AND user_id=?");
        
        if (!$stmt) throw new Exception("Prepare UPDATE failed: " . $conn->error);

        $stmt->bind_param(
            "sssisssississiiiii", 
            $blocksJson, $title, $subject, $grade, $avatar, 
            $achName, $achIcon, $coins, $short_desc, $full_desc, 
            $max_attempts, $language, $privacy, $is_hidden, 
            $class_id, $section_id, $lesson_id, $user_id
        );
        
        if (!$stmt->execute()) throw new Exception("Execute UPDATE failed: " . $stmt->error);
        
        $message = "Урок успешно обновлен";

    } else {
        // INSERT
        $stmt = $conn->prepare("INSERT INTO lessons (user_id, content_json, title, subject, grade, cover_image, achievement_name, achievement_icon, coins_reward, short_description, full_description, max_attempts, language, privacy, is_hidden, class_id, section_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) throw new Exception("Prepare INSERT failed: " . $conn->error);

        $stmt->bind_param(
            "isssisssississiii", 
            $user_id, $blocksJson, $title, $subject, $grade, $avatar, 
            $achName, $achIcon, $coins, $short_desc, $full_desc, 
            $max_attempts, $language, $privacy, $is_hidden, 
            $class_id, $section_id
        );

        if (!$stmt->execute()) throw new Exception("Execute INSERT failed: " . $stmt->error);
        
        $lesson_id = $stmt->insert_id;
        $message = "Новый урок создан";
    }

    sendJson(['success' => true, 'id' => $lesson_id, 'message' => $message]);

} catch (Exception $e) {
    error_log("Save Error: " . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Ошибка БД: ' . $e->getMessage()]);
}
?>