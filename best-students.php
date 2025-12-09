<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// === ИЗМЕНЕНИЕ 1: ЗАГРУЖАЕМ ТОЛЬКО ВИДИМЫЕ РАМКИ (из profile.php) ===
$frames_for_css = [];
// ДОБАВЛЕНО: WHERE is_visible = 1
$all_frames_result = $conn->query("SELECT style_key, image_file FROM ef_premium_frames WHERE is_visible = 1");
if ($all_frames_result) {
    while($frame = $all_frames_result->fetch_assoc()) {
        $frames_for_css[] = $frame;
    }
}
// ==========================================================

$user_id = $_SESSION['user_id'];

// === ИЗМЕНЕНИЕ 2: ОБНОВЛЕННЫЙ ЗАПРОС (из profile.php) ===
// Добавлены: avatar_border_color, border_style, ef_premium, is_admin
$stmt = $conn->prepare("SELECT username, full_name, email, user_type, avatar, class, points, avatar_border_color, border_style, ef_premium, is_admin FROM users WHERE id = ?");
if (!$stmt) {
    error_log("best-students.php: Ошибка подготовки запроса: " . $conn->error);
    die("Ошибка сервера");
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log("best-students.php: Пользователь с ID $user_id не найден");
    echo "Пользователь с ID $user_id не найден!";
    exit;
}

$row = $result->fetch_assoc();
$username = $row['username'] ?: 'Guest_' . $user_id;
$full_name = $row['full_name'] ?: 'Не указано';
$email = $row['email'];
$user_type = $row['user_type'];
$avatar = $row['avatar'] ?: 'Def_Avatar.jpg';
$class = $row['class'] ?: '';
$points = $row['points'] ?: 0;
// Новые переменные
$border_color = $row['avatar_border_color'] ?: '#ff8c42';
$border_style = $row['border_style'] ?: 'solid-default';
$ef_premium = $row['ef_premium'] ?: 0;
$is_admin = $row['is_admin'] ?: 0;
$stmt->close();

// Патч для "Класса"
if (strpos($class, 'frame-') !== false || strpos($class, '.') === 0) {
    $class = ''; 
}

