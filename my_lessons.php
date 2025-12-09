<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php'; // Подключаем functions.php для тем

// 1. Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. Получаем полные данные пользователя
$stmt = $conn->prepare("SELECT username, full_name, email, user_type, avatar, class, points, avatar_border_color, border_style, ef_premium, is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

// 3. Проверка прав (Учитель или Админ)
if (!$user || (strtolower(trim($user['user_type'])) !== 'teacher' && $user['is_admin'] != 1)) {
    echo "<script>alert('Доступ запрещен. Только для учителей.'); window.location.href='profile.php';</script>";
    exit;
}

// 4. Логика удаления урока
$msg = "";
if (isset($_POST['delete_lesson_id'])) {
    $del_id = intval($_POST['delete_lesson_id']);
    $del_stmt = $conn->prepare("DELETE FROM lessons WHERE id = ? AND user_id = ?");
    $del_stmt->bind_param("ii", $del_id, $user_id);
    
    if ($del_stmt->execute()) {
        $msg = "<div class='alert success' style='margin-bottom:20px; padding:15px; background:#dcfce7; color:#166534; border-radius:12px; border: 1px solid #bbf7d0; display:flex; align-items:center; gap:10px;'><i class='fas fa-check-circle'></i> Урок успешно удален.</div>";
    } else {
        $msg = "<div class='alert error' style='margin-bottom:20px; padding:15px; background:#fee2e2; color:#991b1b; border-radius:12px; border: 1px solid #fecaca; display:flex; align-items:center; gap:10px;'><i class='fas fa-exclamation-circle'></i> Ошибка удаления.</div>";
    }
}

// 5. Загрузка списка уроков
$lessons_res = $conn->query("SELECT * FROM lessons WHERE user_id = $user_id ORDER BY created_at DESC");

// 6. Подготовка переменных для сайдбара
$username = $user['username'] ?: 'Guest';
$full_name = $user['full_name'];
$email = $user['email'] ?: '';
$avatar = $user['avatar'] ?: 'Def_Avatar.jpg';
$class = $user['class'] ?: '';
$points = $user['points'] ?: 0;
$border_color = $user['avatar_border_color'] ?: '#ff8c42';
$border_style = $user['border_style'] ?: 'solid-default';
$user_type = $user['user_type'];
$ef_premium = $user['ef_premium'] ?: 0;
$is_admin = $user['is_admin'] ?: 0;

if (strpos($class, 'frame-') !== false || strpos($class, '.') === 0) { $class = ''; }

// 7. Загрузка рамок
$frames_for_css = [];
$all_frames_result = $conn->query("SELECT style_key, image_file FROM ef_premium_frames WHERE is_visible = 1");
if ($all_frames_result) {
    while($frame = $all_frames_result->fetch_assoc()) {
        $frames_for_css[] = $frame;
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="my_lessons_title">Мои Уроки - EDU-Familiar</title>
    
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/settings.css">
    <link rel="stylesheet" href="CSS/header.css"> 
    <link rel="stylesheet" href="CSS/online-book-styles.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* Стили для баннера */
        .dashboard-banner {
            background: linear-gradient(135deg, #22c55e 0%, #4ade80 100%);
            border-radius: 16px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(34, 197, 94, 0.2);
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dashboard-banner h2 { font-size: 24px; margin-bottom: 5px; position: relative; z-index: 2; color: white; }
        .dashboard-banner p { opacity: 0.9; font-size: 14px; position: relative; z-index: 2; margin: 0; color: rgba(255,255,255,0.9); }
        .dashboard-banner::after {
            content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px;
            background: rgba(255,255,255,0.1); border-radius: 50%;
        }

        .banner-action-btn {
            background: white; color: #166534; padding: 10px 20px; border-radius: 10px;
            text-decoration: none; font-weight: 600; font-size: 14px; position: relative; z-index: 2;
            transition: transform 0.2s, box-shadow 0.2s; display: flex; align-items: center; gap: 8px;
        }
        .banner-action-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }

        /* Кнопки управления в карточке */
        .admin-actions {
            display: flex; gap: 8px; margin-top: auto; padding-top: 15px; border-top: 1px solid #f1f5f9;
        }
        .action-btn {
            flex: 1; padding: 8px; border-radius: 8px; border: none; font-size: 13px; font-weight: 600;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px;
            text-decoration: none; transition: all 0.2s;
        }
        .btn-edit { background: #eff6ff; color: #3b82f6; }
        .btn-edit:hover { background: #3b82f6; color: white; }
        .btn-delete { background: #fef2f2; color: #ef4444; }
        .btn-delete:hover { background: #ef4444; color: white; }

        .empty-state {
            grid-column: 1/-1; text-align: center; padding: 50px; color: #94a3b8;
            background: white; border-radius: 16px; border: 2px dashed #e2e8f0;
        }

        /* --- Стили для генерации обложек (из online-book-styles.css) --- */
        .generated-cover {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 50px; text-shadow: 0 4px 10px rgba(0,0,0,0.2);
            position: relative; overflow: hidden;
        }
        .generated-cover::after {
            content: ''; position: absolute; top: -50%; right: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            pointer-events: none;
        }
    </style>

    <?php
    if (!empty($frames_for_css)) {
        echo "<style id=\"dynamic-frame-styles\">\n";
        $after_selectors = [];
        foreach ($frames_for_css as $frame) $after_selectors[] = '.border-' . htmlspecialchars($frame['style_key']) . '::after';
        echo implode(",\n", $after_selectors) . " { content: ''; position: absolute; top: -2px; left: -2px; right: -2px; bottom: -2px; width: auto; height: auto; background-size: cover; background-position: center; background-repeat: no-repeat; pointer-events: none; }\n";
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
                <div class="dropdown">
                    <a href="index.php" class="dropbtn" data-i18n="home">Home</a> 
                    <div class="dropdown-content">
                        <a href="index.php#about" data-i18n="about">About</a>
                        <a href="index.php#programs" data-i18n="programs">Programs</a>
                        <a href="index.php#reviews" data-i18n="reviews">Reviews</a>
                        <a href="index.php#team" data-i18n="team">Team</a>
                        <a href="index.php#partners" data-i18n="partners">Partners</a>
                    </div>
                </div>
                <a href="training.html" data-i18n="training">Training</a> 
                <a href="best-students.php" data-i18n="best_students">Best Students</a> 
                <a href="online-book.php" data-i18n="online_book">Online Book</a>
                <a href="shop.html" data-i18n="catalog">Каталог</a>
            </nav>
            <div class="header-actions">
                <div class="language-switcher">
                    <select id="language-select-header" class="lang-select">
                        <option value="en">EN</option>
                        <option value="kz">KZ</option>
                        <option value="ru">RU</option>
                    </select>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <a href="profile.php" class="login-btn dropbtn" data-i18n="profile">Profile</a>
                        <div class="dropdown-content">
                            <a href="logout.php" data-i18n="logout">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="login-btn" data-i18n="login">Login</a>
                <?php endif; ?>
                <button class="burger-menu" aria-label="Toggle Menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
        <nav class="mobile-nav">
            <a href="index.php" data-i18n="home">Home</a>
            <a href="index.php#about" data-i18n="about">About</a>
            <a href="index.php#programs" data-i18n="programs">Programs</a>
            <a href="index.php#reviews" data-i18n="reviews">Reviews</a>
            <a href="index.php#team" data-i18n="team">Team</a>
            <a href="index.php#partners" data-i18n="partners">Partners</a>
            <div class="mobile-actions">
                <div class="language-switcher">
                    <select id="language-select-mobile" class="lang-select">
                        <option value="en">EN</option>
                        <option value="kz">KZ</option>
                        <option value="ru">RU</option>
                    </select>
                </div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <a href="profile.php" class="login-btn dropbtn" data-i18n="profile">Profile</a>
                        <div class="dropdown-content">
                            <a href="logout.php" data-i18n="logout">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="login-btn" data-i18n="login">Login</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <div id="ef-notification" class="ef-notification">
        <i class="fas fa-coins"></i>
        <span class="plus">+0</span>
        <span>EF</span>
    </div>

    <section id="profile" class="profile-container">
        
        <aside class="sidebar">
            <div class="user-info">
                <?php
                    $main_avatar_style = ''; $main_avatar_class = ''; $is_frame = false; 
                    if ($border_style == 'rgb') { $main_avatar_class = 'border-rgb'; $main_avatar_style = 'border-width: 4px; border-style: solid;'; }
                    elseif ($border_style == 'gradient-custom') { 
                        $main_avatar_class = 'border-gradient-custom'; 
                        $gradient_css = 'linear-gradient(45deg, ' . str_replace('|', ', ', htmlspecialchars($border_color)) . ')';
                        $main_avatar_style = 'border: 4px solid transparent; --custom-gradient: ' . $gradient_css . ';';
                    } elseif (strpos($border_style, 'frame-') === 0) { 
                        $main_avatar_class = 'border-' . $border_style; $main_avatar_style = 'border: 2px solid transparent;'; $is_frame = true; 
                    } else { 
                        $main_avatar_style = 'border: 4px solid ' . ((preg_match('/^#[0-9A-Fa-f]{6}$/', $border_color)) ? htmlspecialchars($border_color) : '#ff8c42') . ';';
                    }
                    if ($is_frame) { $main_avatar_class .= ' avatar-padded'; }
                ?>
                <div alt="User Icon" 
                     class="user-icon <?php echo $main_avatar_class; ?>" 
                     style="background-image: url('img/avatar/<?php echo htmlspecialchars($avatar); ?>'); <?php echo $main_avatar_style; ?>">
                </div>

                <h3 id="profile-username" data-username="<?php echo htmlspecialchars($username); ?>">
                    <?php echo htmlspecialchars($username); ?>
                </h3>
                
                <p class="class-display" id="main-class"><span data-i18n="class_label">Класс:</span> <?php echo htmlspecialchars($class); ?></p>
                <p><span data-i18n="user_type_label">Тип:</span> <?php echo htmlspecialchars(ucfirst($user_type)); ?></p>
                <p><span data-i18n="email_label">Email:</span> <?php echo htmlspecialchars($email); ?></p>
                
                <div class="ef-points">
                    <i class="fas fa-coins"></i>
                    <span class="points-value"><?php echo htmlspecialchars($points); ?></span>
                    <span class="points-label">EF</span>
                </div>
                
                <div class="level-progress">
                    <span data-i18n="level_1">Уровень 1</span>
                    <div class="progress-bar">
                        <div class="fill" style="width: 83%"></div>
                    </div>
                    <span>0 / 1,500 EF</span>
                </div>
            </div>

            <button class="settings-btn" onclick="window.location.href='profile.php'" data-i18n="settings">Settings</button>
            
            <?php
            if (isset($user_type) && strtolower(trim($user_type)) === 'teacher') :
            ?>
                <button class="settings-btn" onclick="window.location.href='teacher_dashboard.php'" style="margin-top: 10px; background: var(--secondary-color);">
                    <i class="fas fa-tools"></i> <span data-i18n="teacher_dashboard_link">Сфера разработки</span>
                </button>
            <?php
            endif;
            ?>

            <?php if ($is_admin == 1): ?>
                <div class="admin-buttons-container" style="margin-top: 10px;">
                    <a href="admin_frames.php" class="admin-panel-btn" style="text-decoration: none;" data-i18n="control_server">
                         Control Server
                    </a>
                </div>
            <?php endif; ?>

            <button class="logout-btn" onclick="window.location.href='logout.php'" data-i18n="logout">Logout</button>
        </aside>

        <main class="main-content">
            
            <div class="dashboard-banner">
                <div>
                    <h2 data-i18n="my_lessons_page_title"><i class="fas fa-chalkboard-teacher"></i> Мои Уроки</h2>
                    <p data-i18n="my_lessons_desc">Управляйте созданными тестами и обучающими материалами</p>
                </div>
                <a href="create_lesson.php" class="banner-action-btn">
                    <i class="fas fa-plus"></i> <span data-i18n="create_lesson_button">Создать</span>
                </a>
            </div>

            <?php echo $msg; ?>

            <div class="books-grid">
                <?php if ($lessons_res && $lessons_res->num_rows > 0): ?>
                    <?php while($lesson = $lessons_res->fetch_assoc()): ?>
                        <div class="book-card">
                            <div class="book-cover-wrapper" style="height: 160px;">
                                <?php 
                                    $cover = $lesson['cover_image'];
                                    
                                    // Логика отображения обложки (картинка или генерация)
                                    if (!empty($cover) && $cover !== 'default_lesson.jpg') {
                                        $src = (strpos($cover, 'data:image') === 0) ? $cover : "uploads/lessons/covers/" . htmlspecialchars($cover);
                                        echo '<img src="' . $src . '" alt="Cover" class="book-image" style="object-fit: cover;">';
                                    } else {
                                        // === ИСПОЛЬЗУЕМ НОВУЮ ФУНКЦИЮ ИЗ functions.php ===
                                        $themeData = getSubjectTheme($lesson['subject']);
                                        $theme = $themeData['style'];
                                        $icon = $themeData['icon'];

                                        echo '<div class="generated-cover ' . $theme . '"><i class="fas ' . $icon . '"></i></div>';
                                    }
                                ?>
                                
                                <div class="book-rating-badge">
                                    <span style="margin-right: 8px;"><i class="fas fa-star" style="color:#ffd700;"></i> <?php echo number_format($lesson['rating'], 1); ?></span>
                                    <span><i class="fas fa-coins"></i> <?php echo $lesson['coins_reward']; ?></span>
                                </div>
                            </div>
                            
                            <div class="book-details-content">
                                <div class="book-meta-tags">
                                    <?php 
                                        $lang_string = $lesson['language'] ?? 'ru';
                                        $lang_array = explode(',', $lang_string);
                                        
                                        foreach($lang_array as $code) {
                                            $code = trim(strtolower($code));
                                            if(!$code) continue;
                                            $label = strtoupper($code);
                                            $style = 'background: #f1f5f9; color: #64748b;'; // Дефолт

                                            if ($code === 'kz') {
                                                $style = 'background: #e0f2f1; color: #00695c; border: 1px solid #b2dfdb;';
                                            } elseif ($code === 'en') {
                                                $style = 'background: #f3e5f5; color: #7b1fa2; border: 1px solid #e1bee7;';
                                            } elseif ($code === 'ru') {
                                                $style = 'background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb;';
                                            }
                                            
                                            echo "<span class='tag' style='$style'>$label</span>";
                                        }
                                    ?>
                                    
                                    <span class="tag"><?php echo htmlspecialchars($lesson['subject']); ?></span>
                                    <span class="tag class-tag"><?php echo $lesson['grade']; ?> кл.</span>
                                </div>
                                
                                <h3 style="font-size:16px; margin-bottom:10px;"><?php echo htmlspecialchars($lesson['title']); ?></h3>
                                
                                <div class="admin-actions">
                                    <a href="create_lesson.php?id=<?php echo $lesson['id']; ?>" class="action-btn btn-edit">
                                        <i class="fas fa-pen"></i> <span data-i18n="edit_btn">Изм.</span>
                                    </a>
                                    <form method="POST" style="flex:1;" onsubmit="return confirm('Вы уверены, что хотите удалить этот урок?');">
                                        <input type="hidden" name="delete_lesson_id" value="<?php echo $lesson['id']; ?>">
                                        <button type="submit" class="action-btn btn-delete" style="width:100%;">
                                            <i class="fas fa-trash"></i> <span data-i18n="delete_btn">Удал.</span>
                                        </button>
                                    </form>
                                </div>
                                
                                <a href="lesson_details.php?id=<?php echo $lesson['id']; ?>" style="display:block; text-align:center; margin-top:8px; font-size:11px; color:#94a3b8; text-decoration:none;">
                                    <span data-i18n="view_btn">Просмотр</span> <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 15px; color: #cbd5e1;"></i>
                        <h3 style="font-size:18px; margin-bottom:10px;" data-i18n="no_lessons_title">Уроков пока нет</h3>
                        <p style="margin-bottom:20px;" data-i18n="no_lessons_desc">Начните создавать свои первые интерактивные уроки.</p>
                        <a href="create_lesson.php" class="settings-btn" style="display:inline-block; width:auto; background: var(--primary-color);" data-i18n="create_first_lesson">
                            Создать первый урок
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </section>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3 data-i18n="about_edu">About EDU-Familiar.kz</h3>
                <p data-i18n="footer_about_desc">We are a leading educational platform in Kazakhstan, offering cutting-edge courses to prepare students for the future.</p>
            </div>
            <div class="footer-section">
                <h3 data-i18n="quick_links">Quick Links</h3>
                <a href="index.php#programs" data-i18n="our_programs">Our Programs</a>
                <a href="index.php#reviews" data-i18n="student_reviews">Student Reviews</a>
                <a href="index.php#team" data-i18n="meet_the_team">Meet the Team</a>
                <a href="index.php#partners" data-i18n="our_partners">Our Partners</a>
                <a href="index.php#faq" data-i18n="faq">FAQ</a>
            </div>
            <div class="footer-section">
                <h3 data-i18n="contact_us">Contact Us</h3>
                <p><span data-i18n="email_label">Email:</span> info@edu-familiar.kz</p>
                <p><span data-i18n="phone_label">Phone:</span> +7 (776) 348-4803</p>
                <p><span data-i18n="address_label">Address:</span> Толстой көшесі, 99, 1-қабат Павлодар, Қазақстан</p>
            </div>
            <div class="footer-section">
                <h3 data-i18n="follow_us">Follow Us</h3>
                <div class="social-links">
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="fab fa-instagram"></i></a>
                </div>
                <h3 data-i18n="language">Language</h3>
                <div class="language-switcher">
                    <select id="language-select-footer" class="lang-select">
                        <option value="en">EN</option>
                        <option value="kz">KZ</option>
                        <option value="ru">RU</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p data-i18n="copyright">2025 EDU-Familiar.kz. All rights reserved.</p>
        </div>
    </footer>

    <script src="JS/profile-script.js"></script>
    <script src="JS/language.js"></script>
    <script src="JS/coins.js"></script>
</body>
</html>
