<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Ошибка авторизации']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод']);
    exit;
}

// Создаем папку для работ учеников, если нет
$upload_dir = 'uploads/assignments/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['assignment_file']['tmp_name'];
    $fileName = $_FILES['assignment_file']['name'];
    $fileSize = $_FILES['assignment_file']['size'];
    
    // Генерируем уникальное имя: user_ID_timestamp_filename
    // Это предотвратит перезапись файлов с одинаковыми именами
    $newFileName = $_SESSION['user_id'] . '_' . time() . '_' . basename($fileName);
    $dest_path = $upload_dir . $newFileName;

    // Ограничение размера (например, 20 МБ)
    if ($fileSize > 20 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Файл слишком большой (макс. 20МБ)']);
        exit;
    }

    // Разрешенные расширения
    $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (in_array($fileExtension, $allowedfileExtensions)) {
        if(move_uploaded_file($fileTmpPath, $dest_path)) {
            echo json_encode([
                'success' => true, 
                'message' => 'Файл успешно загружен',
                'filepath' => $dest_path, // Возвращаем путь, чтобы сохранить его в ответе урока
                'filename' => $newFileName
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ошибка при перемещении файла на сервере']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Недопустимый формат файла']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Файл не отправлен или ошибка загрузки']);
}
?>