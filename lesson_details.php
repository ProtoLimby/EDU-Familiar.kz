<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php'; // Подключаем для getSubjectTheme

// --- 1. ДАННЫЕ ПОЛЬЗОВАТЕЛЯ ---
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$username = "Guest"; $full_name = "Гость"; $email = ""; $user_type = "Guest";
$avatar = "Def_Avatar.jpg"; $class = ""; $points = 0; $border_color = "#ff8c42";
$border_style = "solid-default"; $ef_premium = 0; $is_admin = 0;

$frames_for_css = [];
$all_frames_result = $conn->query("SELECT style_key, image_file FROM ef_premium_frames WHERE is_visible = 1");
if ($all_frames_result) { while($frame = $all_frames_result->fetch_assoc()) { $frames_for_css[] = $frame; } }

if ($user_id > 0) {
    $stmt_user = $conn->prepare("SELECT username, full_name, email, user_type, avatar, class, points, avatar_border_color, border_style, ef_premium, is_admin FROM users WHERE id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result = $stmt_user->get_result();
    if ($row = $result->fetch_assoc()) {
        $username = $row['username'] ?: 'Guest_' . $user_id;
        $full_name = $row['full_name']; $email = $row['email']; $user_type = $row['user_type'];
        $avatar = $row['avatar'] ?: 'Def_Avatar.jpg'; $class = $row['class'] ?: '';
        $points = $row['points'] ?: 0; $border_color = $row['avatar_border_color'] ?: '#ff8c42';
        $border_style = $row['border_style'] ?: 'solid-default'; $ef_premium = $row['ef_premium'] ?: 0;
        $is_admin = $row['is_admin'] ?: 0;
    }
    $stmt_user->close();
}
if (strpos($class, 'frame-') !== false || strpos($class, '.') === 0) { $class = ''; }

// --- 2. ДАННЫЕ УРОКА ---
if (!isset($_GET['id'])) { header("Location: training.php"); exit; }
$lesson_id = intval($_GET['id']);

// --- ЛОГИКА РЕЙТИНГА ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_rating'])) {
    if ($user_id == 0) {
        echo "<script>alert('Пожалуйста, авторизуйтесь, чтобы оценить урок.');</script>";
    } else {
        $new_rating = intval($_POST['rating_value']);
        if ($new_rating >= 1 && $new_rating <= 5) {
            $stmt_vote = $conn->prepare("INSERT INTO lesson_ratings (lesson_id, user_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = ?");
            $stmt_vote->bind_param("iiii", $lesson_id, $user_id, $new_rating, $new_rating);
            $stmt_vote->execute();
            $stmt_vote->close();

            $avg_query = "SELECT AVG(rating) as avg_rate FROM lesson_ratings WHERE lesson_id = $lesson_id";
            $avg_res = $conn->query($avg_query);
            $avg_row = $avg_res->fetch_assoc();
            $final_rating = round($avg_row['avg_rate'], 1);

            $stmt_update = $conn->prepare("UPDATE lessons SET rating = ? WHERE id = ?");
            $stmt_update->bind_param("di", $final_rating, $lesson_id);
            $stmt_update->execute();
            $stmt_update->close();

            header("Location: lesson_details.php?id=$lesson_id");
            exit;
        }
    }
}

$stmt_lesson = $conn->prepare("SELECT lessons.*, users.username as teacher_name, users.avatar as teacher_avatar FROM lessons JOIN users ON lessons.user_id = users.id WHERE lessons.id = ?");
$stmt_lesson->bind_param("i", $lesson_id);
$stmt_lesson->execute();
$lesson_result = $stmt_lesson->get_result();

if ($lesson_result->num_rows == 0) { echo "Урок не найден."; exit; }
$lesson = $lesson_result->fetch_assoc();
$stmt_lesson->close();

$count_sql = "SELECT COUNT(*) as total_votes FROM lesson_ratings WHERE lesson_id = $lesson_id";
$count_res = $conn->query($count_sql);
$total_votes = $count_res->fetch_assoc()['total_votes'];

// --- ПРОВЕРКА ПОПЫТОК ---
$attempts_done = 0;
$max_attempts = intval($lesson['max_attempts']); // 0 = Безлимит

