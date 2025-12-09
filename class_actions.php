<?php
// 1. СТАРТУЕМ БУФЕРИЗАЦИЮ
ob_start();

session_start();
require_once 'db_connect.php';

// 2. ОТКЛЮЧАЕМ ВЫВОД ОШИБОК
ini_set('display_errors', 0);
error_reporting(E_ALL);

function sendJson($data) {
    if (ob_get_length()) { ob_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    sendJson(['success' => false, 'message' => 'Auth required']);
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    // ============================================================
    // 1. ПОИСК ПУБЛИЧНЫХ КЛАССОВ (КАТАЛОГ)
    // ============================================================
    if ($action === 'search_public_classes') {
        $query = trim($_POST['query'] ?? '');
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $limit = 10; 
        $offset = ($page - 1) * $limit;

        $searchCondition = "";
        $params = [];
        $types = "";

        if (!empty($query)) {
            $term = "%" . $query . "%";
            $searchCondition = "AND (c.name LIKE ? OR u.full_name LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $types .= "ss";
        }

        // Считаем
        $count_sql = "SELECT COUNT(*) as total FROM classes c JOIN users u ON c.teacher_id = u.id WHERE c.privacy = 'public' $searchCondition AND c.teacher_id != ? AND c.id NOT IN (SELECT class_id FROM class_members WHERE user_id = ?)";
        
        $stmt_cnt = $conn->prepare($count_sql);
        if (!empty($query)) {
            $stmt_cnt->bind_param($types . "ii", ...array_merge($params, [$user_id, $user_id]));
        } else {
            $stmt_cnt->bind_param("ii", $user_id, $user_id);
        }
        
        $stmt_cnt->execute();
        $total_rows = $stmt_cnt->get_result()->fetch_assoc()['total'];
        $total_pages = ceil($total_rows / $limit);

        // Получаем
        $sql = "SELECT c.id, c.name, c.grade, c.avatar, u.full_name as teacher_name FROM classes c JOIN users u ON c.teacher_id = u.id WHERE c.privacy = 'public' $searchCondition AND c.teacher_id != ? AND c.id NOT IN (SELECT class_id FROM class_members WHERE user_id = ?) ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($sql);
        
        if (!empty($query)) {
            $stmt->bind_param($types . "iiii", ...array_merge($params, [$user_id, $user_id, $limit, $offset]));
        } else {
            $stmt->bind_param("iiii", $user_id, $user_id, $limit, $offset);
        }

        $stmt->execute();
        $classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        sendJson([
            'classes' => $classes,
            'total_pages' => $total_pages,
            'current_page' => $page
        ]);
    }

    // ============================================================
    // 2. ВХОД В КЛАСС ПО КОДУ (ИСПРАВЛЕНО: ОПРЕДЕЛЕНИЕ РОЛИ)
    // ============================================================
    if ($action === 'join_by_code') {
        $code = trim($_POST['code'] ?? '');
        if (empty($code)) sendJson(['success'=>false, 'message'=>'Введите код']);

        // 1. Ищем класс
        $stmt = $conn->prepare("SELECT id, name FROM classes WHERE join_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 0) sendJson(['success'=>false, 'message'=>'Класс не найден']);
        
        $class = $res->fetch_assoc();
        $class_id = $class['id'];
        
        // 2. Проверяем, не участник ли уже
        $check = $conn->prepare("SELECT id FROM class_members WHERE class_id = ? AND user_id = ?");
        $check->bind_param("ii", $class_id, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) sendJson(['success'=>false, 'message'=>'Вы уже в этом классе']);

        // 3. ОПРЕДЕЛЯЕМ РОЛЬ (Учитель или Ученик?)
        $u_stmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
        $u_stmt->bind_param("i", $user_id);
        $u_stmt->execute();
        $u_type = $u_stmt->get_result()->fetch_assoc()['user_type'];
        
        // Если юзер "Teacher", он заходит как учитель (коллега), иначе как ученик
        $role_to_assign = (strtolower(trim($u_type)) === 'teacher') ? 'teacher' : 'student';

        // 4. Добавляем
        $ins = $conn->prepare("INSERT INTO class_members (class_id, user_id, role, status) VALUES (?, ?, ?, 'accepted')");
        $ins->bind_param("iis", $class_id, $user_id, $role_to_assign);
        
        if ($ins->execute()) {
            sendJson(['success'=>true, 'class_name'=>$class['name'], 'class_id'=>$class_id]);
        } else {
            throw new Exception("Ошибка БД: " . $conn->error);
        }
    }
    
    // ============================================================
    // 3. ВХОД В ПУБЛИЧНЫЙ КЛАСС (БЕЗ КОДА)
    // ============================================================
    if ($action === 'join_public_class') {
        $class_id = intval($_POST['class_id']);
        if (!$class_id) { sendJson(['success'=>false, 'message'=>'Ошибка ID']); }

        // 1. Проверяем, существует ли класс и является ли он ПУБЛИЧНЫМ
        $stmt = $conn->prepare("SELECT id, name, privacy FROM classes WHERE id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 0) {
            sendJson(['success'=>false, 'message'=>'Класс не найден']);
        }
        
        $class = $res->fetch_assoc();
        
        if ($class['privacy'] !== 'public') {
            sendJson(['success'=>false, 'message'=>'Этот класс приватный. Нужен код доступа.']);
        }
        
        // 2. Проверяем, не участник ли уже
        $check = $conn->prepare("SELECT id FROM class_members WHERE class_id = ? AND user_id = ?");
        $check->bind_param("ii", $class_id, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            sendJson(['success'=>false, 'message'=>'Вы уже в этом классе']);
        }
        
        // 3. ОПРЕДЕЛЯЕМ РОЛЬ (Учитель или Ученик?)
        $u_stmt = $conn->prepare("SELECT user_type FROM users WHERE id = ?");
        $u_stmt->bind_param("i", $user_id);
        $u_stmt->execute();
        $u_type = $u_stmt->get_result()->fetch_assoc()['user_type'];
        
        $role_to_assign = (strtolower(trim($u_type)) === 'teacher') ? 'teacher' : 'student';

        // 4. Добавляем
        $ins = $conn->prepare("INSERT INTO class_members (class_id, user_id, role, status) VALUES (?, ?, ?, 'accepted')");
        $ins->bind_param("iis", $class_id, $user_id, $role_to_assign);
        
        if ($ins->execute()) {
            sendJson(['success'=>true, 'class_name'=>$class['name'], 'class_id'=>$class_id]);
        } else {
            sendJson(['success'=>false, 'message'=>'Ошибка БД: ' . $conn->error]);
        }
    }

    // ... (ОСТАЛЬНЫЕ МЕТОДЫ БЕЗ ИЗМЕНЕНИЙ) ...
    
    if ($action === 'get_my_lessons') {
        $stmt = $conn->prepare("SELECT id, title, subject, grade FROM lessons WHERE user_id = ? AND privacy = 'private' AND is_hidden = 1 ORDER BY created_at DESC");
        $stmt->bind_param("i", $user_id); $stmt->execute();
        sendJson($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }

    if ($action === 'add_lesson_to_section') {
        $lid = intval($_POST['lesson_id']);
        $sid = intval($_POST['section_id']);
        if (!$lid || !$sid) sendJson(['success' => false]);
        
        $upd = $conn->prepare("UPDATE lessons SET section_id = ?, is_hidden = 0 WHERE id = ? AND user_id = ?");
        $upd->bind_param("iii", $sid, $lid, $user_id);
        if ($upd->execute()) sendJson(['success' => true]);
        else throw new Exception($conn->error);
    }

    if ($action === 'get_publishing_options') {
        $sql_owner = "SELECT c.id as class_id, c.name as class_name, s.id as section_id, s.title as section_title, 'owner' as role FROM classes c JOIN class_sections s ON c.id = s.class_id WHERE c.teacher_id = ?";
        $sql_assigned = "SELECT c.id as class_id, c.name as class_name, s.id as section_id, s.title as section_title, 'assigned' as role FROM section_teachers st JOIN class_sections s ON st.section_id = s.id JOIN classes c ON s.class_id = c.id WHERE st.teacher_id = ?";

        $stmt1 = $conn->prepare($sql_owner); $stmt1->bind_param("i", $user_id); $stmt1->execute(); $r1 = $stmt1->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2 = $conn->prepare($sql_assigned); $stmt2->bind_param("i", $user_id); $stmt2->execute(); $r2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

        $classes = [];
        foreach (array_merge($r1, $r2) as $r) { 
            $cid = $r['class_id'];
            if (!isset($classes[$cid])) {
                $classes[$cid] = ['id'=>$cid, 'name'=>$r['class_name'], 'role'=>$r['role'], 'sections'=>[]];
            }
            $classes[$cid]['sections'][] = ['id'=>$r['section_id'], 'title'=>$r['section_title']];
        }
        sendJson(array_values($classes));
    }

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?>