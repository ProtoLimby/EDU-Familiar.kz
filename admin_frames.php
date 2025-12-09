<?php
session_start();
require_once 'db_connect.php';

// --- Защита ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt_admin = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt_admin->bind_param("i", $user_id);
$stmt_admin->execute();
$result_admin = $stmt_admin->get_result();
$user = $result_admin->fetch_assoc();
$stmt_admin->close();

if (!$user || $user['is_admin'] != 1) {
    echo "Доступ запрещен. Эта страница только для администраторов.";
    exit;
}
// --- Конец защиты ---

$message = '';
$message_type = '';
$edit_id = intval($_GET['edit_id'] ?? 0); // ID рамки для редактирования

// --- Логика: Переключение видимости ---
if (isset($_GET['toggle_visibility'])) {
    $frame_id = intval($_GET['toggle_visibility']);
    $new_status = intval($_GET['status']);
    $new_status = ($new_status === 1) ? 1 : 0;

    $stmt_toggle = $conn->prepare("UPDATE ef_premium_frames SET is_visible = ? WHERE id = ?");
    $stmt_toggle->bind_param("ii", $new_status, $frame_id);
    $stmt_toggle->execute();
    $stmt_toggle->close();
    header("Location: admin_frames.php"); // Убираем GET-параметры
    exit;
}

// --- Логика: Удаление ---
if (isset($_GET['delete'])) {
    $frame_id = intval($_GET['delete']);
    
    $stmt = $conn->prepare("SELECT image_file FROM ef_premium_frames WHERE id = ?");
    $stmt->bind_param("i", $frame_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $frame = $result->fetch_assoc();
        $file_to_delete = 'frames/' . $frame['image_file'];
        if (file_exists($file_to_delete)) { @unlink($file_to_delete); }
        
        $stmt_del = $conn->prepare("DELETE FROM ef_premium_frames WHERE id = ?");
        $stmt_del->bind_param("i", $frame_id);
        $stmt_del->execute();
        $stmt_del->close();
        
        $message = 'Рамка успешно удалена.';
        $message_type = 'success';
    } else {
        $message = 'Ошибка: Рамка не найдена.';
        $message_type = 'error';
    }
    $stmt->close();
    header("Location: admin_frames.php?msg=" . urlencode($message) . "&type=" . $message_type);
    exit;
}

