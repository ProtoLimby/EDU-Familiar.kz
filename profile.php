<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php'; 

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ================================================================================================
// === 1. AJAX ОБРАБОТЧИК ДЛЯ УВЕДОМЛЕНИЙ ===
// ================================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'handle_invite') {
    header('Content-Type: application/json');
    
    $inviteId = intval($_POST['invite_id']);
    $status = $_POST['status']; 

    if (!in_array($status, ['accepted', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Недопустимый статус']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE class_members SET status = ? WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Ошибка подготовки запроса']);
        exit;
    }

    $stmt->bind_param("sii", $status, $inviteId, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка базы данных: ' . $conn->error]);
    }
    
    $stmt->close();
    exit; 
}

// ================================================================================================
// === 2. ЗАГРУЗКА ДАННЫХ ===
// ================================================================================================

// --- А. Загружаем рамки ---
$frames_for_css = [];
$frames_sql = "SELECT style_key, image_file FROM ef_premium_frames WHERE is_visible = 1";
$all_frames_result = $conn->query($frames_sql);

if ($all_frames_result) {
    while($frame = $all_frames_result->fetch_assoc()) {
        $frames_for_css[] = $frame;
    }
}

// --- Б. Данные пользователя ---
$user_sql = "SELECT username, full_name, email, user_type, avatar, class, points, xp, avatar_border_color, border_style, ef_premium, is_admin, highest_score, level FROM users WHERE id = ?";
$stmt = $conn->prepare($user_sql);

if (!$stmt) {
    error_log("profile.php: Ошибка подготовки: " . $conn->error);
    die("Ошибка сервера при загрузке профиля.");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Пользователь с ID $user_id не найден!";
    exit;
}

$row = $result->fetch_assoc();

// --- В. Синхронизация уровня ---
$xp = $row['xp'] ?: 0;
$db_level = $row['level'] ?: 1;

$lvlData = calculateLevel($xp); 
$real_level = $lvlData['level'];

if ($db_level != $real_level) {
    $update_lvl_sql = "UPDATE users SET level = ? WHERE id = ?";
    $update_lvl_stmt = $conn->prepare($update_lvl_sql);
    $update_lvl_stmt->bind_param("ii", $real_level, $user_id);
    $update_lvl_stmt->execute();
    $update_lvl_stmt->close();
    $level = $real_level;
} else {
    $level = $db_level;
}

// --- Г. Переменные ---
$username = $row['username'] ?: 'Guest_' . $user_id;
$full_name = $row['full_name'] ?: 'Не указано';
$email = $row['email'];
$user_type = $row['user_type'];
$avatar = $row['avatar'] ?: 'Def_Avatar.jpg';
$class = $row['class'] ?: '';
$points = $row['points'] ?: 0;
$highest_score = $row['highest_score'] ?: 0; 
$border_color = $row['avatar_border_color'] ?: '#ff8c42';
$border_style = $row['border_style'] ?: 'solid-default';
$ef_premium = $row['ef_premium'] ?: 0;
$is_admin = $row['is_admin'] ?: 0;

$stmt->close();

if (strpos($class, 'frame-') !== false || strpos($class, '.') === 0) {
    $class = ''; 
}

// --- Д. Загрузка приглашений (Уведомления) ---
$invites_sql = "
    SELECT 
        cm.id, 
        c.name as class_name, 
        u.full_name as teacher_name, 
        cm.role, 
        cm.added_at as created_at 
    FROM class_members cm
    JOIN classes c ON cm.class_id = c.id
    JOIN users u ON c.teacher_id = u.id
    WHERE cm.user_id = ? AND cm.status = 'pending'
    ORDER BY cm.added_at DESC
";
$inv_stmt = $conn->prepare($invites_sql);
$inv_stmt->bind_param("i", $user_id);
$inv_stmt->execute();
$invites_result = $inv_stmt->get_result();

$invites = [];
while($inv = $invites_result->fetch_assoc()) {
    $invites[] = $inv;
}
$invites_count = count($invites);
$inv_stmt->close();

// --- Е. Загрузка СПИСКА классов (Свои + Вступившие) ---
$joined_classes = [];

// 1. Классы, где я ВЛАДЕЛЕЦ (Создатель)
$own_sql = "SELECT id, name, grade, avatar, 'teacher' as role FROM classes WHERE teacher_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($own_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    $joined_classes[$row['id']] = $row; 
}
$stmt->close();

