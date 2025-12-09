<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];

// ================= AJAX ОБРАБОТЧИКИ (Оставляем как было) =================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'search_users') {
        $query = trim($_POST['query'] ?? '');
        $role = $_POST['role'] ?? 'student';
        if (strlen($query) < 2) { echo json_encode([]); exit; }
        $term = "%" . $query . "%";
        $stmt = $conn->prepare("SELECT id, username, full_name, avatar FROM users WHERE user_type = ? AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?) LIMIT 5");
        $stmt->bind_param("ssss", $role, $term, $term, $term);
        $stmt->execute();
        $res = $stmt->get_result();
        $users = []; while ($r = $res->fetch_assoc()) { $users[] = $r; }
        echo json_encode($users); exit;
    }
    if ($action === 'add_member') {
        $classId = intval($_POST['class_id']);
        $targetUserId = intval($_POST['user_id']);
        $role = $_POST['role'];
        $check = $conn->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
        $check->bind_param("ii", $classId, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) { echo json_encode(['success' => false, 'message' => 'Нет прав']); exit; }
        $ins = $conn->prepare("INSERT IGNORE INTO class_members (class_id, user_id, role, status) VALUES (?, ?, ?, 'pending')");
        $ins->bind_param("iis", $classId, $targetUserId, $role);
        if ($ins->execute()) echo json_encode(['success' => true]);
        else echo json_encode(['success' => false, 'message' => $conn->error]);
        exit;
    }
    if ($action === 'get_members') {
        $classId = intval($_POST['class_id']);
        $s_stmt = $conn->prepare("SELECT u.id, u.username, u.full_name, u.avatar, cm.status FROM class_members cm JOIN users u ON cm.user_id = u.id WHERE cm.class_id = ? AND cm.role = 'student'");
        $s_stmt->bind_param("i", $classId); $s_stmt->execute();
        $students = $s_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $t_stmt = $conn->prepare("SELECT u.id, u.username, u.full_name, u.avatar, cm.status FROM class_members cm JOIN users u ON cm.user_id = u.id WHERE cm.class_id = ? AND cm.role = 'teacher'");
        $t_stmt->bind_param("i", $classId); $t_stmt->execute();
        $teachers = $t_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['students' => $students, 'teachers' => $teachers]); exit;
    }
    if ($action === 'remove_member') {
        $classId = intval($_POST['class_id']);
        $targetUserId = intval($_POST['user_id']);
        $del = $conn->prepare("DELETE FROM class_members WHERE class_id = ? AND user_id = ?");
        $del->bind_param("ii", $classId, $targetUserId);
        $del->execute();
        echo json_encode(['success' => true]); exit;
    }
    if ($action === 'create_section') {
        $classId = intval($_POST['class_id']);
        $title = trim($_POST['title']);
        if (empty($title)) { echo json_encode(['success' => false, 'message' => 'Пустое название']); exit; }
        $stmt = $conn->prepare("INSERT INTO class_sections (class_id, title) VALUES (?, ?)");
        $stmt->bind_param("is", $classId, $title);
        if ($stmt->execute()) echo json_encode(['success' => true]);
        else echo json_encode(['success' => false, 'message' => 'Ошибка БД: ' . $conn->error]);
        exit;
    }
    if ($action === 'get_sections') {
        $classId = intval($_POST['class_id']);
        $stmt = $conn->prepare("SELECT * FROM class_sections WHERE class_id = ? ORDER BY created_at ASC");
        $stmt->bind_param("i", $classId);
        $stmt->execute();
        $sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($sections as &$sec) {
            $s_id = $sec['id'];
            $t_res = $conn->query("SELECT u.id, u.username, u.full_name FROM section_teachers st JOIN users u ON st.teacher_id = u.id WHERE st.section_id = $s_id");
            $sec['teachers'] = $t_res->fetch_all(MYSQLI_ASSOC);
        }
        echo json_encode(['sections' => $sections]); exit;
    }
    if ($action === 'assign_section_teacher') {
        $sectionId = intval($_POST['section_id']);
        $teacherId = intval($_POST['teacher_id']);
        $stmt = $conn->prepare("INSERT IGNORE INTO section_teachers (section_id, teacher_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $sectionId, $teacherId);
        if ($stmt->execute()) echo json_encode(['success' => true]);
        else echo json_encode(['success' => false, 'message' => $conn->error]);
        exit;
    }
    if ($action === 'delete_section') {
        $sectionId = intval($_POST['section_id']);
        $conn->query("DELETE FROM section_teachers WHERE section_id = $sectionId");
        $conn->query("DELETE FROM class_sections WHERE id = $sectionId");
        echo json_encode(['success' => true]); exit;
    }
    if ($action === 'remove_section_teacher') {
        $sectionId = intval($_POST['section_id']);
        $teacherId = intval($_POST['teacher_id']);
        $conn->query("DELETE FROM section_teachers WHERE section_id = $sectionId AND teacher_id = $teacherId");
        echo json_encode(['success' => true]); exit;
    }
    if ($action === 'get_class_dashboard') {
        $classId = intval($_POST['class_id']);
        $sections_stmt = $conn->prepare("SELECT * FROM class_sections WHERE class_id = ? ORDER BY created_at ASC");
        $sections_stmt->bind_param("i", $classId);
        $sections_stmt->execute();
        $sections = $sections_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($sections as &$sec) {
            $secId = $sec['id'];
            $lessons_sql = "SELECT l.id, l.title, l.cover_image, l.coins_reward FROM lessons l WHERE l.section_id = ? AND (l.privacy = 'public' OR (l.privacy = 'private' AND l.class_id = ?)) AND l.is_hidden = 0 ORDER BY l.created_at DESC";
            $l_stmt = $conn->prepare($lessons_sql);
            $l_stmt->bind_param("ii", $secId, $classId);
            $l_stmt->execute();
            $lessons = $l_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach ($lessons as &$less) {
                $lId = $less['id'];
                $stats_sql = "SELECT u.full_name, lc.percentage, lc.completed_at FROM lesson_completions lc JOIN class_members cm ON lc.user_id = cm.user_id JOIN users u ON lc.user_id = u.id WHERE lc.lesson_id = ? AND cm.class_id = ? AND cm.role = 'student' ORDER BY lc.percentage DESC";
                $s_stmt = $conn->prepare($stats_sql);
                $s_stmt->bind_param("ii", $lId, $classId);
                $s_stmt->execute();
                $less['activity'] = $s_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            }
            $sec['lessons'] = $lessons;
        }
        echo json_encode(['sections' => $sections]); exit;
    }
}