// --- Логика: Обработка POST-запросов (Добавление ИЛИ Обновление) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    
    // === ОБНОВЛЕНИЕ РАМКИ ===
    if ($action === 'update') {
        $frame_id_update = intval($_POST['frame_id'] ?? 0);
        $name = $_POST['name'] ?? '';
        $category = $_POST['category'] ?? '';
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        $new_file = $_FILES['image_file_update'] ?? null; // Файл из формы редактирования

        if (empty($name) || empty($category) || $frame_id_update === 0) {
            $message = 'Ошибка: Название, Категория и ID обязательны.';
            $message_type = 'error';
        } else {
            
            $file_sql_part = ""; // Дополнительная часть SQL-запроса
            $file_sql_val = "";  // Имя нового файла для bind_param
            
            // --- Проверяем, загружен ли НОВЫЙ файл ---
            if ($new_file && $new_file['error'] === UPLOAD_ERR_OK) {
                // 1. Получаем style_key и старое имя файла
                $stmt_get = $conn->prepare("SELECT style_key, image_file FROM ef_premium_frames WHERE id = ?");
                $stmt_get->bind_param("i", $frame_id_update);
                $stmt_get->execute();
                $result_get = $stmt_get->get_result();
                $row = $result_get->fetch_assoc();
                $stmt_get->close();

                if ($row) {
                    $old_path = 'frames/' . $row['image_file'];
                    
                    // 2. Валидация и подготовка нового файла
                    $new_ext = strtolower(pathinfo($new_file['name'], PATHINFO_EXTENSION));
                    if ($new_ext !== 'png') {
                        $message = 'Ошибка: Новый файл должен быть .png';
                        $message_type = 'error';
                    } else {
                        // Имя файла = style_key + .png (style_key не меняется)
                        $new_filename = $row['style_key'] . '.' . $new_ext; 
                        $new_path = 'frames/' . $new_filename;
                        
                        // 3. Замена файла
                        @unlink($old_path); // Удаляем старый
                        if (move_uploaded_file($new_file['tmp_name'], $new_path)) {
                            // 4. Готовим часть SQL-запроса
                            $file_sql_part = ", image_file = ?";
                            $file_sql_val = $new_filename;
                        } else {
                            $message = 'Ошибка при перемещении нового файла.';
                            $message_type = 'error';
                        }
                    }
                }
            }
            
            // --- Обновляем БД (только если не было ошибки с файлом) ---
            if ($message_type !== 'error') {
                // 1. Обновляем рамку
                $sql_update = "UPDATE ef_premium_frames SET name = ?, category = ?, is_visible = ? $file_sql_part WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                
                if ($file_sql_val) {
                    // Обновляем с файлом: s, s, i, s, i
                    $stmt_update->bind_param("ssisi", $name, $category, $is_visible, $file_sql_val, $frame_id_update);
                } else {
                    // Обновляем без файла: s, s, i, i
                    $stmt_update->bind_param("ssii", $name, $category, $is_visible, $frame_id_update);
                }
                $stmt_update->execute();
                $stmt_update->close();
                
                // 2. Убеждаемся, что категория существует
                $stmt_cat = $conn->prepare("INSERT INTO ef_premium_categories (name) VALUES (?) ON DUPLICATE KEY UPDATE name=name");
                $stmt_cat->bind_param("s", $category);
                $stmt_cat->execute();
                $stmt_cat->close();
                
                if (!$message) { // Не перезаписываем ошибку, если она была
                    $message = 'Рамка успешно обновлена!';
                    $message_type = 'success';
                }
            }
        }
        $edit_id = 0; // Сбрасываем режим редактирования

    // === ДОБАВЛЕНИЕ РАМКИ ===
    } elseif ($action === 'add') {
        $name = $_POST['name'] ?? '';
        $style_key = $_POST['style_key'] ?? '';
        $category = $_POST['category'] ?? '';
        $image_file = $_FILES['image_file'] ?? null;
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;

        if (empty($name) || empty($style_key) || empty($category) || !$image_file || $image_file['error'] !== UPLOAD_ERR_OK) {
            $message = 'Ошибка: Все поля и файл изображения обязательны.';
            $message_type = 'error';
        } elseif (strpos($style_key, 'frame-') !== 0) {
            $message = 'Ошибка: "Ключ стиля" должен начинаться с "frame-"';
            $message_type = 'error';
        } else {
            $upload_dir = 'frames/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
            
            $file_extension = strtolower(pathinfo($image_file['name'], PATHINFO_EXTENSION));
            if ($file_extension !== 'png') {
                 $message = 'Ошибка: Допускаются только файлы .png';
                 $message_type = 'error';
            } else {
                $new_filename = $style_key . '.' . $file_extension; // Имя файла = style_key + .png
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($image_file['tmp_name'], $upload_path)) {
                    
                    // Авто-создание категории
                    $stmt_cat = $conn->prepare("INSERT INTO ef_premium_categories (name) VALUES (?) ON DUPLICATE KEY UPDATE name=name");
                    $stmt_cat->bind_param("s", $category);
                    $stmt_cat->execute();
                    $stmt_cat->close();
                    
                    // Добавление рамки
                    $stmt = $conn->prepare("INSERT INTO ef_premium_frames (name, style_key, image_file, category, is_visible) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssi", $name, $style_key, $new_filename, $category, $is_visible);
                    if ($stmt->execute()) {
                        $message = 'Новая рамка успешно добавлена!';
                        $message_type = 'success';
                    } else {
                        $message = 'Ошибка БД: ' . $stmt->error;
                        $message_type = 'error';
                    }
                    $stmt->close();
                } else {
                    $message = 'Ошибка при загрузке файла.';
                    $message_type = 'error';
                }
            }
        }
    }
}
// --- КОНЕЦ ЛОГИКИ POST ---


