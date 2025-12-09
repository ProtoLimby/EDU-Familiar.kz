
<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php'; 

// --- 1. ДАННЫЕ ПОЛЬЗОВАТЕЛЯ (ДЛЯ САЙДБАРА) ---
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// Дефолтные значения
$username = "Guest"; 
$full_name = "Гость"; 
$email = ""; 
$user_type = "Guest";
$avatar = "Def_Avatar.jpg"; 
$class = ""; 
$points = 0; 
$xp = 0;
$level = 1;
$border_color = "#ff8c42";
$border_style = "solid-default"; 
$ef_premium = 0; 
$is_admin = 0;

// Загрузка рамок
$frames_for_css = [];
$all_frames_result = $conn->query("SELECT style_key, image_file FROM ef_premium_frames WHERE is_visible = 1");
if ($all_frames_result) { 
    while($frame = $all_frames_result->fetch_assoc()) { 
        $frames_for_css[] = $frame; 
    } 
}

// Загрузка данных пользователя, если авторизован
if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT username, full_name, email, user_type, avatar, class, points, xp, level, avatar_border_color, border_style, ef_premium, is_admin FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $username = $row['username'] ?: 'Guest_' . $user_id;
        $full_name = $row['full_name']; 
        $email = $row['email']; 
        $user_type = $row['user_type'];
        $avatar = $row['avatar'] ?: 'Def_Avatar.jpg'; 
        $class = $row['class'] ?: '';
        $points = $row['points'] ?: 0; 
        $xp = $row['xp'] ?: 0;
        $level = $row['level'] ?: 1;
        $border_color = $row['avatar_border_color'] ?: '#ff8c42';
        $border_style = $row['border_style'] ?: 'solid-default'; 
        $ef_premium = $row['ef_premium'] ?: 0;
        $is_admin = $row['is_admin'] ?: 0;
    }
    $stmt->close();
}

// Очистка класса от мусора (если старый формат)
if (strpos($class, 'frame-') !== false || strpos($class, '.') === 0) { $class = ''; }


// --- 2. ЛОГИКА ЗАГРУЗКИ УРОКОВ (ОБНОВЛЕНА) ---

// Фильтр по privacy
$where = "privacy = 'public' AND is_hidden = 0";

// Фильтр по классу (grade)
if (!empty($_GET['class'])) {
    $cl = intval($_GET['class']);
    $where .= " AND grade = $cl"; 
}

// Поиск
if (!empty($_GET['search'])) {
    $s = $conn->real_escape_string($_GET['search']);
    $where .= " AND (title LIKE '%$s%' OR subject LIKE '%$s%')";
}

// Запрос
$lessons_sql = "SELECT lessons.*, users.username as teacher_name 
                FROM lessons 
                JOIN users ON lessons.user_id = users.id 
                WHERE $where 
                ORDER BY created_at DESC";