// ================= ЗАГРУЗКА ДАННЫХ ДЛЯ UI =================
// 1. Данные пользователя
$stmt = $conn->prepare("SELECT username, full_name, email, user_type, avatar, class, points, xp, level, avatar_border_color, border_style, ef_premium, is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || (strtolower(trim($user['user_type'])) !== 'teacher' && $user['is_admin'] != 1)) {
    header("Location: profile.php"); exit;
}

// 2. Расчет уровня
$xp = $user['xp'] ?: 0;
$level = $user['level'] ?: 1;
$lvlData = calculateLevel($xp);

// 3. Подсчет уведомлений (приглашений)
$inv_res = $conn->query("SELECT COUNT(*) as cnt FROM class_members WHERE user_id = $user_id AND status = 'pending'");
$invites_count = $inv_res->fetch_assoc()['cnt'];

// 4. Подсчет всех классов (свои + куда вступил) для бейджика "Мои классы"
$my_classes_ids = [];
$r1 = $conn->query("SELECT id FROM classes WHERE teacher_id = $user_id");
while($rw = $r1->fetch_assoc()) $my_classes_ids[] = $rw['id'];
$r2 = $conn->query("SELECT class_id FROM class_members WHERE user_id = $user_id AND status = 'accepted'");
while($rw = $r2->fetch_assoc()) $my_classes_ids[] = $rw['class_id'];
$joined_classes_count = count(array_unique($my_classes_ids));