// 2. Классы, в которые я ВСТУПИЛ (Участник)
$join_sql = "
    SELECT c.id, c.name, c.grade, c.avatar, cm.role 
    FROM class_members cm 
    JOIN classes c ON cm.class_id = c.id 
    WHERE cm.user_id = ? AND cm.status = 'accepted'
    ORDER BY c.created_at DESC
";
$stmt = $conn->prepare($join_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) {
    if (!isset($joined_classes[$row['id']])) {
        $joined_classes[$row['id']] = $row;
    }
}
$stmt->close();
$joined_classes = array_values($joined_classes);
$joined_classes_count = count($joined_classes);


// --- Ж. Рамки для модалки ---
$frames_by_category = [];
if ($ef_premium == 1) {
    $frames_cat_sql = "SELECT name, style_key, image_file, category FROM ef_premium_frames WHERE is_visible = 1 ORDER BY category, name";
    $frames_cat_result = $conn->query($frames_cat_sql);
    if ($frames_cat_result) {
        while($frame = $frames_cat_result->fetch_assoc()) {
            $frames_by_category[$frame['category']][] = $frame;
        }
    }
}

// --- З. Достижения ---
$ach_sql = "
    SELECT 
        l.id as lesson_id,
        l.title as lesson_title,
        l.achievement_name,
        l.achievement_icon,
        MAX(lc.completed_at) as completed_at
    FROM lesson_completions lc
    JOIN lessons l ON lc.lesson_id = l.id
    WHERE lc.user_id = ? 
      AND lc.percentage = 100 
      AND l.achievement_name IS NOT NULL 
      AND l.achievement_name != ''
    GROUP BY l.id, l.title, l.achievement_name, l.achievement_icon
    ORDER BY completed_at DESC
";
$ach_stmt = $conn->prepare($ach_sql);
$ach_stmt->bind_param("i", $user_id);
$ach_stmt->execute();
$ach_result = $ach_stmt->get_result();

$all_achievements = [];
while($ach = $ach_result->fetch_assoc()) {
    $all_achievements[] = $ach;
}
$ach_stmt->close();

$preview_achievements = array_slice($all_achievements, 0, 3);
$has_more_achievements = count($all_achievements) > 3;

// --- И. Рекомендации ---
$rec_sql = "
    SELECT id, title, cover_image, subject 
    FROM lessons 
    WHERE id NOT IN (SELECT lesson_id FROM lesson_completions WHERE user_id = ?)
      AND privacy = 'public' AND is_hidden = 0
    ORDER BY RAND() 
    LIMIT 2
";
$rec_stmt = $conn->prepare($rec_sql);
$rec_stmt->bind_param("i", $user_id);
$rec_stmt->execute();
$rec_result = $rec_stmt->get_result();

