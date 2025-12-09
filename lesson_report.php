<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php'; // Подключаем расчет уровней

// 1. Проверка прав
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) { header("Location: analytics.php"); exit; }
$lesson_id = intval($_GET['id']);

// 2. Данные пользователя (для Sidebar) - ДОБАВИЛИ xp, level, highest_score
$stmt = $conn->prepare("SELECT username, full_name, email, user_type, avatar, class, points, xp, level, highest_score, avatar_border_color, border_style, ef_premium, is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || (strtolower(trim($user['user_type'])) !== 'teacher' && $user['is_admin'] != 1)) {
    header("Location: profile.php"); exit;
}

// Распаковка данных для сайдбара (чтобы код совпадал с profile.php)
$username = $user['username'] ?: 'Guest';
$full_name = $user['full_name'];
$email = $user['email'];
$user_type = $user['user_type'];
$avatar = $user['avatar'] ?: 'Def_Avatar.jpg';
$class = $user['class'] ?: '';
$points = $user['points'] ?: 0;
$xp = $user['xp'] ?: 0;        // НОВОЕ
$level = $user['level'] ?: 1;  // НОВОЕ
$border_color = $user['avatar_border_color'] ?: '#ff8c42';
$border_style = $user['border_style'] ?: 'solid-default';
$ef_premium = $user['ef_premium'] ?: 0;
$is_admin = $user['is_admin'] ?: 0;

if (strpos($class, 'frame-') !== false || strpos($class, '.') === 0) { $class = ''; }

// Рамки (для CSS)
$frames_for_css = [];
$all_frames_result = $conn->query("SELECT style_key, image_file FROM ef_premium_frames WHERE is_visible = 1");
if ($all_frames_result) { while($frame = $all_frames_result->fetch_assoc()) { $frames_for_css[] = $frame; } }

// 3. Проверка урока
$check = $conn->prepare("SELECT title FROM lessons WHERE id = ? AND user_id = ?");
$check->bind_param("ii", $lesson_id, $user_id);
$check->execute();
$lesson_data = $check->get_result()->fetch_assoc();

if (!$lesson_data) { echo "Урок не найден или доступ запрещен"; exit; }

// 4. Получаем прохождения
$sql = "SELECT lc.*, u.username, u.full_name, u.avatar, u.class 
        FROM lesson_completions lc 
        JOIN users u ON lc.user_id = u.id 
        WHERE lc.lesson_id = ? 
        ORDER BY lc.completed_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $lesson_id);
$stmt->execute();
$result = $stmt->get_result();

