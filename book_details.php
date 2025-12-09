<?php
session_start();
require_once 'db_connect.php';

// 1. Проверка ID книги
if (!isset($_GET['id'])) {
    header("Location: online-book.php");
    exit;
}
$book_id = intval($_GET['id']);
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// --- ИСПРАВЛЕННАЯ ЛОГИКА РЕЙТИНГА (С УЧЕТОМ СРЕДНЕГО) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_rating'])) {
    if ($user_id == 0) {
        // ПЕРЕВОД: Вызываем JS-функцию, которая покажет переведенный alert
        echo "<script>document.addEventListener('DOMContentLoaded', showRatingAlert);</script>";
    } else {
        $new_rating = intval($_POST['rating_value']);
        
        if ($new_rating >= 1 && $new_rating <= 5) {
            // A. Сохраняем голос конкретного пользователя в таблицу book_ratings
            // ON DUPLICATE KEY UPDATE означает: если пользователь уже голосовал, просто обновим его оценку
            $stmt_vote = $conn->prepare("INSERT INTO book_ratings (book_id, user_id, rating) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE rating = ?");
            $stmt_vote->bind_param("iiii", $book_id, $user_id, $new_rating, $new_rating);
            $stmt_vote->execute();
            $stmt_vote->close();

            // B. Высчитываем новое СРЕДНЕЕ значение
            $avg_query = "SELECT AVG(rating) as avg_rate FROM book_ratings WHERE book_id = $book_id";
            $avg_res = $conn->query($avg_query);
            $avg_row = $avg_res->fetch_assoc();
            $final_rating = round($avg_row['avg_rate'], 1); // Округляем до 1 знака (например, 4.5)

            // C. Обновляем общую цифру в таблице books (для быстрого отображения)
            $stmt_update = $conn->prepare("UPDATE books SET rating = ? WHERE id = ?");
            $stmt_update->bind_param("di", $final_rating, $book_id);
            $stmt_update->execute();
            $stmt_update->close();

            // Перезагружаем страницу
            header("Location: book_details.php?id=$book_id");
            exit;
        }
    }
}

// 2. Получаем данные книги + данные автора
$sql = "SELECT books.*, users.username as teacher_name, users.avatar as teacher_avatar, users.id as teacher_id
        FROM books 
        LEFT JOIN users ON books.user_id = users.id 
        WHERE books.id = $book_id";
$res = $conn->query($sql);

if ($res->num_rows == 0) {
    echo "Книга не найдена"; exit;
}
$book = $res->fetch_assoc();

// Получаем количество голосов для отображения (опционально)
$count_sql = "SELECT COUNT(*) as total_votes FROM book_ratings WHERE book_id = $book_id";
$count_res = $conn->query($count_sql);
$total_votes = $count_res->fetch_assoc()['total_votes'];

// 3. ЛОГИКА ПОЛЬЗОВАТЕЛЯ (Sidebar)
$username = "Guest"; $full_name = "Гость"; $email = ""; $user_type = "Guest";
$avatar = "Def_Avatar.jpg"; $class = ""; $points = 0;
$border_color = "#ff8c42"; $border_style = "solid-default"; $ef_premium = 0; $is_admin = 0;

$frames_for_css = [];
$all_frames_result = $conn->query("SELECT style_key, image_file FROM ef_premium_frames WHERE is_visible = 1");
if ($all_frames_result) {
    while($frame = $all_frames_result->fetch_assoc()) {
        $frames_for_css[] = $frame;
    }
}