if ($user_id > 0) {
    $att_q = $conn->prepare("SELECT COUNT(*) as cnt FROM lesson_completions WHERE user_id = ? AND lesson_id = ?");
    $att_q->bind_param("ii", $user_id, $lesson_id);
    $att_q->execute();
    $attempts_done = $att_q->get_result()->fetch_assoc()['cnt'];
}

$attempts_left = max(0, $max_attempts - $attempts_done);

// Блокируем, ТОЛЬКО ЕСЛИ лимит установлен (> 0) И попытки исчерпаны
$is_locked = ($max_attempts > 0 && $attempts_done >= $max_attempts); 

function getLessonTimeLimit($jsonContent) {
    if (!$jsonContent) return 0;
    $blocks = json_decode($jsonContent, true);
    if (is_array($blocks)) {
        foreach ($blocks as $section) {
            if (!empty($section['columns'])) {
                foreach ($section['columns'] as $col) {
                    foreach ($col as $block) {
                        if ($block['type'] === 'timer' && !empty($block['content']['minutes'])) {
                            return intval($block['content']['minutes']);
                        }
                    }
                }
            }
        }
    }
    return 0; 
}
$timeLimit = getLessonTimeLimit($lesson['content_json']);

// ИСПРАВЛЕНИЕ: Убрано преждевременное закрытие соединения
// $conn->close(); // УДАЛЕНО, чтобы избежать ошибки mysqli object is already closed
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson['title']); ?> - EDU-Familiar.kz</title>
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/settings.css">
    <link rel="stylesheet" href="CSS/header.css">
    <link rel="stylesheet" href="CSS/online-book-styles.css">
    <link rel="stylesheet" href="CSS/lesson-details.css"> 
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php
    if (!empty($frames_for_css)) {
        echo "<style id=\"dynamic-frame-styles\">\n";
        $after_selectors = []; foreach ($frames_for_css as $frame) $after_selectors[] = '.border-' . htmlspecialchars($frame['style_key']) . '::after';
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
                    <div class="dropdown-content"><a href="index.php#about" data-i18n="about">About</a><a href="index.php#programs" data-i18n="programs">Programs</a></div>
                </div>
                <a href="training.php" data-i18n="training">Training</a> 
                <a href="best-students.php" data-i18n="best_students">Best Students</a> 
                <a href="online-book.php" data-i18n="online_book">Online Book</a>
                <a href="shop.html" data-i18n="catalog">Каталог</a>
            </nav>
            <div class="header-actions">
                <div class="language-switcher"><select id="language-select-header" class="lang-select"><option value="en">EN</option><option value="kz">KZ</option><option value="ru">RU</option></select></div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="dropdown">
                        <a href="profile.php" class="login-btn dropbtn" data-i18n="profile">Profile</a>
                        <div class="dropdown-content"><a href="logout.php" data-i18n="logout">Logout</a></div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="login-btn" data-i18n="login">Login</a>
                <?php endif; ?>
                <button class="burger-menu" aria-label="Toggle Menu"><span></span><span></span><span></span></button>
            </div>
        </div>
        <nav class="mobile-nav"><a href="index.php" data-i18n="home">Home</a></nav>
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
                <p class="class-display" id="main-class"><span data-i18n="class_label">Класс:</span> <?php echo htmlspecialchars($class); ?></p>
                <p><span data-i18n="user_type_label">Тип:</span> <?php echo htmlspecialchars(ucfirst($user_type)); ?></p>
                <p><span data-i18n="email_label">Email:</span> <?php echo htmlspecialchars($email); ?></p>
                <div class="ef-points"><i class="fas fa-coins"></i><span class="points-value"><?php echo htmlspecialchars($points); ?></span><span class="points-label">EF</span></div>
                <div class="level-progress"><span data-i18n="level_1">Уровень 1</span><div class="progress-bar"><div class="fill" style="width: 83%"></div></div><span>0 / 1,500 EF</span></div>
            </div>
            <button class="settings-btn" onclick="window.location.href='profile.php'">Settings</button>
            <?php if (isset($user_type) && strtolower(trim($user_type)) === 'teacher') : ?>
                <button class="settings-btn" onclick="window.location.href='teacher_dashboard.php'" style="margin-top: 10px; background: var(--secondary-color);"><i class="fas fa-tools"></i> Сфера разработки</button>
            <?php endif; ?>
            <?php if ($is_admin == 1): ?>
                <div class="admin-buttons-container" style="margin-top: 10px;"><a href="admin_frames.php" class="admin-panel-btn">Control Server</a></div>
            <?php endif; ?>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
        </aside>

        <main class="main-content" style="background: transparent; box-shadow: none; padding: 0;">
            <div class="lesson-details-grid">
                <div class="lesson-content-main">
                    <?php 
                        $cover = $lesson['cover_image'];
                        if (!empty($cover) && $cover !== 'default_lesson.jpg') {
                            $src = (strpos($cover, 'data:image') === 0) ? $cover : "uploads/lessons/covers/" . htmlspecialchars($cover);
                            echo '<img src="' . $src . '" alt="Lesson Cover" class="lesson-cover-image">';
                        } else {
                            // === ЛОГИКА ГЕНЕРАЦИИ (с использованием functions.php) ===
                            $themeData = getSubjectTheme($lesson['subject']);
                            $icon = $themeData['icon'];
                            $theme = $themeData['style'];
                            echo '<div class="generated-cover-lg ' . $theme . '"><i class="fas ' . $icon . '"></i></div>';
                        }
                    ?>
                    
                    <h1><?php echo htmlspecialchars($lesson['title']); ?></h1>
                    <div class="lesson-meta-tags">
                        <span class="meta-tag rating-tag" title="На основе <?php echo $total_votes; ?> голосов">
                            <i class="fas fa-star"></i> <?php echo number_format($lesson['rating'], 1); ?> (<?php echo $total_votes; ?>)
                        </span>
                        <span class="meta-tag class-tag"><i class="fas fa-graduation-cap"></i> <?php echo $lesson['grade']; ?> класс</span>
                        <span class="meta-tag subject-tag"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($lesson['subject']); ?></span>
                        
                        <?php 
                            $lang_string = $lesson['language'] ?? 'ru';
                            $lang_array = explode(',', $lang_string);
                            
                            foreach($lang_array as $code) {
                                $code = trim(strtolower($code));
                                if(!$code) continue;
                                $label = strtoupper($code);
                                $style = 'background: #f1f5f9; color: #64748b;'; 

                                if ($code === 'kz') {
                                    $style = 'background: #e0f2f1; color: #00695c; border: 1px solid #b2dfdb;';
                                } elseif ($code === 'en') {
                                    $style = 'background: #f3e5f5; color: #7b1fa2; border: 1px solid #e1bee7;';
                                } elseif ($code === 'ru') {
                                    $style = 'background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb;';
                                }
                                
                                echo "<span class='meta-tag' style='$style'>$label</span>";
                            }
                        ?>
                    </div>
                    <div class="lesson-description">
                        <h3>Описание урока:</h3>
                        <p><?php echo nl2br(htmlspecialchars($lesson['full_description'] ?: 'Описание отсутствует.')); ?></p>
                    </div>
                </div>
                
                <div class="lesson-sidebar-details">
                    <div class="start-lesson-card">
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <small>Попыток осталось</small>
                                <strong style="color: <?php echo $is_locked ? '#ef4444' : 'var(--text-color)'; ?>;">
                                    <?php if ($max_attempts == 0): ?>
                                        <i class="fas fa-infinity"></i> Безлимит
                                    <?php else: ?>
                                        <i class="fas fa-redo-alt"></i> <?php echo $attempts_left; ?> / <?php echo $max_attempts; ?>
                                    <?php endif; ?>
                                </strong>
                            </div>
                            <div class="info-item">
                                <small>Время</small>
                                <strong>
                                    <?php if($timeLimit > 0): ?>
                                        <i class="fas fa-clock"></i> <?php echo $timeLimit; ?> мин
                                    <?php else: ?>
                                        <i class="fas fa-infinity"></i> Безлимит
                                    <?php endif; ?>
                                </strong>
                            </div>
                        </div>

                        <div style="text-align: center; margin-bottom: 20px;">
                            <span style="font-size: 14px; color: #64748b;">Награда за прохождение:</span>
                            <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">
                                <i class="fas fa-coins"></i> <?php echo $lesson['coins_reward']; ?> EF
                            </div>
                        </div>
                        <?php if(!empty($lesson['achievement_name'])): ?>
                        <div style="text-align: center; margin-bottom: 25px;">
                            <span style="font-size: 14px; color: #64748b;">Достижение:</span>
                            <div style="font-size: 16px; font-weight: 600; color: var(--secondary-color);">
                                <i class="fas <?php echo htmlspecialchars($lesson['achievement_icon'] ?: 'fa-star'); ?>"></i> <?php echo htmlspecialchars($lesson['achievement_name']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($is_locked): ?>
                            <button type="button" class="start-lesson-btn locked" onclick="openAlertModal()">
                                <i class="fas fa-lock"></i> Доступ закрыт
                            </button>
                        <?php else: ?>
                            <a href="view_page.php?id=<?php echo $lesson['id']; ?>&start=1" class="start-lesson-btn">
                                <i class="fas fa-play"></i> Начать Урок
                            </a>
                        <?php endif; ?>
                        
                        <div class="rating-box">
                            <h4>Оцените урок</h4>
                            <form method="POST" class="star-rating-group">
                                <input type="hidden" name="set_rating" value="1">
                                <input type="radio" name="rating_value" value="5" id="rate-5" onchange="this.form.submit()"><label for="rate-5" title="Отлично"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating_value" value="4" id="rate-4" onchange="this.form.submit()"><label for="rate-4" title="Хорошо"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating_value" value="3" id="rate-3" onchange="this.form.submit()"><label for="rate-3" title="Нормально"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating_value" value="2" id="rate-2" onchange="this.form.submit()"><label for="rate-2" title="Плохо"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating_value" value="1" id="rate-1" onchange="this.form.submit()"><label for="rate-1" title="Ужасно"><i class="fas fa-star"></i></label>
                            </form>
                        </div>
                    </div>
                    
                    <div class="start-lesson-card" style="padding: 20px;">
                        <h4 style="margin: 0 0 15px 0; font-size: 16px; color: var(--text-color);">Автор урока</h4>
                        <div class="teacher-card">
                            <img src="img/avatar/<?php echo htmlspecialchars($lesson['teacher_avatar'] ?: 'Def_Avatar.jpg'); ?>" alt="Teacher Avatar" class="teacher-avatar">
                            <div class="teacher-info">
                                <h4><?php echo htmlspecialchars($lesson['teacher_name'] ?? 'Неизвестно'); ?></h4>
                                <p>Учитель</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <div class="alert-modal-backdrop" id="alertBackdrop"></div>
    <div class="alert-modal" id="alertModal">
        <div class="alert-icon-box">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Попытки исчерпаны</h3>
        <p>Вы использовали все доступные попытки для прохождения этого урока (<?php echo $max_attempts; ?> из <?php echo $max_attempts; ?>).<br>Повторное прохождение невозможно.</p>
        <button class="alert-close-btn" onclick="closeAlertModal()">Понятно</button>
    </div>

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
    <script>
        // Скрипт для модального окна
        const alertModal = document.getElementById('alertModal');
        const alertBackdrop = document.getElementById('alertBackdrop');

        function openAlertModal() {
            if(!alertModal) return;
            alertModal.classList.add('active');
            alertBackdrop.classList.add('active');
        }

        function closeAlertModal() {
            if(!alertModal) return;
            alertModal.classList.remove('active');
            alertBackdrop.classList.remove('active');
        }

        // Закрытие по клику на фон
        if(alertBackdrop) {
            alertBackdrop.addEventListener('click', closeAlertModal);
        }
        
        // Авто-открытие, если редиректнул view_page (опционально)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('locked') === '1') {
            openAlertModal();
            // Очистка URL
            window.history.replaceState({}, document.title, window.location.pathname + "?id=<?php echo $lesson_id; ?>");
        }
    </script>
</body>
</html>

