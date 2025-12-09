<?php
session_start();
require_once 'db_connect.php';

// 1. Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. Получаем полные данные пользователя
$stmt = $conn->prepare("SELECT username, full_name, email, user_type, avatar, class, points, avatar_border_color, border_style, ef_premium, is_admin, highest_score FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

// 3. Проверка на Учителя или Админа
if (!$user || (strtolower($user['user_type']) !== 'teacher' && $user['is_admin'] != 1)) {
    echo "<script>alert('Доступ запрещен. Только для учителей.'); window.location.href='profile.php';</script>";
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

// 5. Загрузка рамок
$frames_for_css = [];
$all_frames_result = $conn->query("SELECT style_key, image_file FROM ef_premium_frames WHERE is_visible = 1");
if ($all_frames_result) {
    while($frame = $all_frames_result->fetch_assoc()) {
        $frames_for_css[] = $frame;
    }
}

// 6. ПОДСЧЕТ СТАТИСТИКИ ПО КНИГАМ
$count_books = 0;
$avg_book_rating = 0;

// Считаем количество книг
$count_books_res = $conn->query("SELECT COUNT(*) as cnt FROM books WHERE user_id = $user_id");
if ($count_books_res) {
    $count_books = $count_books_res->fetch_assoc()['cnt'];
}

// Считаем средний рейтинг книг
$book_rating_res = $conn->query("SELECT AVG(rating) as avg_val FROM books WHERE user_id = $user_id AND rating > 0");
if ($book_rating_res) {
    $row = $book_rating_res->fetch_assoc();
    $avg_book_rating = $row['avg_val'] ? round($row['avg_val'], 1) : 0;
}

// 7. ПОДСЧЕТ СТАТИСТИКИ ПО УРОКАМ
$count_lessons = 0;
$avg_lesson_rating = 0;

// Считаем количество уроков
$count_lessons_res = $conn->query("SELECT COUNT(*) as cnt FROM lessons WHERE user_id = $user_id");
if ($count_lessons_res) {
    $count_lessons = $count_lessons_res->fetch_assoc()['cnt'];
}

// Считаем средний рейтинг уроков
$lesson_rating_res = $conn->query("SELECT AVG(rating) as avg_val FROM lessons WHERE user_id = $user_id AND rating > 0");
if ($lesson_rating_res) {
    $row = $lesson_rating_res->fetch_assoc();
    $avg_lesson_rating = $row['avg_val'] ? round($row['avg_val'], 1) : 0;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="teacher_dashboard_title">Кабинет Учителя - EDU-Familiar</title>
    <link rel="stylesheet" href="CSS/header.css"> 
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/settings.css">
    
    <style>
        .dashboard-banner {
            background: linear-gradient(135deg, #7b61ff 0%, #aa96ff 100%);
            border-radius: 16px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(123, 97, 255, 0.2);
            position: relative;
            overflow: hidden;
        }
        .dashboard-banner h2 { font-size: 24px; margin-bottom: 10px; position: relative; z-index: 2; }
        .dashboard-banner p { opacity: 0.9; font-size: 15px; position: relative; z-index: 2; }
        .dashboard-banner::after {
            content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px;
            background: rgba(255,255,255,0.1); border-radius: 50%;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .dash-card {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .dash-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.08);
            border-color: var(--secondary-color);
        }

        .dash-card.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: #f8fafc;
        }
        .dash-card.disabled:hover { transform: none; box-shadow: none; border-color: #e2e8f0; }

        .card-icon-wrapper {
            width: 50px; height: 50px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 15px;
            font-size: 22px;
        }

        .dash-card h3 { font-size: 18px; font-weight: 600; color: var(--text-color); margin-bottom: 5px; }
        .dash-card p { font-size: 13px; color: #64748b; line-height: 1.5; }

        /* Цвета для карточек */
        .c-orange .card-icon-wrapper { background: #fff7ed; color: #ff8c42; }
        .c-purple .card-icon-wrapper { background: #f5f3ff; color: #7b61ff; }
        .c-blue .card-icon-wrapper { background: #eff6ff; color: #3b82f6; }
        .c-green .card-icon-wrapper { background: #f0fdf4; color: #22c55e; }
        
        /* НОВЫЕ ЦВЕТА ДЛЯ АНАЛИТИКИ */
        .c-red .card-icon-wrapper { background: #fef2f2; color: #ef4444; }
        .c-yellow .card-icon-wrapper { background: #fefce8; color: #eab308; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: white; padding: 15px 20px; border-radius: 12px;
            border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }
        .stat-number { font-size: 20px; font-weight: 700; color: var(--text-color); }
        .stat-label { font-size: 13px; color: #64748b; }
    </style>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <?php
    if (!empty($frames_for_css)) {
        echo "<style id=\"dynamic-frame-styles\">\n";
        $after_selectors = [];
        foreach ($frames_for_css as $frame) {
            $after_selectors[] = '.border-' . htmlspecialchars($frame['style_key']) . '::after';
        }
        echo implode(",\n", $after_selectors) . " {
            content: ''; position: absolute; top: -2px; left: -2px; right: -2px; bottom: -2px;
            width: auto; height: auto; background-size: cover; background-position: center;
            background-repeat: no-repeat; pointer-events: none;
        }\n";
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
                    // --- ЛОГИКА АВАТАРА ---
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
            
            <div class="dashboard-banner">
                <h2 data-i18n="teacher_dashboard_title">Сфера Разработки</h2>
                <p data-i18n="teacher_dashboard_desc">Добро пожаловать в панель управления контентом. Здесь вы можете создавать материалы для студентов.</p>
            </div>

            <div class="stats-row">
                <div class="stat-box">
                    <i class="fas fa-book" style="color: #ff8c42; font-size: 24px;"></i>
                    <div>
                        <div class="stat-number"><?php echo $count_books; ?></div>
                        <div class="stat-label">Книг</div>
                    </div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-star" style="color: #ffd700; font-size: 24px;"></i>
                    <div>
                        <div class="stat-number"><?php echo $avg_book_rating; ?></div> 
                        <div class="stat-label">Рейтинг (Книги)</div>
                    </div>
                </div>

                <div class="stat-box">
                    <i class="fas fa-chalkboard-teacher" style="color: #22c55e; font-size: 24px;"></i>
                    <div>
                        <div class="stat-number"><?php echo $count_lessons; ?></div>
                        <div class="stat-label">Уроков</div>
                    </div>
                </div>
                <div class="stat-box">
                    <i class="fas fa-star" style="color: #7b61ff; font-size: 24px;"></i>
                    <div>
                        <div class="stat-number"><?php echo $avg_lesson_rating; ?></div> 
                        <div class="stat-label">Рейтинг (Уроки)</div>
                    </div>
                </div>
            </div>

            <h3 style="margin-bottom: 20px; color: var(--text-color);" data-i18n="tools_title">Инструменты</h3>
            
            <div class="dashboard-grid">
                <div class="dash-card c-orange" onclick="window.location.href='add_book.php'">
                    <div class="card-icon-wrapper"><i class="fas fa-plus"></i></div>
                    <h3 data-i18n="add_book_title">Добавить Книгу</h3>
                    <p data-i18n="add_book_desc">Загрузить новый PDF учебник или методичку.</p>
                </div>
                
                <div class="dash-card c-purple" onclick="window.location.href='my_books.php'">
                    <div class="card-icon-wrapper"><i class="fas fa-book-open"></i></div>
                    <h3 data-i18n="my_publications_title">Мои Публикации (Книги)</h3>
                    <p data-i18n="my_publications_desc">Просмотр ваших загруженных материалов в библиотеке.</p>
                </div>

                <div class="dash-card c-blue" onclick="window.location.href='create_lesson.php'">
                    <div class="card-icon-wrapper"><i class="fas fa-tasks"></i></div>
                    <h3 data-i18n="create_test_title">Создать Урок/Тест</h3>
                    <p data-i18n="create_test_desc">Конструктор интерактивных заданий.</p>
                </div>

                <div class="dash-card c-green" onclick="window.location.href='my_lessons.php'">
                    <div class="card-icon-wrapper"><i class="fas fa-chalkboard-teacher"></i></div>
                    <h3 data-i18n="my_lessons_title">Мои Уроки</h3>
                    <p data-i18n="my_lessons_desc">Редактировать и управлять созданными уроками.</p>
                </div>
            </div>

            <h3 style="margin-bottom: 20px; color: var(--text-color); margin-top: 40px;">Аналитика и Отчеты</h3>
            
            <div class="dashboard-grid">
                <div class="dash-card c-red" onclick="window.location.href='analytics.php'">
                    <div class="card-icon-wrapper"><i class="fas fa-chart-line"></i></div>
                    <h3>Аналитика</h3>
                    <p>Общая статистика успеваемости и активности.</p>
                </div>

                <div class="dash-card c-yellow" onclick="window.location.href='create_class.php'">
                    <div class="card-icon-wrapper"><i class="fas fa-users"></i></div>
                    <h3>Мои Классы - Создания класса</h3>
                    <p>Управление классами и списком учеников.</p>
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
</body>
</html>