$lessons_result = $conn->query($lessons_sql);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Каталог Уроков - EDU-Familiar.kz</title>
    
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/settings.css">
    <link rel="stylesheet" href="CSS/header.css">
    <link rel="stylesheet" href="CSS/online-book-styles.css"> 
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <?php
    // Генерация CSS для рамок аватара
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
                    </div>
                </div>
                <a href="training.php" data-i18n="training">Training</a> 
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
                    <span></span><span></span><span></span>
                </button>
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
                    // Логика отображения рамки (та же, что и в profile.php)
                    $main_avatar_style = ''; $main_avatar_class = ''; $is_frame = false; 
                    if ($border_style == 'rgb') {
                        $main_avatar_class = 'border-rgb'; $main_avatar_style = 'border-width: 4px; border-style: solid;';
                    } elseif ($border_style == 'gradient-custom') {
                        $main_avatar_class = 'border-gradient-custom';
                        $gradient_css = 'linear-gradient(45deg, ' . str_replace('|', ', ', htmlspecialchars($border_color)) . ')';
                        $main_avatar_style = 'border: 4px solid transparent; --custom-gradient: ' . $gradient_css . ';';
                    } elseif (strpos($border_style, 'frame-') === 0) {
                        $main_avatar_class = 'border-' . $border_style; 
                        $main_avatar_style = 'border: 2px solid transparent;';
                        $is_frame = true; 
                    } else {
                        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $border_color)) {
                            $main_avatar_style = 'border: 4px solid ' . htmlspecialchars($border_color) . ';';
                        } else {
                            $main_avatar_style = 'border: 4px solid #ff8c42;';
                        }
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
                
                <div class="ef-points"><i class="fas fa-coins"></i><span class="points-value"><?php echo htmlspecialchars($points); ?></span><span class="points-label">EF</span></div>
                
                <?php
                    
                    $lvlData = calculateLevel($xp);
                ?>
                <div class="level-progress">
                    <span data-i18n="level_1">Уровень <?php echo $lvlData['level']; ?></span>
                    <div class="progress-bar"><div class="fill" style="width: <?php echo $lvlData['progress']; ?>%"></div></div>
                    <span><?php echo $lvlData['xp_current_level']; ?> / <?php echo $lvlData['xp_next_level']; ?> XP</span>
                </div>
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

        <main class="main-content">
            <h2 style="margin-bottom: 20px;">Интерактивные Уроки</h2>

            <div class="filters-container">
                <form class="filters-form" method="GET">
                    <div class="filter-group">
                        <label><i class="fas fa-graduation-cap"></i> Класс</label>
                        <select name="class">
                            <option value="">Все классы</option>
                            <?php for($i=1; $i<=11; $i++) echo "<option value='$i' ".($_GET['class']==$i?'selected':'').">$i класс</option>"; ?>
                        </select>
                    </div>
                    <div class="filter-group" style="flex-grow:1;">
                        <label><i class="fas fa-search"></i> Поиск</label>
                        <input type="text" name="search" placeholder="Название урока или предмет..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="apply-filters-btn">Найти</button>
                </form>
            </div>

            <div class="books-grid">
                <?php if ($lessons_result && $lessons_result->num_rows > 0): ?>
                    <?php while($lesson = $lessons_result->fetch_assoc()): ?>
                        <div class="book-card"> 
                            <div class="book-cover-wrapper" style="height: 160px;"> 
                                <?php 
                                    $cover = $lesson['cover_image'];
                                    
                                    // === ЛОГИКА ОБЛОЖКИ (Изображение или Генерация) ===
                                    if (!empty($cover) && $cover !== 'default_lesson.jpg') {
                                        $src = (strpos($cover, 'data:image') === 0) ? $cover : "uploads/lessons/covers/" . htmlspecialchars($cover);
                                        echo '<img src="' . $src . '" alt="Cover" class="book-image" style="object-fit: cover;">';
                                    } else {
                                        $themeData = getSubjectTheme($lesson['subject']);
                                        $icon = $themeData['icon'];
                                        $theme = $themeData['style'];

                                        echo '<div class="generated-cover ' . $theme . '"><i class="fas ' . $icon . '"></i></div>';
                                    }
                                ?>
                                
                                <div class="book-rating-badge">
                                    <span style="margin-right: 8px;"><i class="fas fa-star" style="color:#ffd700;"></i> <?php echo number_format($lesson['rating'], 1); ?></span>
                                    <span><i class="fas fa-coins"></i> <?php echo $lesson['coins_reward'] ?? 0; ?></span>
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
                                            $style = 'background: #f1f5f9; color: #64748b;'; 

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
                                
                                <h3><?php echo htmlspecialchars($lesson['title']); ?></h3>
                                
                                <p class="book-desc-short">
                                    <?php echo htmlspecialchars($lesson['short_description'] ?: 'Интерактивный урок.'); ?>
                                </p>
                                
                                <div style="font-size: 12px; color: #94a3b8; margin-bottom: 15px;">
                                    <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($lesson['teacher_name'] ?? 'Учитель'); ?>
                                </div>

                                <div class="book-card-footer">
                                    <a href="lesson_details.php?id=<?php echo $lesson['id']; ?>" class="book-action-btn primary">Подробнее</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align:center; padding: 40px; color: #64748b;">
                        <i class="fas fa-chalkboard-teacher" style="font-size: 40px; margin-bottom: 15px; display:block;"></i>
                        Уроки не найдены.
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