// --- Получение данных для отображения ---
$frames_result = $conn->query("SELECT * FROM ef_premium_frames ORDER BY category, name");
$frames_by_category = [];
if ($frames_result && $frames_result->num_rows > 0) {
    while($row = $frames_result->fetch_assoc()) {
        $frames_by_category[$row['category']][] = $row;
    }
}

// Загружаем список категорий для <datalist>
$categories_list = [];
$categories_result = $conn->query("SELECT name FROM ef_premium_categories ORDER BY name");
if ($categories_result) {
    while($cat = $categories_result->fetch_assoc()) {
        $categories_list[] = $cat['name'];
    }
}

// Сообщения после редиректа
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'info';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление рамками EFPremium</title>
    <link rel="stylesheet" href="CSS/settings.css"> 
    <link rel="stylesheet" href="CSS/admin_frames.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <header class="admin-top-nav">
        <div class="admin-nav-content">
            <div class="admin-nav-links">
                <span class="admin-nav-title">Админ-панель:</span>
                <a href="admin_frames.php" class="active"><i class="fas fa-crown"></i> Рамки</a>
                <a href="admin_users.php"><i class="fas fa-users"></i> Пользователи</a>
                <a href="admin_support.php"><i class="fas fa-life-ring"></i> Поддержка</a>
                <a href="admin_shop.php"><i class="fas fa-store"></i> Магазин</a>
                <a href="admin_learning.php"><i class="fas fa-graduation-cap"></i> Обучение</a>
                <a href="admin_books.php"><i class="fas fa-book-open"></i> Книги</a>
            </div>
            <div class="admin-nav-actions">
                <a href="profile.php" class="nav-action-btn"><i class="fas fa-user-circle"></i> Вернуться в профиль</a>
                <a href="index.php" class="nav-action-btn alt"><i class="fas fa-home"></i> На главную</a>
            </div>
        </div>
    </header>
    <datalist id="category-list">
        <?php foreach ($categories_list as $cat_name): ?>
            <option value="<?php echo htmlspecialchars($cat_name); ?>">
        <?php endforeach; ?>
    </datalist>

    <div class="admin-panel-container">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-crown"></i> Управление рамками EFPremium</h2>
                <a href="profile.php" class="close-modal" title="Вернуться в профиль">&times;</a>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="profile-grid">

                <div class="customization-column">
                    <div class="customization-card">
                        <h3>Добавить новую рамку</h3>
                        <form action="admin_frames.php" method="POST" enctype="multipart/form-data" class="edit-card">
                            <input type="hidden" name="action" value="add">
                            
                            <label for="name">Название</label>
                            <input type="text" id="name" name="name" required>

                            <label for="style_key">Ключ стиля (e.g., "frame-new")</label>
                            <input type="text" id="style_key" name="style_key" required>

                            <label for="category">Категория</label>
                            <input type="text" id="category" name="category" list="category-list" required>

                            <label for="image_file">Файл рамки (.png)</label>
                            <input type="file" id="image_file" name="image_file" accept=".png" required>

                            <label for="is_visible" class="checkbox-label">
                                <input type="checkbox" id="is_visible" name="is_visible" value="1" checked>
                                Сделать видимым
                            </label>

                            <button type="submit" class="save-btn">
                                <i class="fas fa-upload"></i> Загрузить
                            </button>
                        </form>
                    </div>
                </div>

                <div class="info-edit-column" id="frame-list-column">
                    <h3>Существующие рамки</h3>
                    
                    <?php if ($edit_id > 0): ?>
                        <div class="message success" style="margin: 0 0 15px 0;">
                            РЕЖИМ РЕДАКТИРОВАНИЯ: Загружена форма для ID <?php echo $edit_id; ?>
                        </div>
                    <?php else: ?>
                        <div class="message" style="margin: 0 0 15px 0; background: #f1f5f9; border: 1px solid #e2e8f0; color: #475569;">
                            РЕЖИМ ПРОСМОТРА. (edit_id = 0)
                        </div>
                    <?php endif; ?>

                    <div class="frame-list-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Превью</th>
                                    <th>Название</th>
                                    <th>Ключ стиля</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($frames_by_category)): ?>
                                    <?php foreach ($frames_by_category as $category_name => $frames): ?>
                                        
                                        <tr class="category-header">
                                            <td colspan="5"><?php echo htmlspecialchars($category_name); ?></td>
                                        </tr>
                                        
                                        <?php foreach ($frames as $row): ?>
                                        
                                        <?php if ($edit_id == $row['id']): // Используем == ?>
                                            <tr class="edit-row">
                                                <td colspan="5">
                                                    <form action="admin_frames.php" method="POST" class="edit-form-container" enctype="multipart/form-data">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="frame_id" value="<?php echo $row['id']; ?>">
                                                        
                                                        <div class="edit-form-preview">
                                                            <div class="border-preview-cell" style="background-image: url('frames/<?php echo htmlspecialchars($row['image_file']); ?>');">
                                                            </div>
                                                            <code><?php echo htmlspecialchars($row['style_key']); ?></code>
                                                        </div>
                                                        
                                                        <div class="edit-form-inputs">
                                                            <label>Название: <input type="text" name="name" value="<?php echo htmlspecialchars($row['name']); ?>" required></label>
                                                            <label>Категория: <input type="text" name="category" value="<?php echo htmlspecialchars($row['category']); ?>" list="category-list" required></label>
                                                        </div>

                                                        <div class="edit-form-file-toggle">
                                                            <label>Заменить .png: <input type="file" name="image_file_update" accept=".png"></label>
                                                            <label class="checkbox-label" style="margin-top:0; padding: 5px 0;">
                                                                <input type="checkbox" name="is_visible" value="1" <?php echo ($row['is_visible'] == 1) ? 'checked' : ''; ?>>
                                                                Видим
                                                            </label>
                                                        </div>
                                                        
                                                        <div class="edit-form-actions">
                                                            <button type="submit" class="save-btn small-btn" title="Сохранить">
                                                                <i class="fas fa-save"></i>
                                                            </button>
                                                            <a href="admin_frames.php" class="cancel-btn" title="Отмена">
                                                                <i class="fas fa-times"></i>
                                                            </a>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <tr>
                                                <td>
                                                    <div class="border-preview-cell" 
                                                         style="background-image: url('frames/<?php echo htmlspecialchars($row['image_file']); ?>');">
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><code><?php echo htmlspecialchars($row['style_key']); ?></code></td>
                                                <td>
                                                    <?php if ($row['is_visible'] == 1): ?>
                                                        <a href="admin_frames.php?toggle_visibility=<?php echo $row['id']; ?>&status=0" 
                                                           class="toggle-btn visible" title="Скрыть">
                                                            <i class="fas fa-eye"></i> Видим
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="admin_frames.php?toggle_visibility=<?php echo $row['id']; ?>&status=1" 
                                                           class="toggle-btn hidden" title="Показать">
                                                            <i class="fas fa-eye-slash"></i> Скрыт
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="actions-cell">
                                                    <a href="admin_frames.php?edit_id=<?php echo $row['id']; ?>" 
                                                       class="edit-btn" title="Редактировать">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <a href="admin_frames.php?delete=<?php echo $row['id']; ?>" 
                                                       class="delete-btn" 
                                                       onclick="return confirm('Вы уверены, что хотите удалить рамку?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endif; ?>

                                        <?php endforeach; ?>
                                        
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">Рамок пока не добавлено.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

</body>
</html>