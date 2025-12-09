<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lesson = $result->fetch_assoc();

    if ($lesson) {
        $response = [
            'blocks' => json_decode($lesson['content_json']),
            'meta' => [
                'page_name' => $lesson['title'],
                'subject' => $lesson['subject'],
                'grade' => $lesson['grade'],
                'lesson_avatar' => $lesson['cover_image'],
                'achievement_name' => $lesson['achievement_name'],
                'achievement_icon' => $lesson['achievement_icon'],
                'coins_reward' => $lesson['coins_reward'],
                'short_description' => $lesson['short_description'],
                'full_description' => $lesson['full_description'],
                'max_attempts' => $lesson['max_attempts'],
                'privacy' => $lesson['privacy'],
                'is_hidden' => $lesson['is_hidden'],
                'class_id' => $lesson['class_id'],
                'section_id' => $lesson['section_id']
            ]
        ];
        echo json_encode(['success' => true, 'data' => $response]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Урок не найден']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Неверный ID']);
}
?>