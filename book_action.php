<?php
// book_action.php
session_start();
require_once 'db_connect.php';

// 1. Получаем параметры
if (!isset($_GET['id']) || !isset($_GET['action'])) {
    die("Неверный запрос.");
}

$book_id = intval($_GET['id']);
$action = $_GET['action']; // 'read' или 'download'
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// 2. Получаем информацию о книге и авторе
$stmt = $conn->prepare("SELECT pdf_file, user_id FROM books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("Книга не найдена.");
}

$book = $res->fetch_assoc();
$file_path = 'uploads/books/' . $book['pdf_file'];
$author_id = $book['user_id'];

// 3. ЛОГИКА НАЧИСЛЕНИЯ МОНЕТ (Только если пользователь авторизован)
if ($user_id > 0) {
    // Не начисляем монеты, если автор скачивает свою же книгу
    if ($user_id != $author_id) {
        
        // Проверяем, получал ли автор уже монеты от этого пользователя за это действие
        $check_stmt = $conn->prepare("SELECT id FROM book_actions WHERE user_id = ? AND book_id = ? AND action_type = ?");
        $check_stmt->bind_param("iis", $user_id, $book_id, $action);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();

        if ($check_res->num_rows === 0) {
            // Записи нет -> Начисляем монеты и записываем действие
            
            // A. Добавляем 100 монет автору
            $conn->query("UPDATE users SET points = points + 100 WHERE id = $author_id");

            // B. Проверяем и обновляем highest_score автора (для лидерборда)
            $conn->query("UPDATE users SET highest_score = points WHERE id = $author_id AND points > highest_score");

            // C. Записываем лог действия, чтобы не платить второй раз
            $log_stmt = $conn->prepare("INSERT INTO book_actions (user_id, book_id, author_id, action_type) VALUES (?, ?, ?, ?)");
            $log_stmt->bind_param("iiis", $user_id, $book_id, $author_id, $action);
            $log_stmt->execute();
        }
    }
}

// 4. ОТДАЕМ ФАЙЛ ПОЛЬЗОВАТЕЛЮ
if (file_exists($file_path)) {
    // Очищаем буфер вывода, чтобы не побить PDF
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    
    if ($action === 'download') {
        // Для скачивания
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    } else {
        // Для чтения в браузере
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
    }
    
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    
    readfile($file_path);
    exit;
} else {
    die("Файл книги физически отсутствует на сервере.");
}
?>