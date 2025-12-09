<?php
session_start();
require_once 'db_connect.php';

// 1. Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. Получаем полные данные пользователя (для сайдбара)
$stmt = $conn->prepare("SELECT username, full_name, email, user_type, avatar, class, points, avatar_border_color, border_style, ef_premium, is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

// 3. Проверка прав (Учитель или Админ)
if (!$user || (strtolower(trim($user['user_type'])) !== 'teacher' && $user['is_admin'] != 1)) {
    echo "<script>alert('Доступ запрещен.'); window.location.href='profile.php';</script>";
    exit;
}

// 4. Подготовка переменных для сайдбара
$username = $user['username'];
$full_name = $user['full_name'];
$email = $user['email'];
$user_type = $user['user_type'];
$avatar = $user['avatar'] ?: 'Def_Avatar.jpg';
$class = $user['class'] ?: '';
$points = $user['points'] ?: 0;
$border_color = $user['avatar_border_color'] ?: '#ff8c42';
$border_style = $user['border_style'] ?: 'solid-default';
$ef_premium = $user['ef_premium'] ?: 0;
$is_admin = $user['is_admin'] ?: 0;

// Патч для класса
if (strpos($class, 'frame-') !== false || strpos($class, '.') === 0) { $class = ''; }

// 5. Загрузка рамок (для CSS аватара)
$frames_for_css = [];
$all_frames_result = $conn->query("SELECT style_key, image_file FROM ef_premium_frames WHERE is_visible = 1");
if ($all_frames_result) {
    while($frame = $all_frames_result->fetch_assoc()) {
        $frames_for_css[] = $frame;
    }
}

// === АНАЛИТИКА: КНИГИ ===
$total_downloads_sql = "SELECT COUNT(*) as total FROM book_actions ba 
                        JOIN books b ON ba.book_id = b.id 
                        WHERE b.user_id = $user_id AND ba.action_type = 'download'";
$total_downloads = $conn->query($total_downloads_sql)->fetch_assoc()['total'];

$books_stats_sql = "SELECT b.id, b.title, b.cover_image, b.created_at, 
                    (SELECT COUNT(*) FROM book_actions ba WHERE ba.book_id = b.id AND ba.action_type = 'download') as downloads_count,
                    (SELECT COUNT(*) FROM book_actions ba WHERE ba.book_id = b.id AND ba.action_type = 'read') as reads_count
                    FROM books b 
                    WHERE b.user_id = $user_id 
                    ORDER BY downloads_count DESC";
$books_res = $conn->query($books_stats_sql);

// === АНАЛИТИКА: УРОКИ (УНИКАЛЬНЫЕ УЧЕНИКИ) ===
$total_students_sql = "SELECT COUNT(DISTINCT lc.user_id) as total 
                       FROM lesson_completions lc 
                       JOIN lessons l ON lc.lesson_id = l.id 
                       WHERE l.user_id = $user_id";
$total_students = $conn->query($total_students_sql)->fetch_assoc()['total'];

$lessons_stats_sql = "SELECT l.id, l.title, l.cover_image, l.created_at,
                      (SELECT COUNT(DISTINCT lc.user_id) FROM lesson_completions lc WHERE lc.lesson_id = l.id) as finish_count,
                      (SELECT AVG(percentage) FROM lesson_completions lc WHERE lc.lesson_id = l.id) as avg_score
                      FROM lessons l
                      WHERE l.user_id = $user_id
                      ORDER BY finish_count DESC";
$lessons_res = $conn->query($lessons_stats_sql);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аналитика - EDU-Familiar</title>
    
    <link rel="stylesheet" href="CSS/header.css">
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/settings.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .analytics-tabs { display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .an-tab {
            padding: 10px 25px; border-radius: 12px; cursor: pointer; font-weight: 600; color: #64748b; transition: all 0.3s;
            border: 2px solid transparent; background: transparent;
        }
        .an-tab:hover { background: #f1f5f9; }
        .an-tab.active { background: #eff6ff; color: var(--primary-color); border-color: var(--primary-color); }
        
        .tab-content-area { display: none; animation: fadeIn 0.4s; }
        .tab-content-area.active { display: block; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Карточка статистики (Wide) */
        .stat-card-wide {
            background: white; border-radius: 16px; padding: 20px; display: flex; align-items: center; gap: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; margin-bottom: 20px;
        }
        .sc-icon {
            width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 24px; color: white;
        }
        .sc-orange { background: linear-gradient(135deg, #ff9f5e, #ff8c42); }
        .sc-purple { background: linear-gradient(135deg, #a78bfa, #7b61ff); }
        
        .sc-info h3 { font-size: 28px; font-weight: 700; color: #1e293b; margin: 0; }
        .sc-info p { margin: 0; font-size: 13px; color: #64748b; }

        /* Таблица/Список элементов */
        .analytics-list { display: grid; gap: 15px; }
        .analytics-item {
            background: white; border-radius: 12px; padding: 15px; display: flex; align-items: center; gap: 20px;
            border: 1px solid #e2e8f0; transition: transform 0.2s; cursor: pointer; text-decoration: none; color: inherit;
        }
        .analytics-item:hover { transform: translateX(5px); border-color: var(--secondary-color); }
        
        .item-img { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; background: #eee; }
        .item-info { flex: 1; }
        .item-info h4 { margin: 0 0 5px 0; font-size: 16px; color: #1e293b; }
        .item-info span { font-size: 12px; color: #94a3b8; }
        
        .item-stat { text-align: right; min-width: 100px; }
        .stat-val { font-size: 18px; font-weight: 700; color: var(--primary-color); display: block; }
        .stat-sub { font-size: 11px; color: #64748b; }
        .stat-score { color: #22c55e; } 
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
                    // --- ЛОГИКА АВАТАРА (ТОЧНАЯ КОПИЯ ИЗ PROFILE.PHP) ---
                    $main_avatar_style = '';
                    $main_avatar_class = '';
                    $is_frame = false; 

                    if ($border_style == 'rgb') {
                        $main_avatar_class = 'border-rgb';
                        $main_avatar_style = 'border-width: 4px; border-style: solid;';
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
            <h2 style="margin-bottom: 20px;">Аналитика</h2>

            <div class="analytics-tabs">
                <button class="an-tab active" onclick="switchTab('books')"><i class="fas fa-book"></i> Книги</button>
                <button class="an-tab" onclick="switchTab('lessons')"><i class="fas fa-chalkboard-teacher"></i> Уроки</button>
            </div>

            <div id="books-tab" class="tab-content-area active">
                <div class="stat-card-wide">
                    <div class="sc-icon sc-orange"><i class="fas fa-download"></i></div>
                    <div class="sc-info">
                        <h3><?php echo $total_downloads; ?></h3>
                        <p>Всего скачиваний ваших книг</p>
                    </div>
                </div>

                <div class="analytics-list">
                    <?php if($books_res->num_rows > 0): ?>
                        <?php while($b = $books_res->fetch_assoc()): ?>
                            <div class="analytics-item">
                                <img src="uploads/covers/<?php echo $b['cover_image']; ?>" class="item-img">
                                <div class="item-info">
                                    <h4><?php echo htmlspecialchars($b['title']); ?></h4>
                                    <span>Загружено: <?php echo date('d.m.Y', strtotime($b['created_at'])); ?></span>
                                </div>
                                <div class="item-stat">
                                    <span class="stat-val"><?php echo $b['downloads_count']; ?></span>
                                    <span class="stat-sub">Скачиваний</span>
                                </div>
                                <div class="item-stat" style="border-left:1px solid #eee; padding-left:15px;">
                                    <span class="stat-val" style="color:#7b61ff;"><?php echo $b['reads_count']; ?></span>
                                    <span class="stat-sub">Прочтений</span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align:center; padding:20px; color:#999;">У вас еще нет загруженных книг.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div id="lessons-tab" class="tab-content-area">
                <div class="stat-card-wide">
                    <div class="sc-icon sc-purple"><i class="fas fa-users"></i></div>
                    <div class="sc-info">
                        <h3><?php echo $total_students; ?></h3>
                        <p>Уникальных учеников</p>
                    </div>
                </div>

                <div class="analytics-list">
                    <?php if($lessons_res->num_rows > 0): ?>
                        <?php while($l = $lessons_res->fetch_assoc()): ?>
                            <a href="lesson_report.php?id=<?php echo $l['id']; ?>" class="analytics-item">
                                <?php 
                                    // Логика отображения обложки (картинка или сгенерированный фон)
                                    if ($l['cover_image'] && $l['cover_image'] !== 'default_lesson.jpg') {
                                        $src = (strpos($l['cover_image'], 'data:') === 0) ? $l['cover_image'] : 'uploads/lessons/covers/' . htmlspecialchars($l['cover_image']);
                                        echo '<img src="' . $src . '" class="item-img">';
                                    } else {
                                        echo '<div class="item-img" style="display:flex; align-items:center; justify-content:center; color:#ccc; border:1px solid #eee;"><i class="fas fa-chalkboard-teacher"></i></div>';
                                    }
                                ?>
                                
                                <div class="item-info">
                                    <h4><?php echo htmlspecialchars($l['title']); ?></h4>
                                    <span>Создан: <?php echo date('d.m.Y', strtotime($l['created_at'])); ?></span>
                                </div>
                                
                                <div class="item-stat">
                                    <span class="stat-val"><?php echo $l['finish_count']; ?></span>
                                    <span class="stat-sub">Учеников</span>
                                </div>
                                
                                <div class="item-stat" style="border-left:1px solid #eee; padding-left:15px;">
                                    <span class="stat-val stat-score"><?php echo round($l['avg_score'] ?? 0, 1); ?>%</span>
                                    <span class="stat-sub">Ср. балл</span>
                                </div>
                                
                                <div class="item-stat" style="color:#ccc;">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align:center; padding:20px; color:#999;">У вас еще нет созданных уроков.</p>
                    <?php endif; ?>
                </div>
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
    
    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.an-tab').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
            
            document.querySelectorAll('.tab-content-area').forEach(area => area.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
        }
    </script>
</body>
</html>