$recommendations = [];
while($rec_row = $rec_result->fetch_assoc()) {
    $recommendations[] = $rec_row;
}
$rec_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="profile_title">Profile - EDU-Familiar.kz</title>
    
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/settings.css">
    <link rel="stylesheet" href="CSS/header.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* СТИЛИ УВЕДОМЛЕНИЙ */
        .notification-btn {
            position: relative;
            background: #fff !important;
            color: #1e293b !important;
            border: 1px solid #e2e8f0 !important;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: all 0.3s ease;
        }
        .notification-btn:hover { background: #f1f5f9 !important; transform: translateY(-2px); }
        
        .notif-badge {
            background: #ef4444; color: white; font-size: 11px; font-weight: 700;
            padding: 2px 6px; border-radius: 10px; min-width: 18px; text-align: center;
            box-shadow: 0 2px 5px rgba(239, 68, 68, 0.3);
        }

        .invite-list { display: flex; flex-direction: column; gap: 15px; max-height: 400px; overflow-y: auto; padding-right: 5px; }
        .invite-card {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;
            display: flex; flex-direction: column; gap: 15px; transition: all 0.2s;
        }
        .invite-card:hover { background: #fff; border-color: var(--secondary-color); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .invite-header { display: flex; align-items: center; gap: 15px; }
        .invite-icon {
            width: 50px; height: 50px; background: #eff6ff; color: var(--secondary-color);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .invite-info h4 { margin: 0 0 5px 0; font-size: 16px; color: #1e293b; font-weight: 600; }
        .invite-info p { margin: 0; font-size: 13px; color: #64748b; }
        
        .invite-actions { display: flex; gap: 10px; }
        .invite-btn { flex: 1; padding: 10px; border-radius: 8px; border: none; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s; }
        
        .btn-accept { background: var(--secondary-color); color: white; }
        .btn-accept:hover { background: #604abd; box-shadow: 0 4px 10px rgba(123, 97, 255, 0.2); }
        
        .btn-reject { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }
        .btn-reject:hover { background: #fee2e2; border-color: #ef4444; }

        .empty-invites { text-align: center; padding: 40px; color: #94a3b8; display: flex; flex-direction: column; align-items: center; gap: 15px; }

        /* --- СТИЛИ ДЛЯ СПИСКА МОИХ КЛАССОВ (В МОДАЛКЕ) --- */
        .my-classes-list {
            display: grid; grid-template-columns: 1fr; gap: 15px;
        }
        .my-class-card {
            display: flex; align-items: center; gap: 15px;
            background: #fff; border: 1px solid #e2e8f0;
            padding: 15px; border-radius: 12px;
            text-decoration: none; color: inherit;
            transition: all 0.2s;
        }
        .my-class-card:hover {
            border-color: #22c55e; transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(34, 197, 94, 0.1);
        }
        .mc-icon {
            width: 50px; height: 50px;
            background: #f0fdf4; color: #16a34a;
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 24px;
        }
        .mc-info h4 { margin: 0 0 4px 0; font-size: 16px; color: #1e293b; }
        .mc-info span { font-size: 12px; color: #64748b; display: block; }
        .mc-arrow { margin-left: auto; color: #cbd5e1; }
    </style>

    <?php
    if (!empty($frames_for_css)) {
        echo "<style id=\"dynamic-frame-styles\">\n";
        $after_selectors = [];
        foreach ($frames_for_css as $frame) $after_selectors[] = '.border-' . htmlspecialchars($frame['style_key']) . '::after';
        if (!empty($after_selectors)) {
            echo implode(",\n", $after_selectors) . " { content: ''; position: absolute; top: -2px; left: -2px; right: -2px; bottom: -2px; width: auto; height: auto; background-size: cover; background-position: center; background-repeat: no-repeat; pointer-events: none; }\n\n";
        }
        foreach ($frames_for_css as $frame) {
            $class_name = '.border-' . htmlspecialchars($frame['style_key']);
            $file = 'frames/' . htmlspecialchars($frame['image_file']);
            echo "{$class_name}::after { background-image: url('{$file}'); }\n";
        }
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

    <div id="ef-notification" class="ef-notification"><i class="fas fa-coins"></i><span class="plus">+0</span><span>EF</span></div>

    <section id="profile" class="profile-container">
        <aside class="sidebar">
            <div class="user-info">
                <?php
                    $main_avatar_style = ''; $main_avatar_class = ''; $is_frame = false; 
                    if ($border_style == 'rgb') { $main_avatar_class = 'border-rgb'; $main_avatar_style = 'border-width: 4px; border-style: solid;'; }
                    elseif ($border_style == 'gradient-custom') { $main_avatar_class = 'border-gradient-custom'; $gradient_css = 'linear-gradient(45deg, ' . str_replace('|', ', ', htmlspecialchars($border_color)) . ')'; $main_avatar_style = 'border: 4px solid transparent; --custom-gradient: ' . $gradient_css . ';'; }
                    elseif (strpos($border_style, 'frame-') === 0) { $main_avatar_class = 'border-' . $border_style; $main_avatar_style = 'border: 2px solid transparent;'; $is_frame = true; }
                    else { $main_avatar_style = 'border: 4px solid ' . ((preg_match('/^#[0-9A-Fa-f]{6}$/', $border_color)) ? htmlspecialchars($border_color) : '#ff8c42') . ';'; }
                    if ($is_frame) { $main_avatar_class .= ' avatar-padded'; }
                ?>
                <div alt="User Icon" class="user-icon <?php echo $main_avatar_class; ?>" style="background-image: url('img/avatar/<?php echo htmlspecialchars($avatar); ?>'); <?php echo $main_avatar_style; ?>"></div>
                <h3 id="profile-username" data-username="<?php echo htmlspecialchars($username); ?>"><?php echo htmlspecialchars($username); ?></h3>
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
            
            <button class="settings-btn" data-i18n="settings">Settings</button>

            <div class="sidebar-row">
                <button id="open-notifications-btn" class="settings-btn icon-btn notification-btn">
                    <i class="fas fa-bell"></i>
                    <?php if($invites_count > 0): ?>
                        <span class="notif-badge" id="sidebar-badge"><?php echo $invites_count; ?></span>
                    <?php endif; ?>
                </button>

                <button id="open-classes-btn" class="settings-btn classes-btn">
                    <i class="fas fa-graduation-cap"></i> 
                    <span><?php echo ($joined_classes_count > 0) ? 'Мои Классы' : 'Классы'; ?></span>
                    <?php if ($joined_classes_count > 0): ?>
                        <span class="mini-badge"><?php echo $joined_classes_count; ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <?php if (strtolower(trim($user_type)) === 'teacher') : ?>
                <button class="settings-btn" onclick="window.location.href='teacher_dashboard.php'" style="margin-top: 10px; background: var(--secondary-color);" data-i18n="teacher_dashboard_link"><i class="fas fa-tools"></i> Сфера разработки</button>
            <?php endif; ?>
            
            <?php if ($is_admin == 1): ?><div class="admin-buttons-container"><a href="admin_frames.php" class="admin-panel-btn" style="text-decoration: none;" data-i18n="control_server">Control Server</a></div><?php endif; ?>
            <button class="logout-btn" data-i18n="logout">Logout</button>
        </aside>

        <main class="main-content">
            <div class="small-windows">
                <div class="achievements card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 data-i18n="achievements" style="margin: 0;">Достижения</h3>
                        <span style="font-size: 12px; font-weight: 600; color: var(--primary-color); background: #fff7ed; padding: 2px 8px; border-radius: 10px;"><?php echo count($all_achievements); ?></span>
                    </div>
                    <ul class="ach-list-preview">
                        <?php if (count($preview_achievements) > 0): foreach ($preview_achievements as $ach): ?>
                            <li class="ach-item-mini" title="<?php echo htmlspecialchars($ach['achievement_name']); ?>">
                                <div class="ach-mini-icon"><i class="fas <?php echo htmlspecialchars($ach['achievement_icon'] ?: 'fa-star'); ?>"></i></div>
                                <div class="ach-mini-text"><?php echo htmlspecialchars($ach['achievement_name']); ?></div>
                            </li>
                        <?php endforeach; else: ?><li class="empty-ach" style="border:none; padding:10px 0;">Пока нет достижений</li><?php endif; ?>
                    </ul>
                    <?php if ($has_more_achievements): ?><button id="show-all-ach-btn" class="ach-more-btn">Показать все</button><?php endif; ?>
                </div>
                <div class="leaders card">
                    <h3 data-i18n="leaders">Leaders</h3>
                    <ol id="leaderboard-list"><li class="loading" data-i18n="loading">Загрузка...</li></ol>
                    <p class="user-position" id="user-position" data-i18n="your_position">Your Position: —</p>
                </div>
                <div class="highest-score card">
                    <h3 data-i18n="highest_score">Highest Score</h3>
                    <p><span data-i18n="highest_score_value">Best Score:</span> <?php echo htmlspecialchars(number_format($highest_score)); ?> EF</p>
                </div>
            </div>

            <div class="cta-banner">
                <h3 data-i18n="cta_tasks_title">Take on New Tasks!</h3>
                <p data-i18n="cta_tasks_desc">Challenge yourself with new assignments to boost your skills!</p>
                <a href="tasks.html" class="tasks-btn" data-i18n="explore_tasks">Explore Tasks</a>
            </div>

            <div class="motivational-banner">
                <h2 data-i18n="motivational_title">Keep Learning and Achieve More!</h2>
                <p data-i18n="motivational_desc">Every step you take brings you closer to your goals. Stay motivated!</p>
            </div>

            <div class="course-recommendations-container">
                <h2 data-i18n="course_recommendations">Рекомендации уроков</h2>
                <?php if (count($recommendations) > 0): ?>
                    <div class="cards">
                        <?php foreach ($recommendations as $rec): 
                            $cover = $rec['cover_image'];
                            $is_img = (!empty($cover) && $cover !== 'default_lesson.jpg');
                            $img_src = (strpos($cover, 'data:') === 0) ? $cover : "uploads/lessons/covers/" . htmlspecialchars($cover);
                            $subj = mb_strtolower($rec['subject']);
                            $bg_gradient = 'linear-gradient(135deg, #64748b, #475569)'; 
                            if (strpos($subj, 'математ') !== false) $bg_gradient = 'linear-gradient(135deg, #3b82f6, #2563eb)';
                            elseif (strpos($subj, 'физик') !== false) $bg_gradient = 'linear-gradient(135deg, #8b5cf6, #7c3aed)';
                            elseif (strpos($subj, 'хим') !== false || strpos($subj, 'биолог') !== false) $bg_gradient = 'linear-gradient(135deg, #10b981, #059669)';
                            elseif (strpos($subj, 'истор') !== false) $bg_gradient = 'linear-gradient(135deg, #f59e0b, #d97706)';
                            elseif (strpos($subj, 'язык') !== false) $bg_gradient = 'linear-gradient(135deg, #ef4444, #dc2626)';
                            elseif (strpos($subj, 'информ') !== false) $bg_gradient = 'linear-gradient(135deg, #0ea5e9, #0284c7)';
                        ?>
                            <div class="card" onclick="window.location.href='lesson_details.php?id=<?php echo $rec['id']; ?>'" style="cursor: pointer;">
                                <?php if ($is_img): ?><img src="<?php echo $img_src; ?>" alt="Cover" class="card-image"><?php else: ?><div class="card-image" style="background: <?php echo $bg_gradient; ?>; display: flex; align-items: center; justify-content: center; color: white; font-size: 40px;"><i class="fas fa-book-reader"></i></div><?php endif; ?>
                                <p style="font-weight: 600; margin-top: 10px;"><?php echo htmlspecialchars($rec['title']); ?></p>
                                <span style="font-size: 12px; color: #94a3b8;"><?php echo htmlspecialchars($rec['subject']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 20px; color: #94a3b8; background: #fff; border-radius: 12px; border: 1px dashed #e2e8f0;"><i class="fas fa-check-circle" style="font-size: 30px; margin-bottom: 10px; color: #22c55e;"></i><p>Вы прошли все доступные уроки! Отличная работа.</p></div>
                <?php endif; ?>
            </div>

            <div class="certificates-container">
                <h2 data-i18n="certificates">Certificates</h2>
                <div class="cards">
                    <div class="card"><img src="img/serif py.jpg" alt="Certificate" class="card-image"><p data-i18n="certificate1">Certificate: Introduction to Coding, 10.10.2025</p></div>
                    <div class="card"><img src="img/sertif m.png" alt="Certificate" class="card-image"><p data-i18n="certificate2">Certificate: Data Science Basics, 15.09.2025</p></div>
                </div>
            </div>
        </main>
    </section>

    <div class="settings-modal">
        <div class="modal-content">
            <div class="modal-header"><h2 data-i18n="profile_settings">Настройки профиля</h2><button class="close-modal" aria-label="Close">×</button></div>
            <div class="profile-grid">
                <div class="avatar-column">
                    <div class="avatar-box"><div class="main-avatar <?php echo $modal_class; ?>" id="main-avatar-preview" style="background-image: url('img/avatar/<?php echo htmlspecialchars($avatar); ?>'); <?php echo $modal_style; ?>"></div><div class="avatar-glow"></div></div>
                    <button class="upload-btn" data-i18n="upload_avatar"><i class="fas fa-camera"></i> Загрузить аватар</button>
                    <input type="file" id="avatar-upload" accept="image/*" style="display: none;">
                </div>
                <div class="info-edit-column">
                    <div class="info-card"><h3 data-i18n="user_info">Ваши данные</h3><div class="info-row"><span data-i18n="username">Логин:</span><strong id="display-username"><?php echo htmlspecialchars($username); ?></strong></div><div class="info-row"><span data-i18n="full_name">ФИО:</span><strong id="display-fullname"><?php echo htmlspecialchars($full_name); ?></strong></div><div class="info-row"><span data-i18n="class_label">Класс:</span><strong id="display-class"><?php echo $class ?: '<span data-i18n="not_specified">Не указан</span>'; ?></strong></div></div>
                    <div class="edit-card"><h3 data-i18n="edit_profile">Редактировать</h3><label data-i18n="new_username">Новый логин</label><input type="text" id="new-username" value="<?php echo htmlspecialchars($username); ?>"><label data-i18n="new_fullname">Новое ФИО</label><input type="text" id="new-full-name" value="<?php echo htmlspecialchars($full_name); ?>"><label data-i18n="class_select">Класс</label><select id="new-class"><option value="" data-i18n="select_class_empty">—</option><?php for ($i = 1; $i <= 11; $i++): ?><option value="<?php echo $i; ?>" <?php echo $class == $i ? 'selected' : ''; ?>><?php echo $i; ?> класс</option><?php endfor; ?></select><button class="save-btn" data-i18n="save_changes">Сохранить изменения</button></div>
                </div>
                <div class="customization-column">
                    <div class="customization-card"><h3 data-i18n="avatar_border_settings">Оформление аватара</h3>
                        <div class="tabs"><button class="tab-btn active" data-tab="standard" data-i18n="standard">Standard</button><button class="tab-btn premium" data-tab="efpremium" data-i18n="efpremium">EFPremium</button></div>
                        <div class="tab-content active" id="standard-tab"><div class="control-row"><label data-i18n="border_color">Цвет рамки</label><input type="color" id="border-color" value="<?php echo (strpos($border_color, '#') === 0 ? htmlspecialchars($border_color) : '#ff8c42'); ?>"></div><div class="preview-row"><span data-i18n="border_preview">Превью:</span><div class="border-preview <?php echo $modal_class; ?>" id="avatar-preview" style="background-image: url('img/avatar/<?php echo htmlspecialchars($avatar); ?>'); <?php echo $modal_style; ?>"></div></div></div>
                        <div class="tab-content" id="efpremium-tab">
                            <?php if ($ef_premium == 1): ?>
                                <div class="premium-sections-container">
                                    <div class="premium-section"><h3 data-i18n="frame_rgb">RGB</h3><div class="grid-scroll-wrapper"><button class="scroll-btn left">&lt;</button><div class="efpremium-frames-grid"><div class="border-cell <?php echo ($border_style == 'rgb' ? 'selected' : ''); ?>" data-border-style="rgb" data-border-color="rgb" data-border-width="4px"><div class="border-preview-cell border-rgb"></div><p class="border-label" data-i18n="frame_rgb">RGB</p></div></div><button class="scroll-btn right">&gt;</button></div></div>
                                    <?php foreach ($frames_by_category as $category_name => $frames): ?><div class="premium-section"><h3><?php echo htmlspecialchars($category_name); ?></h3><div class="grid-scroll-wrapper"><button class="scroll-btn left">&lt;</button><div class="efpremium-frames-grid"><?php foreach ($frames as $frame): $is_selected = ($border_style == $frame['style_key']) ? 'selected' : ''; ?><div class="border-cell <?php echo $is_selected; ?>" data-border-style="<?php echo $frame['style_key']; ?>" data-border-color="frame" data-border-width="2px"><div class="border-preview-cell" style="background-image: url('frames/<?php echo $frame['image_file']; ?>'); border: none; background-size: cover;"></div><p class="border-label"><?php echo $frame['name']; ?></p></div><?php endforeach; ?></div><button class="scroll-btn right">&gt;</button></div></div><?php endforeach; ?>
                                </div> 
                            <?php else: ?><p data-i18n="efpremium_required_frames" class="premium-notice">Получите EFPremium для доступа к эксклюзивным рамкам!</p><a href="premium.php" class="premium-link" data-i18n="get_premium">Получить EFPremium</a><?php endif; ?>
                        </div>
                        <button class="save-border-btn" data-i18n="save_border">Сохранить рамку</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button class="close-menu-btn" data-i18n="close">Закрыть</button></div>
        </div>
    </div>

    <div class="confirm-modal-backdrop" id="joinConfirmBackdrop"></div>
    <div class="confirm-modal" id="joinConfirmModal">
        <div class="confirm-icon-box">
            <i class="fas fa-school"></i>
        </div>
        <h3>Вступить в класс?</h3>
        <p>Вы действительно хотите присоединиться к публичному классу <span id="join-class-name-target" class="class-name-highlight"></span>?</p>
        
        <div class="confirm-actions">
            <button class="confirm-btn c-btn-cancel" onclick="closeJoinConfirm()">Отмена</button>
            <button class="confirm-btn c-btn-join" id="confirm-join-btn">Вступить</button>
        </div>
    </div>

    <div id="my-classes-modal" class="settings-modal">
        <div class="modal-content class-hub-modal">
            <div class="modal-header">
                <h2><i class="fas fa-school" style="color:var(--secondary-color);"></i> WEB-Aудитория</h2>
                <button id="close-classes-modal" class="close-modal" aria-label="Close">×</button>
            </div>
            
            <div class="hub-top-bar">
                <div class="hub-search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="public-class-search" placeholder="Поиск публичных классов..." onkeyup="searchPublicClasses(this.value, 1)">
                </div>
                <div class="hub-code-box">
                    <input type="text" id="join-class-code" placeholder="Код класса" maxlength="6" autocomplete="off">
                    <button onclick="joinClassByCode()"><i class="fas fa-sign-in-alt"></i> Войти</button>
                </div>
            </div>

            <div class="hub-split-body">
                
                <div class="hub-column left-col">
                    <h3 class="col-title">Мои Классы <span class="count-badge"><?php echo count($joined_classes); ?></span></h3>
                    <div class="hub-list-scroll">
                        <?php if (count($joined_classes) > 0): ?>
                            <div class="my-classes-vertical">
                                <?php foreach ($joined_classes as $jc): 
                                     $c_icon = $jc['avatar'] ?? 'fa-users';
                                     $role_badge = ($jc['role'] === 'teacher') 
                                        ? '<span class="role-tag teacher">Учитель</span>' 
                                        : '<span class="role-tag student">Ученик</span>';
                                ?>
                                    <a href="class_view.php?id=<?php echo $jc['id']; ?>" class="my-class-row">
                                        <div class="mc-icon-small"><i class="fas <?php echo htmlspecialchars($c_icon); ?>"></i></div>
                                        <div class="mc-info">
                                            <h4><?php echo htmlspecialchars($jc['name']); ?></h4>
                                            <div class="mc-meta"><?php echo $jc['grade']; ?> кл. &bull; <?php echo $role_badge; ?></div>
                                        </div>
                                        <i class="fas fa-chevron-right mc-arrow"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-column-state">
                                <i class="fas fa-folder-open"></i>
                                <p>Вы пока не состоите в классах.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="hub-column right-col">
                    <h3 class="col-title">Каталог Классов</h3>
                    <div id="public-classes-results" class="public-classes-grid">
                        <div class="hub-loading">Загрузка каталога...</div>
                    </div>
                    
                    <div class="hub-pagination" id="hub-pagination-controls">
                        </div>
                </div>

            </div>
        </div>
    </div>

    <div id="notifications-modal" class="settings-modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header"><h2><i class="fas fa-bell" style="color:var(--secondary-color);"></i> Уведомления</h2><button id="close-notif-modal" class="close-modal" aria-label="Close">×</button></div>
            <div style="padding: 30px;">
                <div class="invite-list" id="invite-list-container">
                    <?php if ($invites_count > 0): foreach ($invites as $inv): ?>
                        <div class="invite-card" id="invite-<?php echo $inv['id']; ?>">
                            <div class="invite-header"><div class="invite-icon"><i class="fas fa-chalkboard-teacher"></i></div><div class="invite-info"><h4>Приглашение в класс: <strong><?php echo htmlspecialchars($inv['class_name']); ?></strong></h4><p>Преподаватель: <?php echo htmlspecialchars($inv['teacher_name']); ?></p><p style="font-size:11px; margin-top:3px;">Роль: <?php echo htmlspecialchars($inv['role'] === 'student' ? 'Ученик' : 'Учитель'); ?></p></div></div>
                            <div class="invite-actions"><button class="invite-btn btn-accept" onclick="handleInvite(<?php echo $inv['id']; ?>, 'accepted')">Принять</button><button class="invite-btn btn-reject" onclick="handleInvite(<?php echo $inv['id']; ?>, 'rejected')">Отклонить</button></div>
                        </div>
                    <?php endforeach; else: ?><div class="empty-invites"><i class="far fa-bell-slash" style="font-size:40px; margin-bottom:15px; opacity:0.3;"></i><p>У вас нет новых уведомлений.</p></div><?php endif; ?>
                </div>
            </div>
            <div class="modal-footer"><button id="close-notif-btn-footer" class="close-menu-btn">Закрыть</button></div>
        </div>
    </div>

    <div id="achievements-modal" class="settings-modal">
        <div class="modal-content">
            <div class="modal-header"><h2>Все ваши достижения</h2><button id="close-ach-modal" class="close-modal" aria-label="Close">×</button></div>
            <div class="ach-grid">
                <?php if (count($all_achievements) > 0): foreach ($all_achievements as $ach): ?>
                    <div class="ach-card" onclick="window.location.href='lesson_details.php?id=<?php echo $ach['lesson_id']; ?>'">
                        <div class="ach-card-icon"><i class="fas <?php echo htmlspecialchars($ach['achievement_icon'] ?: 'fa-star'); ?>"></i></div>
                        <div class="ach-card-title"><?php echo htmlspecialchars($ach['achievement_name']); ?></div>
                        <span class="ach-card-date"><i class="far fa-calendar-alt"></i> <?php echo date('d.m.Y', strtotime($ach['completed_at'])); ?></span>
                        <div class="ach-card-link">Перейти к уроку <i class="fas fa-arrow-right" style="font-size: 10px; margin-left: 3px;"></i></div>
                    </div>
                <?php endforeach; else: ?><div class="empty-ach"><i class="fas fa-trophy" style="font-size: 40px; margin-bottom: 15px; opacity: 0.3;"></i><p>Вы пока не получили ни одного достижения.<br>Проходите уроки на 100%, чтобы открыть их!</p></div><?php endif; ?>
            </div>
            <div class="modal-footer"><button id="close-ach-btn-footer" class="close-menu-btn">Закрыть</button></div>
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
    <script src="JS/coins.js"></script>
    <script src="JS/class_manager.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Достижения
            const achModal = document.getElementById('achievements-modal');
            const showAchBtn = document.getElementById('show-all-ach-btn');
            const closeAchX = document.getElementById('close-ach-modal');
            const closeAchFooter = document.getElementById('close-ach-btn-footer');

            if (showAchBtn) showAchBtn.addEventListener('click', () => achModal.classList.add('active'));
            const closeAch = () => achModal.classList.remove('active');
            if(closeAchX) closeAchX.addEventListener('click', closeAch);
            if(closeAchFooter) closeAchFooter.addEventListener('click', closeAch);
            
            // Уведомления
            const notifModal = document.getElementById('notifications-modal');
            const notifBtn = document.getElementById('open-notifications-btn');
            const closeNotifX = document.getElementById('close-notif-modal');
            const closeNotifFooter = document.getElementById('close-notif-btn-footer');

            if (notifBtn) notifBtn.addEventListener('click', () => notifModal.classList.add('active'));
            const closeNotif = () => notifModal.classList.remove('active');
            if(closeNotifX) closeNotifX.addEventListener('click', closeNotif);
            if(closeNotifFooter) closeNotifFooter.addEventListener('click', closeNotif);

            // Мои Классы
            const classesModal = document.getElementById('my-classes-modal');
            const classesBtn = document.getElementById('open-classes-btn');
            const closeClassesX = document.getElementById('close-classes-modal');
            const closeClassesFooter = document.getElementById('close-classes-btn-footer');

            if (classesBtn) classesBtn.addEventListener('click', () => classesModal.classList.add('active'));
            const closeClasses = () => classesModal.classList.remove('active');
            if(closeClassesX) closeClassesX.addEventListener('click', closeClasses);
            if(closeClassesFooter) closeClassesFooter.addEventListener('click', closeClasses);

            window.addEventListener('click', (e) => {
                if (e.target === achModal) closeAch();
                if (e.target === notifModal) closeNotif();
                if (e.target === classesModal) closeClasses();
            });
        });

        function handleInvite(inviteId, status) {
            const formData = new FormData();
            formData.append('action', 'handle_invite');
            formData.append('invite_id', inviteId);
            formData.append('status', status);

            fetch('profile.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const card = document.getElementById('invite-' + inviteId);
                    if(card) { card.style.opacity = '0'; setTimeout(() => card.remove(), 300); }
                    
                    const badge = document.getElementById('sidebar-badge');
                    if (badge) {
                        let count = parseInt(badge.innerText) - 1;
                        if (count <= 0) badge.remove(); else badge.innerText = count;
                    }
                    
                    const list = document.getElementById('invite-list-container');
                    setTimeout(() => {
                        if (list && list.children.length === 0) {
                            list.innerHTML = `<div class="empty-invites"><i class="far fa-bell-slash" style="font-size:40px; margin-bottom:15px; opacity:0.3;"></i><p>У вас нет новых уведомлений.</p></div>`;
                        }
                    }, 350);

                    if(status === 'accepted') setTimeout(() => window.location.reload(), 500);
                } else {
                    alert('Ошибка: ' + data.message);
                }
            })
            .catch(err => { console.error(err); alert('Ошибка соединения'); });
        }
    </script>
</body>
<audio id="coin-sound" src="sounds/coin.mp3" preload="auto"></audio>
</html>