// 5. Обработка формы создания класса
$msg = "";
if (isset($_SESSION['flash_msg'])) { $msg = $_SESSION['flash_msg']; unset($_SESSION['flash_msg']); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_class'])) {
    $className = trim($_POST['class_name']);
    $classGrade = intval($_POST['class_grade']);
    $classAvatar = $_POST['class_avatar'] ?? 'fa-users';
    $privacy = $_POST['privacy'] ?? 'private';
    $joinCode = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

    if (!empty($className)) {
        $conn->query("CREATE TABLE IF NOT EXISTS classes (id INT AUTO_INCREMENT PRIMARY KEY, teacher_id INT NOT NULL, name VARCHAR(255) NOT NULL, grade INT NOT NULL, avatar VARCHAR(50) DEFAULT 'fa-users', privacy VARCHAR(20) DEFAULT 'private', join_code VARCHAR(10) UNIQUE NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $conn->query("CREATE TABLE IF NOT EXISTS class_members (id INT AUTO_INCREMENT PRIMARY KEY, class_id INT NOT NULL, user_id INT NOT NULL, role VARCHAR(20) NOT NULL, status ENUM('pending','accepted','rejected') DEFAULT 'pending', added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_member (class_id, user_id))");

        $stmt_ins = $conn->prepare("INSERT INTO classes (teacher_id, name, grade, avatar, privacy, join_code) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param("isisss", $user_id, $className, $classGrade, $classAvatar, $privacy, $joinCode);
        
        if ($stmt_ins->execute()) {
            $newClassId = $stmt_ins->insert_id;
            $conn->query("INSERT INTO class_members (class_id, user_id, role, status) VALUES ($newClassId, $user_id, 'teacher', 'accepted')");
            $_SESSION['flash_msg'] = "<div class='alert success'>Класс успешно создан!</div>";
            header("Location: create_class.php"); exit;
        } else {
            $msg = "<div class='alert error'>Ошибка: " . $conn->error . "</div>";
        }
        $stmt_ins->close();
    }
}

// 6. Загрузка списка классов для левой колонки (только свои)
$my_created_classes = [];
$sql = "SELECT * FROM classes WHERE teacher_id = $user_id ORDER BY created_at DESC";
$classes_res = $conn->query($sql);
if ($classes_res) {
    while($row = $classes_res->fetch_assoc()) {
        $c_id = $row['id'];
        $chk = $conn->query("SHOW TABLES LIKE 'class_members'");
        if($chk->num_rows > 0) {
            $row['student_count'] = $conn->query("SELECT COUNT(*) FROM class_members WHERE class_id=$c_id AND role='student' AND status='accepted'")->fetch_row()[0];
            $row['teacher_count'] = $conn->query("SELECT COUNT(*) FROM class_members WHERE class_id=$c_id AND role='teacher' AND status='accepted'")->fetch_row()[0];
        } else { $row['student_count'] = 0; $row['teacher_count'] = 1; }
        $my_created_classes[] = $row;
    }
}

// 7. Переменные для HTML
$username = $user['username']; $full_name = $user['full_name']; $email = $user['email']; $user_type = $user['user_type']; $avatar = $user['avatar'] ?: 'Def_Avatar.jpg'; $class = $user['class'] ?: ''; $points = $user['points'] ?: 0;
$border_color = $user['avatar_border_color'] ?: '#ff8c42'; $border_style = $user['border_style'] ?: 'solid-default'; $ef_premium = $user['ef_premium'] ?: 0; $is_admin = $user['is_admin'] ?: 0;
if (strpos($class, 'frame-') !== false || strpos($class, '.') === 0) { $class = ''; }
$frames_for_css = []; $all_frames_result = $conn->query("SELECT style_key, image_file FROM ef_premium_frames WHERE is_visible = 1"); if ($all_frames_result) { while($frame = $all_frames_result->fetch_assoc()) { $frames_for_css[] = $frame; } }
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои Классы - EDU-Familiar</title>
    <link rel="stylesheet" href="CSS/header.css">
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/settings.css">
    <link rel="stylesheet" href="CSS/class_manager.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Доп. стили для кнопок в сайдбаре, такие же как в profile */
        .notification-btn {
            position: relative; background: #fff !important; color: #1e293b !important; border: 1px solid #e2e8f0 !important;
            display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.3s ease;
        }
        .notification-btn:hover { background: #f1f5f9 !important; transform: translateY(-2px); }
        .notif-badge {
            background: #ef4444; color: white; font-size: 11px; font-weight: 700;
            padding: 2px 6px; border-radius: 10px; min-width: 18px; text-align: center;
            box-shadow: 0 2px 5px rgba(239, 68, 68, 0.3);
        }
    </style>
    <?php
    if (!empty($frames_for_css)) {
        echo "<style id=\"dynamic-frame-styles\">\n";
        foreach ($frames_for_css as $frame) echo ".border-" . htmlspecialchars($frame['style_key']) . "::after { background-image: url('frames/" . htmlspecialchars($frame['image_file']) . "'); }\n";
        echo "</style>\n";
    }
    ?>
</head>
<body class="page-internal">

    <header>
        <div class="header-content">
            <div class="site-title">EDU-Familiar.kz</div>
            <nav class="desktop-nav">
                <div class="dropdown"><a href="index.php" class="dropbtn" data-i18n="home">Home</a><div class="dropdown-content"><a href="index.php#about" data-i18n="about">About</a></div></div>
                <a href="training.php" data-i18n="training">Training</a> 
                <a href="best-students.php" data-i18n="best_students">Best Students</a> 
                <a href="online-book.php" data-i18n="online_book">Online Book</a>
                <a href="shop.html" data-i18n="catalog">Каталог</a>
            </nav>
            <div class="header-actions">
                <div class="language-switcher"><select id="language-select-header" class="lang-select"><option value="en">EN</option><option value="kz">KZ</option><option value="ru">RU</option></select></div>
                <div class="dropdown"><a href="profile.php" class="login-btn dropbtn" data-i18n="profile">Profile</a><div class="dropdown-content"><a href="logout.php" data-i18n="logout">Logout</a></div></div>
            </div>
        </div>
        <nav class="mobile-nav">
            <a href="index.php" data-i18n="home">Home</a>
        </nav>
    </header>

    <div id="ef-notification" class="ef-notification">...</div>

    <section id="profile" class="profile-container">
        <aside class="sidebar">
            <div class="user-info">
                <?php
                    $main_avatar_style = ''; $main_avatar_class = ''; $is_frame = false; 
                    if ($border_style == 'rgb') { $main_avatar_class = 'border-rgb'; $main_avatar_style = 'border-width: 4px; border-style: solid;'; }
                    elseif ($border_style == 'gradient-custom') { $main_avatar_class = 'border-gradient-custom'; $gradient_css = 'linear-gradient(45deg, ' . str_replace('|', ', ', htmlspecialchars($border_color)) . ')'; $main_avatar_style = 'border: 4px solid transparent; --custom-gradient: ' . $gradient_css . ';'; }
                    elseif (strpos($border_style, 'frame-') === 0) { $main_avatar_class = 'border-' . $border_style; $main_avatar_style = 'border: 2px solid transparent;'; $is_frame = true; }
                    else { if (preg_match('/^#[0-9A-Fa-f]{6}$/', $border_color)) { $main_avatar_style = 'border: 4px solid ' . htmlspecialchars($border_color) . ';'; } else { $main_avatar_style = 'border: 4px solid #ff8c42;'; } }
                    if ($is_frame) { $main_avatar_class .= ' avatar-padded'; }
                ?>
                <div alt="User Icon" class="user-icon <?php echo $main_avatar_class; ?>" style="background-image: url('img/avatar/<?php echo htmlspecialchars($avatar); ?>'); <?php echo $main_avatar_style; ?>"></div>
                <h3 id="profile-username"><?php echo htmlspecialchars($username); ?></h3>
                <p class="class-display"><span data-i18n="class_label">Класс:</span> <?php echo htmlspecialchars($class); ?></p>
                <p><span data-i18n="user_type_label">Тип:</span> <?php echo htmlspecialchars(ucfirst($user_type)); ?></p>
                <p><span data-i18n="email_label">Email:</span> <?php echo htmlspecialchars($email); ?></p>
                <div class="ef-points"><i class="fas fa-coins"></i> <span class="points-value"><?php echo htmlspecialchars(number_format($points)); ?></span> EF</div>
                <div class="level-progress">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <span style="font-weight:700; color:var(--primary-color);">Уровень <?php echo $level; ?></span>
                        <span style="font-size:11px; color:#64748b;"><?php echo number_format($lvlData['xp_current_level']); ?> / <?php echo number_format($lvlData['xp_next_level']); ?> XP</span>
                    </div>
                    <div class="progress-bar" style="background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden;"><div class="fill" style="width: <?php echo $lvlData['progress']; ?>%; background: linear-gradient(90deg, var(--primary-color), var(--secondary-color)); height:100%; transition: width 0.5s ease;"></div></div>
                </div>
            </div>
            
            <button class="settings-btn" onclick="window.location.href='profile.php'">Settings</button>

            <button onclick="window.location.href='profile.php'" class="settings-btn notification-btn" style="margin-top: 10px;">
                <i class="fas fa-bell"></i> Уведомления
                <?php if($invites_count > 0): ?>
                    <span class="notif-badge"><?php echo $invites_count; ?></span>
                <?php endif; ?>
            </button>

            <?php if ($joined_classes_count > 0): ?>
                <button onclick="window.location.href='profile.php'" class="settings-btn" style="margin-top: 10px; background: #22c55e; color: white;">
                    <i class="fas fa-users"></i> Мои Классы
                    <span style="background:rgba(255,255,255,0.3); padding:0 6px; border-radius:10px; font-size:11px; margin-left:5px;"><?php echo $joined_classes_count; ?></span>
                </button>
            <?php endif; ?>

            <?php if (strtolower(trim($user_type)) === 'teacher') : ?>
                <button class="settings-btn" onclick="window.location.href='teacher_dashboard.php'" style="margin-top: 10px; background: var(--secondary-color);"><i class="fas fa-tools"></i> Сфера разработки</button>
            <?php endif; ?>
            <?php if ($is_admin == 1): ?><div class="admin-buttons-container" style="margin-top:10px;"><a href="admin_frames.php" class="admin-panel-btn" style="text-decoration: none;">Control Server</a></div><?php endif; ?>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
        </aside>

        <main class="main-content">
            <h2 style="margin-bottom: 20px; color: #1e293b;">Управление Классами</h2>
            <?php echo $msg; ?>

            <div class="class-manager-layout">
                <div class="class-sidebar-list">
                    <div class="class-sidebar-header">
                        <button class="create-class-btn-main" onclick="openCreateModal()">
                            <i class="fas fa-plus-circle"></i> Создать Класс
                        </button>
                    </div>
                    <div class="class-list-scroll">
                        <?php if (count($my_created_classes) > 0): ?>
                            <?php foreach($my_created_classes as $cls): 
                                $icon = $cls['avatar'] ?? 'fa-users';
                                $privacy_icon = ($cls['privacy'] ?? 'private') === 'public' ? 'fa-globe' : 'fa-lock';
                                $dataAttr = "data-id='{$cls['id']}' data-name='".htmlspecialchars($cls['name'], ENT_QUOTES)."' data-code='{$cls['join_code']}' data-privacy='".($cls['privacy'] ?? 'private')."' data-grade='{$cls['grade']}' data-students='{$cls['student_count']}' data-teachers='{$cls['teacher_count']}'";
                            ?>
                                <div class="class-item" onclick="loadClassDetails(this)" <?php echo $dataAttr; ?>>
                                    <div class="class-item-icon"><i class="fas <?php echo htmlspecialchars($icon); ?>"></i></div>
                                    <div class="class-item-info">
                                        <div class="class-item-name"><?php echo htmlspecialchars($cls['name']); ?></div>
                                        <div class="class-item-details">
                                            <span><?php echo $cls['grade']; ?> Класс</span>
                                            <span><i class="fas <?php echo $privacy_icon; ?>" style="font-size:10px;"></i></span>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right" style="color:#cbd5e1; font-size:12px;"></i>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-classes"><i class="fas fa-folder-open" style="font-size: 30px; margin-bottom: 10px; color:#cbd5e1;"></i><br>Нет классов</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="class-content-area" id="class-details-container">
                    <div class="empty-selection">
                        <img src="img/classroom_placeholder.png" alt="" style="width: 150px; opacity: 0.5; margin-bottom: 20px;">
                        <h3>Выберите класс слева</h3>
                        <p>Или создайте новый, чтобы начать работу.</p>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <div class="modal-overlay" id="createClassModal">
        <div class="modal-window create-window">
            <h3 style="margin-top:0;">Создать новый класс</h3>
            <form method="POST">
                <input type="hidden" name="create_class" value="1">
                <input type="hidden" name="class_avatar" id="selected-icon-input" value="fa-users">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div><label class="form-label">Название</label><input type="text" name="class_name" class="form-input" required placeholder="Например: 9 &quot;А&quot;"></div>
                    <div><label class="form-label">Параллель</label><select name="class_grade" class="form-input"><?php for($i=1; $i<=11; $i++) echo "<option value='$i'>$i класс</option>"; ?></select></div>
                </div>
                <label class="form-label">Иконка</label>
                <div class="icon-grid">
                    <div class="class-icon-option selected" data-icon="fa-users"><i class="fas fa-users"></i></div>
                    <div class="class-icon-option" data-icon="fa-graduation-cap"><i class="fas fa-graduation-cap"></i></div>
                    <div class="class-icon-option" data-icon="fa-book"><i class="fas fa-book"></i></div>
                    <div class="class-icon-option" data-icon="fa-atom"><i class="fas fa-atom"></i></div>
                    <div class="class-icon-option" data-icon="fa-globe"><i class="fas fa-globe"></i></div>
                    <div class="class-icon-option" data-icon="fa-laptop-code"><i class="fas fa-laptop-code"></i></div>
                </div>
                <label class="form-label">Доступ</label>
                <div class="privacy-toggle-group">
                    <label class="privacy-option"><input type="radio" name="privacy" value="public"><div class="privacy-card"><i class="fas fa-globe"></i><span>Публичный</span></div></label>
                    <label class="privacy-option"><input type="radio" name="privacy" value="private" checked><div class="privacy-card"><i class="fas fa-lock"></i><span>Частный</span></div></label>
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:25px;">
                    <button type="button" class="settings-btn" style="background:#f1f5f9; color:#64748b; width:auto;" onclick="closeCreateModal()">Отмена</button>
                    <button type="submit" class="settings-btn" style="width:auto; padding:0 30px;">Создать</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="classSettingsModal">
        <div class="modal-window settings-window">
            <div class="settings-header">
                <h3>Настройки класса</h3>
                <button class="close-settings-btn" onclick="closeSettingsModal()">&times;</button>
            </div>
            <div class="settings-nav">
                <div class="s-nav-item active" onclick="switchSettingsTab('participants', this)">Участники</div>
                <div class="s-nav-item" onclick="switchSettingsTab('sections', this)">Разделы</div>
            </div>
            <div class="settings-body">
                <div id="set-tab-participants" class="s-tab-content active">
                    <div class="participants-split">
                        <div class="participants-col">
                            <div class="p-header"><h4><i class="fas fa-user-graduate" style="color:var(--primary-color)"></i> Ученики</h4></div>
                            <div class="user-search-wrapper">
                                <input type="text" class="user-search-input" placeholder="Найти и добавить ученика..." onkeyup="searchUsers(this, 'student')">
                                <div class="search-results" id="search-res-student"></div>
                            </div>
                            <ul class="p-list" id="students-list"><li class="empty-state">Загрузка...</li></ul>
                        </div>
                        <div class="participants-col">
                            <div class="p-header"><h4><i class="fas fa-chalkboard-teacher" style="color:var(--secondary-color)"></i> Учителя</h4></div>
                            <div class="user-search-wrapper">
                                <input type="text" class="user-search-input" placeholder="Найти и пригласить учителя..." onkeyup="searchUsers(this, 'teacher')">
                                <div class="search-results" id="search-res-teacher"></div>
                            </div>
                            <ul class="p-list" id="teachers-list"><li class="empty-state">Загрузка...</li></ul>
                        </div>
                    </div>
                </div>
                <div id="set-tab-sections" class="s-tab-content">
                    <div class="section-create-row">
                        <i class="fas fa-folder-plus" style="font-size:20px; color:#cbd5e1;"></i>
                        <input type="text" id="new-section-name" class="section-input" placeholder="Название раздела...">
                        <select id="quick-section-select" class="section-select" onchange="applyQuickName(this)">
                            <option value="">Быстрый выбор...</option>
                            <option value="Математика">Математика</option>
                            <option value="Алгебра">Алгебра</option>
                            <option value="Геометрия">Геометрия</option>
                            <option value="Физика">Физика</option>
                            <option value="Химия">Химия</option>
                            <option value="Введение">Введение</option>
                        </select>
                        <button class="create-btn" onclick="createSection()">Создать</button>
                    </div>
                    <div class="sections-list" id="sections-list-container">
                        <div class="empty-state">Разделов пока нет</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section"><h3 data-i18n="about_edu">About EDU-Familiar.kz</h3><p data-i18n="footer_about_desc">We are a leading educational platform in Kazakhstan, offering cutting-edge courses to prepare students for the future.</p></div>
            <div class="footer-section"><h3 data-i18n="quick_links">Quick Links</h3><a href="index.php#programs" data-i18n="our_programs">Our Programs</a><a href="index.php#reviews" data-i18n="student_reviews">Student Reviews</a><a href="index.php#team" data-i18n="meet_the_team">Meet the Team</a><a href="index.php#partners" data-i18n="our_partners">Our Partners</a><a href="index.php#faq" data-i18n="faq">FAQ</a></div>
            <div class="footer-section"><h3 data-i18n="contact_us">Contact Us</h3><p><span data-i18n="email_label">Email:</span> info@edu-familiar.kz</p><p><span data-i18n="phone_label">Phone:</span> +7 (776) 348-4803</p><p><span data-i18n="address_label">Address:</span> Толстой көшесі, 99, 1-қабат Павлодар, Қазақстан</p></div>
            <div class="footer-section"><h3 data-i18n="follow_us">Follow Us</h3><div class="social-links"><a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a><a href="#" class="social-icon"><i class="fab fa-twitter"></i></a><a href="#" class="fab fa-instagram"></i></a></div><h3 data-i18n="language">Language</h3><div class="language-switcher"><select id="language-select-footer" class="lang-select"><option value="en">EN</option><option value="kz">KZ</option><option value="ru">RU</option></select></div></div>
        </div>
        <div class="footer-bottom"><p data-i18n="copyright">2025 EDU-Familiar.kz. All rights reserved.</p></div>
    </footer>

    <script src="JS/profile-script.js"></script>
    <script src="JS/language.js"></script>
    <script src="JS/class_manager.js"></script>
</body>
</html>