// === ИЗМЕНЕНИЕ 3: ЗАГРУЖАЕМ РАМКИ ДЛЯ МОДАЛКИ (из profile.php) ===
$frames_by_category = [];
if ($ef_premium == 1) {
    // ДОБАВЛЕНО: WHERE is_visible = 1
    $frames_result = $conn->query("SELECT name, style_key, image_file, category FROM ef_premium_frames WHERE is_visible = 1 ORDER BY category, name");
    if ($frames_result) {
        while($frame = $frames_result->fetch_assoc()) {
            $frames_by_category[$frame['category']][] = $frame;
        }
    }
}
// ==============================================================

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="best_students_title">Best Students - EDU-Familiar.kz</title>
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/header.css">
    <link rel="stylesheet" href="CSS/settings.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <?php
    // --- ИЗМЕНЕНИЕ 5: ГЕНЕРАЦИЯ СТИЛЕЙ (из profile.php) ---
    if (!empty($frames_for_css)) {
        echo "<style id=\"dynamic-frame-styles\">\n";
        
        $after_selectors = [];
        foreach ($frames_for_css as $frame) {
            $after_selectors[] = '.border-' . htmlspecialchars($frame['style_key']) . '::after';
        }
        
        echo implode(",\n", $after_selectors) . " {\n";
        echo "    content: '';\n";
        echo "    position: absolute;\n";
        echo "    top: -2px; left: -2px; right: -2px; bottom: -2px;\n";
        echo "    width: auto; height: auto;\n";
        echo "    background-size: cover;\n";
        echo "    background-position: center;\n";
        echo "    background-repeat: no-repeat;\n";
        echo "    pointer-events: none;\n";
        echo "}\n\n";

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
                <a href="training.html" data-i18n="training">Training</a> 
                <a href="best-students.php" class="active" data-i18n="best_students">Best Students</a> 
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

    <section id="best-students" class="profile-container">
        <aside class="sidebar">
            <div class="user-info">
                
                <?php
                    $main_avatar_style = '';
                    $main_avatar_class = '';
                    $is_frame = false; 

                    if ($border_style == 'rgb') {
                        $main_avatar_class = 'border-rgb';
                        $main_avatar_style = 'border-width: 4px; border-style: solid;';
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
                    
                    if ($is_frame) {
                        $main_avatar_class .= ' avatar-padded';
                    }
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
            <button class="settings-btn" data-i18n="settings">Settings</button>
            
            <?php if ($is_admin == 1): ?>
                <div class="admin-buttons-container">
                    <a href="admin_frames.php" class="admin-panel-btn" style="text-decoration: none;" data-i18n="control_server">
                         Control Server
                    </a>
                </div>
            <?php endif; ?>
            
            <button class="logout-btn" data-i18n="logout">Logout</button>
        </aside>

        <main class="main-content">
            <h2 data-i18n="leaders">Leaderboard</h2>
            <div class="leaderboard-container">
                <table class="leaderboard-table" id="leaderboard-table">
                    <thead>
                        <tr>
                            <th data-i18n="rank">Rank</th>
                            <th data-i18n="avatar">Avatar</th>
                            <th data-i18n="user_name">Name</th>
                            <th data-i18n="points">Total Points</th>
                        </tr>
                    </thead>
                    <tbody id="leaderboard-list">
                        <tr>
                            <td colspan="4" class="loading" data-i18n="loading">Загрузка...</td>
                        </tr>
                    </tbody>
                </table>
                <button class="pagination-btn prev" style="display:none;" data-i18n="prev_page">Previous Page</button>
                <button class="pagination-btn next" data-i18n="next_page">Next Page</button>
            </div>
        </main>
    </section>

<div class="settings-modal">
    <div class="modal-content">

        <div class="modal-header">
            <h2 data-i18n="profile_settings">Настройки профиля</h2>
            <button class="close-modal" aria-label="Close">×</button>
        </div>

        <div class="profile-grid">

            <div class="avatar-column">
                <div class="avatar-box">
                    <?php
                        // --- (ЛОГИКА РЕНДЕРИНГА АВАТАРА В МОДАЛКЕ) ---
                        $modal_class = '';
                        $modal_style = '';
                        $is_frame_modal = false; 
                        
                        if ($border_style == 'rgb') {
                            $modal_class = 'border-rgb';
                            $modal_style = 'border-width: 4px; border-style: solid;';
                        } elseif (strpos($border_style, 'frame-') === 0) {
                            $modal_class = 'border-' . $border_style; 
                            $modal_style = 'border: 2px solid transparent;';
                            $is_frame_modal = true; 
                            
                        } else {
                            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $border_color)) {
                                $modal_style = 'border: 4px solid ' . htmlspecialchars($border_color) . ';';
                            } else {
                                $modal_style = 'border: 4px solid #ff8c42;';
                            }
                        }
                        
                        if ($is_frame_modal) {
                            $modal_class .= ' avatar-padded';
                        }
                    ?>
                    <div class="main-avatar <?php echo $modal_class; ?>" 
                         id="main-avatar-preview"
                         style="background-image: url('img/avatar/<?php echo htmlspecialchars($avatar); ?>'); <?php echo $modal_style; ?>">
                    </div>
                    <div class="avatar-glow"></div>
                </div>

                <button class="upload-btn" data-i18n="upload_avatar">
                    <i class="fas fa-camera"></i> Загрузить аватар
                </button>
                <input type="file" id="avatar-upload" accept="image/*" style="display: none;">
            </div>

            <div class="info-edit-column">
                <div class="info-card">
                    <h3 data-i18n="user_info">Ваши данные</h3>
                    <div class="info-row">
                        <span data-i18n="username">Логин:</span>
                        <strong id="display-username"><?php echo htmlspecialchars($username); ?></strong>
                    </div>
                    <div class="info-row">
                        <span data-i18n="full_name">ФИО:</span>
                        <strong id="display-fullname"><?php echo htmlspecialchars($full_name); ?></strong>
                    </div>
                    <div class="info-row">
                        <span data-i18n="class_label">Класс:</span>
                        <strong id="display-class"><?php echo $class ?: '<span data-i18n="not_specified">Не указан</span>'; ?></strong>
                    </div>
                </div>

                <div class="edit-card">
                    <h3 data-i18n="edit_profile">Редактировать</h3>
                    <label data-i18n="new_username">Новый логин</label>
                    <input type="text" id="new-username" value="<?php echo htmlspecialchars($username); ?>">

                    <label data-i18n="new_fullname">Новое ФИО</label>
                    <input type="text" id="new-full-name" value="<?php echo htmlspecialchars($full_name); ?>">

                    <label data-i18n="class_select">Класс</label>
                    <select id="new-class">
                        <option value="" data-i18n="select_class_empty">—</option>
                        <?php for ($i = 1; $i <= 11; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $class == $i ? 'selected' : ''; ?>><?php echo $i; ?> класс</option>
                        <?php endfor; ?>
                    </select>

                    <button class="save-btn" data-i18n="save_changes">Сохранить изменения</button>
                </div>
            </div>

            <div class="customization-column">
                <div class="customization-card">
                    <h3 data-i18n="avatar_border_settings">Оформление аватара</h3>

                    <div class="tabs">
                        <button class="tab-btn active" data-tab="standard" data-i18n="standard">Standard</button>
                        <button class="tab-btn premium" data-tab="efpremium" data-i18n="efpremium">EFPremium</button>
                    </div>

                    <div class="tab-content active" id="standard-tab">
                        <div class="control-row">
                            <label data-i18n="border_color">Цвет рамки</label>
                            
                            <input type="color" id="border-color" 
                                   value="<?php echo (strpos($border_color, '#') === 0 ? htmlspecialchars($border_color) : '#ff8c42'); ?>">
                        </div>
                        <div class="preview-row">
                            <span data-i18n="border_preview">Превью:</span>
                            
                            <div class="border-preview <?php echo $modal_class; ?>" 
                                 id="avatar-preview"
                                 style="background-image: url('img/avatar/<?php echo htmlspecialchars($avatar); ?>'); <?php echo $modal_style; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="tab-content" id="efpremium-tab">
                        <?php if ($ef_premium == 1): ?>
                            
                            <div class="premium-sections-container">
                                
                                <div class="premium-section">
                                    <h3 data-i18n="frame_rgb">RGB</h3>
                                    <div class="grid-scroll-wrapper">
                                        <button class="scroll-btn left" aria-label="Scroll left">&lt;</button>
                                        <div class="efpremium-frames-grid">
                                            <div class="border-cell <?php echo ($border_style == 'rgb' ? 'selected' : ''); ?>" 
                                                 data-border-style="rgb" data-border-color="rgb" data-border-width="4px">
                                                <div class="border-preview-cell border-rgb"></div>
                                                <p class="border-label" data-i18n="frame_rgb">RGB</p>
                                            </div>
                                        </div>
                                        <button class="scroll-btn right" aria-label="Scroll right">&gt;</button>
                                    </div>
                                </div>

                                <?php foreach ($frames_by_category as $category_name => $frames): ?>
                                    <div class="premium-section">
                                        <h3><?php echo htmlspecialchars($category_name); ?></h3>
                                        <div class="grid-scroll-wrapper">
                                            <button class="scroll-btn left" aria-label="Scroll left">&lt;</button>
                                            <div class="efpremium-frames-grid">
                                                
                                                <?php foreach ($frames as $frame):
                                                    $style_key = htmlspecialchars($frame['style_key']);
                                                    $image_file = htmlspecialchars($frame['image_file']);
                                                    $name = htmlspecialchars($frame['name']);
                                                    $is_selected = ($border_style == $frame['style_key']) ? 'selected' : '';
                                                ?>
                                                    <div class="border-cell <?php echo $is_selected; ?>" 
                                                         data-border-style="<?php echo $style_key; ?>" 
                                                         data-border-color="frame" 
                                                         data-border-width="2px">
                                                        
                                                        <div class="border-preview-cell" 
                                                             style="background-image: url('frames/<?php echo $image_file; ?>'); border: none; background-size: cover;">
                                                        </div>
                                                        <p class="border-label"><?php echo $name; ?></p>
                                                    </div>
                                                <?php endforeach; ?>
                                                
                                            </div> 
                                            <button class="scroll-btn right" aria-label="Scroll right">&gt;</button>
                                        </div> 
                                    </div> 
                                <?php endforeach; ?>
                                
                            </div> 
                        <?php else: ?>
                            <p data-i18n="efpremium_required_frames" class="premium-notice">Получите EFPremium для доступа к эксклюзивным рамкам!</p>
                            <a href="premium.php" class="premium-link" data-i18n="get_premium">Получить EFPremium</a>
                        <?php endif; ?>
                    </div>
                    <button class="save-border-btn" data-i18n="save_border">Сохранить рамку</button>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="close-menu-btn" data-i18n="close">Закрыть</button>
        </div>
    </div>
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
                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
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
<audio id="coin-sound" src="sounds/coin.mp3" preload="auto"></audio>

<div id="ef-notification" class="ef-notification"></div>
</html>