// Группировка студентов
$students = [];
while($row = $result->fetch_assoc()) {
    $uid = $row['user_id'];
    if (!isset($students[$uid])) {
        $students[$uid] = [
            'info' => ['name'=>$row['full_name'], 'username'=>$row['username'], 'avatar'=>$row['avatar'], 'class'=>$row['class']],
            'attempts' => [], 'best_score' => 0, 'last_date' => $row['completed_at']
        ];
    }
    $students[$uid]['attempts'][] = $row;
    
    if (intval($row['percentage']) > $students[$uid]['best_score']) {
        $students[$uid]['best_score'] = intval($row['percentage']);
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчет: <?php echo htmlspecialchars($lesson_data['title']); ?></title>
    
    <link rel="stylesheet" href="CSS/header.css">
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/settings.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <?php
    // Генерация стилей рамок (1 в 1 как в profile.php)
    if (!empty($frames_for_css)) {
        echo "<style id=\"dynamic-frame-styles\">\n";
        $after_selectors = [];
        foreach ($frames_for_css as $frame) {
            $after_selectors[] = '.border-' . htmlspecialchars($frame['style_key']) . '::after';
        }
        echo implode(",\n", $after_selectors) . " { content: ''; position: absolute; top: -2px; left: -2px; right: -2px; bottom: -2px; width: auto; height: auto; background-size: cover; background-position: center; background-repeat: no-repeat; pointer-events: none; }\n";
        foreach ($frames_for_css as $frame) {
            $class_name = '.border-' . htmlspecialchars($frame['style_key']);
            $file = 'frames/' . htmlspecialchars($frame['image_file']);
            echo "{$class_name}::after { background-image: url('{$file}'); }\n";
        }
        echo "</style>\n";
    }
    ?>

    <style>
        .report-header {
            background: white; padding: 25px; border-radius: 16px; margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center;
        }
        .back-btn { text-decoration: none; color: #64748b; font-weight: 500; display: flex; align-items: center; gap: 5px; transition: color 0.2s; }
        .back-btn:hover { color: #ff8c42; }
        
        .report-table { width: 100%; border-collapse: collapse; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .report-table th, .report-table td { padding: 15px 20px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        .report-table th { background: #f8fafc; font-weight: 600; color: #64748b; font-size: 13px; text-transform: uppercase; }
        
        .user-cell { display: flex; align-items: center; gap: 12px; }
        .user-ava { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 1px solid #eee; }
        
        .score-badge { padding: 5px 10px; border-radius: 20px; font-weight: 700; font-size: 13px; display: inline-block; }
        .score-high { background: #dcfce7; color: #166534; }
        .score-mid { background: #fef9c3; color: #854d0e; }
        .score-low { background: #fee2e2; color: #991b1b; }

        .clickable-row { cursor: pointer; transition: background 0.2s; }
        .clickable-row:hover { background: #f1f5f9; }
        .expand-icon { color: #cbd5e1; transition: transform 0.3s; }
        .clickable-row.active .expand-icon { transform: rotate(180deg); color: #7b61ff; }
        
        .details-row { display: none; background: #f8fafc; }
        .details-row.active { display: table-row; }
        
        .history-table { width: 95%; margin: 10px auto; border: 1px solid #e2e8f0; border-radius: 8px; background: white; }
        .history-table th { background: #eff6ff; color: #1e40af; font-size: 11px; padding: 10px; }
        .history-table td { padding: 10px; font-size: 13px; color: #475569; border-bottom: 1px dashed #e2e8f0; }
        .attempts-count-badge { background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }

        .view-ans-btn {
            background: #fff; border: 1px solid #7b61ff; color: #7b61ff; padding: 5px 10px; 
            border-radius: 6px; font-size: 12px; cursor: pointer; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .view-ans-btn:hover { background: #7b61ff; color: white; }

        /* Модальное окно ответов */
        .ans-modal-backdrop {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 10000; backdrop-filter: blur(3px);
        }
        .ans-modal {
            display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 700px; max-width: 95%; max-height: 85vh; overflow-y: auto;
            background: white; border-radius: 16px; padding: 25px; z-index: 10001;
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
        }
        .ans-modal.active, .ans-modal-backdrop.active { display: block; }
        
        .ans-item { border-bottom: 1px solid #eee; padding: 20px 0; }
        .ans-item:last-child { border-bottom: none; }
        .ans-question { font-weight: 700; color: #1e293b; margin-bottom: 8px; font-size: 15px; }
        .ans-response { font-size: 14px; padding: 12px; border-radius: 8px; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
        
        .ans-correct { border-left: 4px solid #22c55e; background: #f0fdf4; color: #166534; }
        .ans-incorrect { border-left: 4px solid #ef4444; background: #fef2f2; color: #991b1b; }
        .ans-neutral { border-left: 4px solid #cbd5e1; }
        
        .ans-file-link { 
            display: inline-flex; align-items: center; gap: 8px;
            color: white; background: #3b82f6; text-decoration: none; font-weight: 500;
            padding: 10px 20px; border-radius: 8px; transition: background 0.2s;
            margin-top: 5px;
        }
        .ans-file-link:hover { background: #2563eb; }

        /* Дизайн пар (Соответствие) */
        .match-grid { display: grid; gap: 8px; margin-top: 5px; }
        .match-pair {
            display: flex; align-items: stretch;
            border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;
        }
        .match-key {
            background: #f1f5f9; padding: 10px 15px; font-weight: 600; width: 45%;
            border-right: 1px solid #e2e8f0; color: #475569; display: flex; align-items: center;
        }
        .match-val {
            background: #fff; padding: 10px 15px; width: 55%; color: #1e293b; display: flex; align-items: center;
        }
        .match-icon { margin: 0 10px; color: #cbd5e1; }
    </style>
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
                <div class="dropdown">
                    <a href="profile.php" class="login-btn dropbtn" data-i18n="profile">Profile</a>
                    <div class="dropdown-content">
                        <a href="logout.php" data-i18n="logout">Logout</a>
                    </div>
                </div>
                <button class="burger-menu" aria-label="Toggle Menu">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
        <nav class="mobile-nav">
            <a href="index.php" data-i18n="home">Home</a>
            <a href="index.php#about" data-i18n="about">About</a>
            <div class="mobile-actions">
                <a href="login.php" class="login-btn" data-i18n="login">Login</a>
            </div>
        </nav>
    </header>

    <div id="ef-notification" class="ef-notification"><i class="fas fa-coins"></i><span class="plus">+0</span><span>EF</span></div>

    <section id="profile" class="profile-container">
        
        <aside class="sidebar">
            <div class="user-info">
                <?php
                    $main_avatar_style = ''; $main_avatar_class = ''; $is_frame = false; 
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
                <div class="user-icon <?php echo $main_avatar_class; ?>" style="background-image: url('img/avatar/<?php echo htmlspecialchars($avatar); ?>'); <?php echo $main_avatar_style; ?>"></div>
                
                <h3 id="profile-username"><?php echo htmlspecialchars($username); ?></h3>
                <p class="class-display"><span data-i18n="class_label">Класс:</span> <?php echo htmlspecialchars($class); ?></p>
                <p><span data-i18n="user_type_label">Тип:</span> <?php echo htmlspecialchars(ucfirst($user_type)); ?></p>
                <p><span data-i18n="email_label">Email:</span> <?php echo htmlspecialchars($email); ?></p>
                
                <div class="ef-points">
                    <i class="fas fa-coins"></i>
                    <span class="points-value"><?php echo htmlspecialchars(number_format($points)); ?></span>
                    <span class="points-label">EF</span>
                </div>
                
                <?php
                    // Используем функцию calculateLevel из functions.php
                    $lvlData = calculateLevel($xp); 
                ?>
                <div class="level-progress">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <span style="font-weight:700; color:var(--primary-color);">Уровень <?php echo $level; ?></span>
                        <span style="font-size:11px; color:#64748b;"><?php echo number_format($lvlData['xp_current_level']); ?> / <?php echo number_format($lvlData['xp_next_level']); ?> XP</span>
                    </div>
                    <div class="progress-bar" style="background:#e2e8f0; height:8px; border-radius:4px; overflow:hidden;">
                        <div class="fill" style="width: <?php echo $lvlData['progress']; ?>%; background: linear-gradient(90deg, var(--primary-color), var(--secondary-color)); height:100%; transition: width 0.5s ease;"></div>
                    </div>
                </div>
            </div>

            <button class="settings-btn" onclick="window.location.href='profile.php'">Settings</button>
            <button class="settings-btn" onclick="window.location.href='teacher_dashboard.php'" style="margin-top: 10px; background: var(--secondary-color);">Сфера разработки</button>
            <?php if ($is_admin == 1): ?><div class="admin-buttons-container" style="margin-top: 10px;"><a href="admin_frames.php" class="admin-panel-btn">Control Server</a></div><?php endif; ?>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
        </aside>

        <main class="main-content">
            <div class="report-header">
                <div>
                    <a href="analytics.php" class="back-btn"><i class="fas fa-arrow-left"></i> К аналитике</a>
                    <h2 style="margin: 10px 0 0 0; color: #1e293b;"><?php echo htmlspecialchars($lesson_data['title']); ?></h2>
                    <p style="margin: 5px 0 0 0; color: #64748b;">Отчет по успеваемости</p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 24px; font-weight: 700; color: #7b61ff;"><?php echo count($students); ?></div>
                    <div style="font-size: 12px; color: #94a3b8;">Учеников</div>
                </div>
            </div>

            <?php if (count($students) > 0): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Ученик</th>
                            <th>Класс</th>
                            <th>Посл. активность</th>
                            <th>Попыток</th>
                            <th>Лучший рез.</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $uid => $data): 
                            $uInfo = $data['info'];
                            $attemptsCount = count($data['attempts']);
                            $percent = $data['best_score'];
                            $class_score = ($percent >= 80) ? 'score-high' : (($percent >= 50) ? 'score-mid' : 'score-low');
                        ?>
                            <tr class="clickable-row" onclick="toggleDetails(<?php echo $uid; ?>, this)">
                                <td>
                                    <div class="user-cell">
                                        <img src="img/avatar/<?php echo $uInfo['avatar']?:'Def_Avatar.jpg'; ?>" class="user-ava">
                                        <div>
                                            <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($uInfo['name']); ?></div>
                                            <div style="font-size: 12px; color: #94a3b8;">@<?php echo htmlspecialchars($uInfo['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($uInfo['class']); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($data['last_date'])); ?></td>
                                <td><span class="attempts-count-badge"><i class="fas fa-redo-alt"></i> <?php echo $attemptsCount; ?></span></td>
                                <td><span class="score-badge <?php echo $class_score; ?>"><?php echo $percent; ?>%</span></td>
                                <td style="text-align: right;"><i class="fas fa-chevron-down expand-icon"></i></td>
                            </tr>

                            <tr id="details-<?php echo $uid; ?>" class="details-row">
                                <td colspan="6">
                                    <table class="history-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Дата</th>
                                                <th>Время</th>
                                                <th>Баллы</th>
                                                <th>Результат</th>
                                                <th>Действия</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $attIndex = count($data['attempts']);
                                            foreach($data['attempts'] as $att): 
                                                $min = floor($att['time_spent'] / 60); $sec = $att['time_spent'] % 60;
                                                $time_str = sprintf("%02d:%02d", $min, $sec);
                                                // Кодируем ответы в JSON для передачи в JS
                                                $answersJson = base64_encode($att['user_answers'] ?? '[]');
                                            ?>
                                                <tr>
                                                    <td><?php echo $attIndex--; ?></td>
                                                    <td><?php echo date('d.m.Y H:i', strtotime($att['completed_at'])); ?></td>
                                                    <td><?php echo $time_str; ?></td>
                                                    <td><?php echo $att['score']; ?> / <?php echo $att['max_score']; ?></td>
                                                    <td style="font-weight:600;"><?php echo $att['percentage']; ?>%</td>
                                                    <td>
                                                        <button class="view-ans-btn" onclick="openAnswersModal('<?php echo $answersJson; ?>')">
                                                            <i class="fas fa-eye"></i> Просмотр
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 50px; background: white; border-radius: 16px;">
                    <p style="color: #64748b;">Никто еще не проходил этот урок.</p>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <div class="ans-modal-backdrop" id="amsBackdrop" onclick="closeAnswersModal()"></div>
    <div class="ans-modal" id="amsModal">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; font-size:18px;">Детали ответов</h3>
            <button onclick="closeAnswersModal()" style="background:none; border:none; font-size:24px; cursor:pointer;">&times;</button>
        </div>
        <div id="answersContent"></div>
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
        function toggleDetails(uid, row) {
            if(event.target.closest('button')) return; 
            const details = document.getElementById('details-' + uid);
            const isActive = details.classList.contains('active');
            if (!isActive) { details.classList.add('active'); row.classList.add('active'); }
            else { details.classList.remove('active'); row.classList.remove('active'); }
        }

        function openAnswersModal(base64Data) {
            const content = document.getElementById('answersContent');
            content.innerHTML = '';

            let data = [];
            try {
                // Декодируем с поддержкой кириллицы
                const binaryString = atob(base64Data);
                const bytes = new Uint8Array(binaryString.length);
                for (let i = 0; i < binaryString.length; i++) {
                    bytes[i] = binaryString.charCodeAt(i);
                }
                const jsonStr = new TextDecoder().decode(bytes);
                data = JSON.parse(jsonStr);
            } catch (e) {
                console.error("Ошибка парсинга данных:", e);
                content.innerHTML = '<p style="color:#999; text-align:center;">Ошибка загрузки ответов.</p>';
                return;
            }

            if (!data || data.length === 0) {
                content.innerHTML = '<p style="color:#999; text-align:center;">Нет данных об ответах (возможно, старая версия попытки)</p>';
            } else {
                data.forEach(item => {
                    let styleClass = 'ans-neutral';
                    if (item.is_correct === true) styleClass = 'ans-correct';
                    else if (item.is_correct === false) styleClass = 'ans-incorrect';

                    let responseHtml = '';

                    // 1. ФАЙЛ
                    if (item.type === 'file_submission' && item.user_response) {
                        if (item.user_response.startsWith('uploads/')) {
                            const parts = item.user_response.split('_');
                            const simpleName = parts.length > 2 ? parts.slice(2).join('_') : item.user_response.split('/').pop();
                            
                            responseHtml = `
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span>Загружен файл: <strong>${simpleName}</strong></span>
                                    <a href="${item.user_response}" target="_blank" class="ans-file-link" download>
                                        <i class="fas fa-download"></i> Скачать файл
                                    </a>
                                </div>`;
                        } else {
                            responseHtml = '<span style="color:#94a3b8; font-style:italic;">Файл не найден или не загружен</span>';
                        }
                    } 
                    // 2. СООТВЕТСТВИЕ
                    else if (item.type === 'question_matching' && item.user_response) {
                        const pairs = item.user_response.split('; ');
                        let gridHtml = '<div class="match-grid">';
                        pairs.forEach(pairStr => {
                            const [key, val] = pairStr.split(' = ');
                            if(key && val) {
                                gridHtml += `
                                    <div class="match-pair">
                                        <div class="match-key">${key}</div>
                                        <div class="match-val"><i class="fas fa-arrow-right match-icon"></i> ${val}</div>
                                    </div>`;
                            }
                        });
                        gridHtml += '</div>';
                        responseHtml = gridHtml;
                    }
                    // 3. ТЕКСТ
                    else {
                        responseHtml = item.user_response || '<i style="color:#94a3b8">Нет ответа</i>';
                    }

                    const html = `
                        <div class="ans-item">
                            <div class="ans-question">${item.question}</div>
                            <div class="ans-response ${styleClass}">${responseHtml}</div>
                        </div>
                    `;
                    content.innerHTML += html;
                });
            }

            document.getElementById('amsModal').classList.add('active');
            document.getElementById('amsBackdrop').classList.add('active');
        }

        function closeAnswersModal() {
            document.getElementById('amsModal').classList.remove('active');
            document.getElementById('amsBackdrop').classList.remove('active');
        }
    </script>
</body>
</html>