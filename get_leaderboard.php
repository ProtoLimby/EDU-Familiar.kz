<?php
header('Content-Type: application/json');
require_once 'db_connect.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;

// Определяем контекст (страницу)
$context = $_GET['context'] ?? 'profile';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Количество записей — сначала проверяем переданный per_page, иначе по контексту
$per_page = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : (($context === 'best') ? 100 : 3);
$offset = ($page - 1) * $per_page;

// Считаем пользователей
$total_users = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$total_pages = ceil($total_users / $per_page);

// Получаем пользователей по очкам
$stmt = $conn->prepare("SELECT username, points, avatar FROM users ORDER BY points DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$res = $stmt->get_result();

$leaders = [];
while ($r = $res->fetch_assoc()) {
    $leaders[] = [
        'username' => $r['username'],
        'points' => (int)$r['points'],
        'avatar' => $r['avatar'] ?: 'Def_Avatar.jpg'
    ];
}

// Позиция текущего пользователя
$user_position = null;
if ($user_id) {
    $rank = $conn->prepare("SELECT COUNT(*)+1 AS pos FROM users WHERE points > (SELECT points FROM users WHERE id = ?)");
    $rank->bind_param("i", $user_id);
    $rank->execute();
    $rank_r = $rank->get_result()->fetch_assoc();
    $user_position = $rank_r['pos'] ?? null;
}

echo json_encode([
    'leaders' => $leaders,
    'user_position' => $user_position,
    'page' => $page,
    'total_pages' => $total_pages,
    'context' => $context,
    'per_page' => $per_page  // Добавляем для consistency с клиентом, если нужно
], JSON_UNESCAPED_UNICODE);
?>