if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT username, full_name, email, user_type, avatar, class, points, avatar_border_color, border_style, ef_premium, is_admin FROM users WHERE id = ?");
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
        $border_color = $row['avatar_border_color'] ?: '#ff8c42';
        $border_style = $row['border_style'] ?: 'solid-default';
        $ef_premium = $row['ef_premium'] ?: 0;
        $is_admin = $row['is_admin'] ?: 0;
    }
    $stmt->close();
}
if (strpos($class, 'frame-') !== false || strpos($class, '.') === 0) { $class = ''; }
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - EDU-Familiar</title>
    
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/settings.css">
    <link rel="stylesheet" href="CSS/online-book-styles.css">
    <link rel="stylesheet" href="CSS/header.css"> 

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .book-container {
            display: grid; grid-template-columns: 340px 1fr; gap: 40px;
            background: #ffffff; border-radius: 20px; padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid #f1f5f9;
        }
        .book-aside { display: flex; flex-direction: column; gap: 20px; }
        .book-cover-wrapper-lg {
            position: relative; border-radius: 12px; overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15); transition: transform 0.3s;
            aspect-ratio: 2/3; background: #f1f5f9;
        }
        .book-cover-wrapper-lg img { width: 100%; height: 100%; object-fit: cover; }
        .action-buttons { display: flex; flex-direction: column; gap: 12px; }
        .btn-action {
            width: 100%; padding: 16px; border-radius: 12px; font-weight: 600; font-size: 16px;
            text-decoration: none; display: flex; align-items: center; justify-content: center;
            gap: 10px; transition: all 0.3s ease; border: none; cursor: pointer;
        }
        .btn-primary-gradient {
            background: linear-gradient(135deg, var(--primary-color) 0%, #ff6b6b 100%); color: white;
            box-shadow: 0 8px 20px rgba(255, 140, 66, 0.25);
        }
        .btn-primary-gradient:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(255, 140, 66, 0.35); }
        .btn-soft { background: #f1f5f9; color: #475569; }
        .btn-soft:hover { background: #e2e8f0; color: #1e293b; }

        .book-info h1 { font-size: 32px; font-weight: 700; color: #1e293b; margin-bottom: 20px; line-height: 1.2; }
        .tags-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 25px; }
        .meta-tag { padding: 8px 16px; border-radius: 30px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .tag-subject { background: #eff6ff; color: #3b82f6; }
        .tag-class { background: #fff7ed; color: #f97316; }
        .tag-lang { background: #f0fdf4; color: #22c55e; }
        .tag-rating { background: #fefce8; color: #eab308; border: 1px solid #fef08a; }

        .section-title { font-size: 18px; font-weight: 600; color: #334155; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .description-text { font-size: 16px; line-height: 1.7; color: #64748b; margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid #e2e8f0; }
        .interactive-footer { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start; }
        
        .author-card {
            display: flex; align-items: center; gap: 15px; padding: 15px;
            background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;
        }
        .author-avatar-img {
            width: 48px; height: 48px; border-radius: 50%; object-fit: cover;
            border: 2px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .rating-box { background: #fff; padding: 20px; border-radius: 12px; border: 2px dashed #e2e8f0; text-align: center; }
        .rating-box h4 { margin-bottom: 10px; font-size: 14px; color: #64748b; }
        .star-rating-group { display: flex; flex-direction: row-reverse; justify-content: center; gap: 8px; }
        .star-rating-group input { display: none; }
        .star-rating-group label { font-size: 28px; color: #cbd5e1; cursor: pointer; transition: color 0.2s, transform 0.2s; }
        .star-rating-group label:hover, .star-rating-group label:hover ~ label, .star-rating-group input:checked ~ label { color: #fbbf24; transform: scale(1.1); }

        .report-link {
            display: block; text-align: center; margin-top: 15px; color: #ef4444;
            font-size: 13px; font-weight: 500; text-decoration: none; opacity: 0.7; transition: opacity 0.2s;
        }
        .report-link:hover { opacity: 1; text-decoration: underline; }

        @media (max-width: 900px) { .book-container { grid-template-columns: 1fr; padding: 20px; } .interactive-footer { grid-template-columns: 1fr; } }
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

    <div id="ef-notification" class="ef-notification"><i class="fas fa-coins"></i><span class="plus">+0</span><span>EF</span></div>

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
            
            <div style="margin-bottom: 20px;">
                <a href="online-book.php" style="color: #64748b; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 5px; transition: color 0.2s;" data-i18n="back_to_library">
                    <i class="fas fa-arrow-left"></i> Вернуться в библиотеку
                </a>
            </div>

            <div class="book-container">
                
                <div class="book-aside">
                    <div class="book-cover-wrapper-lg">
                        <img src="uploads/covers/<?php echo htmlspecialchars($book['cover_image'] ?: 'default_book.jpg'); ?>" alt="Book Cover">
                    </div>
                    
                    <div class="action-buttons">
                        <a href="book_action.php?id=<?php echo $book_id; ?>&action=read" target="_blank" class="btn-action btn-primary-gradient">
                            <i class="fas fa-book-reader"></i> <span data-i18n="read_online_button">Читать Онлайн</span>
                        </a>
                        
                        <a href="book_action.php?id=<?php echo $book_id; ?>&action=download" class="btn-action btn-soft">
                            <i class="fas fa-download"></i> <span data-i18n="download_pdf_button">Скачать PDF</span>
                        </a>
                        
                        <?php if ($user_id > 0 && $user_id != $book['teacher_id']): ?>
                            <small style="text-align:center; color:#94a3b8; font-size:11px; margin-top:-5px;" data-i18n="author_support_notice">
                                <i class="fas fa-coins" style="color:#ffd700;"></i> Автор получит поддержку при просмотре
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="book-info">
                    
                    <h1><?php echo htmlspecialchars($book['title']); ?></h1>

                    <div class="tags-row">
                        <span class="meta-tag tag-rating" title="На основе <?php echo $total_votes; ?> голосов">
                            <i class="fas fa-star"></i> <?php echo number_format($book['rating'], 1); ?> (<?php echo $total_votes; ?>)
                        </span>
                        <span class="meta-tag tag-subject">
                            <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($book['subject']); ?>
                        </span>
                        <span class="meta-tag tag-class">
                            <i class="fas fa-graduation-cap"></i> <?php echo $book['class_level']; ?> Класс
                        </span>
                        <span class="meta-tag tag-lang">
                            <i class="fas fa-language"></i> <?php echo htmlspecialchars($book['languages']); ?>
                        </span>
                    </div>

                    <div class="section-title" data-i18n="description_label"><i class="fas fa-align-left"></i> Описание материала</div>
                    <div class="description-text">
                        <?php echo nl2br(htmlspecialchars($book['full_description'])); ?>
                    </div>

                    <div class="interactive-footer">
                        
                        <div class="rating-box">
                            <h4 data-i18n="your_rating_label">Ваша оценка материала</h4>
                            <form method="POST" class="star-rating-group">
                                <input type="hidden" name="set_rating" value="1">
                                <input type="radio" name="rating_value" value="5" id="rate-5" onchange="this.form.submit()"><label for="rate-5" data-i18n-title="rating_5_star" title="Отлично"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating_value" value="4" id="rate-4" onchange="this.form.submit()"><label for="rate-4" data-i18n-title="rating_4_star" title="Хорошо"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating_value" value="3" id="rate-3" onchange="this.form.submit()"><label for="rate-3" data-i18n-title="rating_3_star" title="Нормально"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating_value" value="2" id="rate-2" onchange="this.form.submit()"><label for="rate-2" data-i18n-title="rating_2_star" title="Плохо"><i class="fas fa-star"></i></label>
                                <input type="radio" name="rating_value" value="1" id="rate-1" onchange="this.form.submit()"><label for="rate-1" data-i18n-title="rating_1_star" title="Ужасно"><i class="fas fa-star"></i></label>
                            </form>
                        </div>

                        <div>
                            <div class="section-title" style="font-size:14px; margin-bottom:8px;" data-i18n="author_label">Автор публикации</div>
                            <div class="author-card">
                                <img src="img/avatar/<?php echo htmlspecialchars($book['teacher_avatar'] ?: 'Def_Avatar.jpg'); ?>" class="author-avatar-img">
                                <div>
                                    <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($book['teacher_name'] ?: 'Неизвестно'); ?></div>
                                    <div style="font-size: 12px; color: #64748b;"><?php echo date('d.m.Y', strtotime($book['created_at'])); ?></div>
                                </div>
                            </div>
                            
                            <a href="#" class="report-link" onclick="return showReportAlert(event);">
                                <i class="fas fa-exclamation-triangle"></i> <span data-i18n="report_error_link">Нашли ошибку? Пожаловаться</span>
                            </a>
                        </div>

                    </div>
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
    
    <script>
    // Для ссылки "Пожаловаться"
    function showReportAlert(event) {
        if (event) event.preventDefault();
        const lang = localStorage.getItem('language') || 'ru';
        let message = 'Функция жалоб временно недоступна. Мы работаем над этим!'; // Fallback

        // Проверяем, загружен ли 'translations' из language.js
        if (typeof translations !== 'undefined' && translations[lang] && translations[lang].report_error_alert) {
            message = translations[lang].report_error_alert;
        }
        alert(message);
        return false;
    }

    // Для PHP-уведомления "Войдите, чтобы оценить"
    function showRatingAlert() {
        const lang = localStorage.getItem('language') || 'ru';
        let message = 'Пожалуйста, авторизуйтесь, чтобы оценить книгу.'; // Fallback

        if (typeof translations !== 'undefined' && translations[lang] && translations[lang].alert_rating_login) {
            message = translations[lang].alert_rating_login;
        }
        alert(message);
    }
    </script>
    
    <script src="JS/coins.js"></script>
</body>
</html>