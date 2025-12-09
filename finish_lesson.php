<?php
// 1. СТАРТ БУФЕРИЗАЦИИ (Защита от "грязного" JSON)
ob_start();

session_start();
require_once 'db_connect.php';
require_once 'functions.php'; 

// 2. ОТКЛЮЧАЕМ ВЫВОД ОШИБОК В ОТВЕТ (чтобы не ломать JSON)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Функция для безопасной отправки JSON
function sendJson($data) {
    // Очищаем любой мусор перед отправкой
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Вы не авторизованы']);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        sendJson(['success' => false, 'message' => 'Некорректные данные (JSON)']);
    }

    $lesson_id = isset($input['lesson_id']) ? intval($input['lesson_id']) : 0;
    $user_id = $_SESSION['user_id'];

    // Статистика прохождения
    $score = isset($input['score']) ? intval($input['score']) : 0;
    $max_score = isset($input['max_score']) ? intval($input['max_score']) : 0;
    $percentage = isset($input['percentage']) ? intval($input['percentage']) : 0;
    $time_spent = isset($input['time_spent']) ? intval($input['time_spent']) : 0;
    $user_answers = isset($input['answers']) ? json_encode($input['answers'], JSON_UNESCAPED_UNICODE) : null;

    if ($lesson_id <= 0) {
        sendJson(['success' => false, 'message' => 'Неверный ID урока']);
    }

    // 1. Получаем награду в МОНЕТАХ
    $stmt = $conn->prepare("SELECT coins_reward FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $lesson_id);
    $stmt->execute();
    $res_lesson = $stmt->get_result();
    
    if ($res_lesson->num_rows === 0) {
        sendJson(['success' => false, 'message' => 'Урок не найден']);
    }
    
    $lesson_data = $res_lesson->fetch_assoc();
    $coins_reward = intval($lesson_data['coins_reward']);

    // 2. Проверяем, проходил ли урок раньше
    $check = $conn->query("SELECT id FROM lesson_completions WHERE user_id = $user_id AND lesson_id = $lesson_id");
    $is_first_time = ($check->num_rows === 0);

    // Инициализируем награды
    $coins_to_add = 0;
    $xp_to_add = 0;

    // Начисляем ТОЛЬКО если результат >= 50%
    if ($percentage >= 50) {
        if ($is_first_time) {
            // Первый раз: Даем полные монеты + Рандомный опыт (100-500)
            $coins_to_add = $coins_reward;
            
            // ИСПРАВЛЕНИЕ: Используем mt_rand напрямую, если функции нет
            if (function_exists('generateRandomXP')) {
                $xp_to_add = generateRandomXP();
            } else {
                $xp_to_add = mt_rand(100, 500); 
            }
        } else {
            // Повторно: Монет не даем (0), Опыта совсем чуть-чуть за старание (10-50)
            $coins_to_add = 0; 
            $xp_to_add = mt_rand(10, 50);
        }
    }

    // 3. Запись в БД (Транзакция)
    $conn->begin_transaction();

    // A. Сохраняем попытку
    $ins = $conn->prepare("INSERT INTO lesson_completions (user_id, lesson_id, score, max_score, percentage, time_spent, user_answers) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins->bind_param("iiiiiss", $user_id, $lesson_id, $score, $max_score, $percentage, $time_spent, $user_answers);
    
    if (!$ins->execute()) {
        throw new Exception("Ошибка при сохранении попытки: " . $conn->error);
    }

    // B. Обновляем баланс пользователя
    if ($coins_to_add > 0 || $xp_to_add > 0) {
        // 1. Обновляем Coins и XP
        $upd = $conn->prepare("UPDATE users SET points = points + ?, xp = xp + ? WHERE id = ?");
        $upd->bind_param("iii", $coins_to_add, $xp_to_add, $user_id);
        $upd->execute();

        // 2. Считаем новый уровень
        $res_xp = $conn->query("SELECT xp FROM users WHERE id = $user_id");
        $current_total_xp = $res_xp->fetch_assoc()['xp'];
        
        // Проверяем наличие функции calculateLevel
        if (function_exists('calculateLevel')) {
            $levelData = calculateLevel($current_total_xp);
            $new_level = $levelData['level'];
        } else {
            // Фолбэк, если функции нет (например, каждые 1000 XP = 1 уровень)
            $new_level = floor($current_total_xp / 1000) + 1;
        }

        // 3. Обновляем уровень и рекорд
        $conn->query("UPDATE users SET level = $new_level WHERE id = $user_id");
        $conn->query("UPDATE users SET highest_score = xp WHERE id = $user_id AND xp > highest_score");
    }

    // Сброс таймера сессии
    if (isset($_SESSION['lesson_start_time_' . $lesson_id . '_' . $user_id])) {
        unset($_SESSION['lesson_start_time_' . $lesson_id . '_' . $user_id]);
    }

    $conn->commit();
    
    // Формируем сообщение
    $msg = 'Урок завершен! Результат: ' . $percentage . '%';
    
    sendJson([
        'success' => true, 
        'coins_added' => $coins_to_add, 
        'xp_added' => $xp_to_add,
        'message' => $msg
    ]);

} catch (Throwable $e) {
    // Ловим любые ошибки (включая Fatal Errors)
    if ($conn->errno) $conn->rollback();
    error_log("Finish Lesson Error: " . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?>