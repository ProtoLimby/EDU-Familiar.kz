
<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php'; 

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];


if (!isset($_GET['id'])) { header("Location: profile.php"); exit; }
$class_id = intval($_GET['id']);


$stmt = $conn->prepare("
    SELECT c.*, u.username as owner_username, u.full_name as owner_name, u.avatar as owner_avatar, u.id as owner_id
    FROM classes c 
    JOIN users u ON c.teacher_id = u.id 
    WHERE c.id = ?
");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$class_info = $stmt->get_result()->fetch_assoc();

if (!$class_info) { echo "Класс не найден."; exit; }


$is_owner = ($class_info['teacher_id'] == $user_id);
$my_role = '';

if ($is_owner) {
    $my_role = 'teacher';
} else {
    $check_sql = "SELECT role, status FROM class_members WHERE class_id = ? AND user_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $class_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) { echo "Доступ запрещен. Вы не являетесь участником этого класса."; exit; }
    $member_data = $res->fetch_assoc();
    if ($member_data['status'] !== 'accepted') { echo "Ваша заявка в этот класс еще не одобрена или отклонена."; exit; }
    $my_role = $member_data['role'];
    $stmt->close();
}


$sections = [];
$sql_sec = "SELECT * FROM class_sections WHERE class_id = $class_id ORDER BY created_at ASC";
$sec_res = $conn->query($sql_sec);

if ($sec_res) {
    while($sec = $sec_res->fetch_assoc()) {
        $s_id = $sec['id'];
        $can_edit = false;
        if ($my_role === 'teacher') {
            if ($is_owner) { $can_edit = true; } 
            else {
                $check_assign = $conn->query("SELECT id FROM section_teachers WHERE section_id = $s_id AND teacher_id = $user_id");
                if ($check_assign->num_rows > 0) { $can_edit = true; }
            }
        }
        
        $lessons = [];
        $l_sql = "SELECT id, title, cover_image, subject, short_description, rating, coins_reward, language FROM lessons WHERE section_id = $s_id AND is_hidden = 0";
        $l_res = $conn->query($l_sql);
        while($l = $l_res->fetch_assoc()) { $lessons[] = $l; }
        
        $sec['lessons'] = $lessons;
        $sec['can_edit'] = $can_edit;
        $sections[] = $sec;
    }
}


$members_teachers = [];
$members_students = [];

$mem_sql = "
    SELECT u.id, u.username, u.full_name, u.avatar, cm.role 
    FROM class_members cm 
    JOIN users u ON cm.user_id = u.id 
    WHERE cm.class_id = ? AND cm.status = 'accepted'
    ORDER BY u.full_name ASC
";
$mem_stmt = $conn->prepare($mem_sql);
$mem_stmt->bind_param("i", $class_id);
$mem_stmt->execute();
$mem_res = $mem_stmt->get_result();

while($row = $mem_res->fetch_assoc()) {
    if ($row['role'] === 'teacher') {
        $members_teachers[] = $row;
    } else {
        $members_students[] = $row;
    }
}
$mem_stmt->close();



$u_res = $conn->query("SELECT username, full_name, email, user_type, avatar, class, points, xp, level, avatar_border_color, border_style, ef_premium, is_admin FROM users WHERE id = $user_id")->fetch_assoc();
$username = $u_res['username']; 
$full_name = $u_res['full_name']; 
$email = $u_res['email']; 
$user_type = $u_res['user_type']; 
$avatar = $u_res['avatar'] ?: 'Def_Avatar.jpg'; 
$class = $u_res['class'] ?: ''; 
$points = $u_res['points'] ?: 0;
$xp = $u_res['xp'] ?: 0;
$level = $u_res['level'] ?: 1;
$lvlData = calculateLevel($xp);

$border_color = $u_res['avatar_border_color'] ?: '#ff8c42'; 
$border_style = $u_res['border_style'] ?: 'solid-default'; 
$ef_premium = $u_res['ef_premium'] ?: 0; 
$is_admin = $u_res['is_admin'] ?: 0;

if (strpos($class, 'frame-') !== false || strpos($class, '.') === 0) { $class = ''; }
$frames_for_css = []; 
$all_frames_result = $conn->query("SELECT style_key, image_file FROM ef_premium_frames WHERE is_visible = 1"); 
if ($all_frames_result) { while($frame = $all_frames_result->fetch_assoc()) { $frames_for_css[] = $frame; } }


$inv_res = $conn->query("SELECT COUNT(*) as cnt FROM class_members WHERE user_id = $user_id AND status = 'pending'");
$invites_count = $inv_res->fetch_assoc()['cnt'];

$my_classes_ids = [];
$r1 = $conn->query("SELECT id FROM classes WHERE teacher_id = $user_id");
while($rw = $r1->fetch_assoc()) $my_classes_ids[] = $rw['id'];
$r2 = $conn->query("SELECT class_id FROM class_members WHERE user_id = $user_id AND status = 'accepted'");
while($rw = $r2->fetch_assoc()) $my_classes_ids[] = $rw['class_id'];
$joined_classes_count = count(array_unique($my_classes_ids));
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($class_info['name'] ?? ''); ?> - Класс</title>
    <link rel="stylesheet" href="CSS/header.css">
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/settings.css">
    <link rel="stylesheet" href="CSS/class_view.css">
    <link rel="stylesheet" href="CSS/online-book-styles.css"> <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    else { if (preg_match('/^#[0-9A-Fa-f]{6}$/', $border_color)) { $main_avatar_style = 'border: 4px solid ' . htmlspecialchars($border_color) . ';'; } else { $main_avatar_style = 'border: 4px solid #ff8c42;'; } }
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
            
            <div class="class-page-header">
                <div class="class-page-title">
                    <h1><?php echo htmlspecialchars($class_info['name'] ?? ''); ?></h1>
                    <p>Параллель: <?php echo $class_info['grade']; ?> | Ваша роль: <strong><?php echo ($my_role === 'teacher') ? 'Учитель' : 'Ученик'; ?></strong></p>
                </div>
            </div>

            <div class="class-layout">
                
                <div class="class-sidebar-left">
                    <div class="sidebar-tabs">
                        <button class="sidebar-tab-btn active" onclick="switchSidebarTab('topics', this)">
                            <i class="fas fa-list"></i> Темы
                        </button>
                        <button class="sidebar-tab-btn" onclick="switchSidebarTab('members', this)">
                            <i class="fas fa-users"></i> Люди (<?php echo 1 + count($members_teachers) + count($members_students); ?>)
                        </button>
                    </div>

                    <div id="sidebar-content-topics" class="sidebar-content-pane active">
                        <ul class="section-nav-list">
                            <?php if (empty($sections)): ?>
                                <li style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;">Нет доступных разделов</li>
                            <?php else: ?>
                                <?php foreach($sections as $index => $sec): 
                                    $activeClass = ($index === 0) ? 'active' : '';
                                ?>
                                    <li class="section-nav-item <?php echo $activeClass; ?>" onclick="showSection(<?php echo $sec['id']; ?>, this)">
                                        <?php echo htmlspecialchars($sec['title']); ?>
                                        <i class="fas fa-chevron-right" style="font-size:10px;"></i>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div id="sidebar-content-members" class="sidebar-content-pane">
                        <div class="members-list-container">
                            
                            <div class="members-section-title">Куратор</div>
                            <div class="member-mini-card is-teacher">
                                <img src="img/avatar/<?php echo htmlspecialchars($class_info['owner_avatar'] ?? 'Def_Avatar.jpg'); ?>" class="mm-avatar">
                                <div class="mm-info">
                                    <h5><?php echo htmlspecialchars($class_info['owner_name'] ?? ''); ?></h5>
                                    <span>Владелец</span>
                                </div>
                                <i class="fas fa-crown role-icon" style="color:#ffd700;"></i>
                            </div>

                            <?php if (!empty($members_teachers)): ?>
                                <div class="members-section-title">Учителя</div>
                                <?php foreach ($members_teachers as $mt): ?>
                                    <div class="member-mini-card is-teacher">
                                        <img src="img/avatar/<?php echo htmlspecialchars($mt['avatar'] ?? 'Def_Avatar.jpg'); ?>" class="mm-avatar">
                                        <div class="mm-info">
                                            <h5><?php echo htmlspecialchars($mt['full_name'] ?? ''); ?></h5>
                                            <span>Учитель</span>
                                        </div>
                                        <i class="fas fa-chalkboard-teacher role-icon" style="color:var(--secondary-color);"></i>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="members-section-title">Ученики (<?php echo count($members_students); ?>)</div>
                            <?php if (empty($members_students)): ?>
                                <div style="font-size:12px; color:#94a3b8; font-style:italic;">Список пуст</div>
                            <?php else: ?>
                                <?php foreach ($members_students as $ms): ?>
                                    <div class="member-mini-card">
                                        <img src="img/avatar/<?php echo htmlspecialchars($ms['avatar'] ?? 'Def_Avatar.jpg'); ?>" class="mm-avatar">
                                        <div class="mm-info">
                                            <h5><?php echo htmlspecialchars($ms['full_name'] ?? ''); ?></h5>
                                            <span>Ученик</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="class-content-center">
                    <?php if (empty($sections)): ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open" style="font-size:40px; margin-bottom:15px;"></i>
                            <p>Учитель еще не создал разделы для этого класса.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($sections as $index => $sec): 
                            $activeClass = ($index === 0) ? 'active' : '';
                        ?>
                            <div id="section-view-<?php echo $sec['id']; ?>" class="section-content-view <?php echo $activeClass; ?>">
                                <div class="section-view-header">
                                    <div class="sv-title"><?php echo htmlspecialchars($sec['title']); ?></div>
                                    <?php if ($sec['can_edit']): ?>
                                        <button class="btn-add-lesson" onclick="openAddLessonModal(<?php echo $sec['id']; ?>)">
                                            <i class="fas fa-plus"></i> Добавить урок
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <div class="lessons-grid">
                                    <?php if (empty($sec['lessons'])): ?>
                                        <div class="empty-state" style="grid-column:1/-1; background:none; border:none; padding: 20px;">
                                            <p>В этом разделе пока нет уроков.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach($sec['lessons'] as $lesson): ?>
                                            <div class="book-card">
                                                <div class="book-cover-wrapper" style="height: 160px;">
                                                    <?php 
                                                        $cover = $lesson['cover_image'];
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
                                                        <span style="margin-right: 8px;"><i class="fas fa-star" style="color:#ffd700;"></i> <?php echo number_format($lesson['rating'] ?? 0, 1); ?></span>
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
                                                                if ($code === 'kz') $style = 'background: #e0f2f1; color: #00695c; border: 1px solid #b2dfdb;';
                                                                elseif ($code === 'en') $style = 'background: #f3e5f5; color: #7b1fa2; border: 1px solid #e1bee7;';
                                                                elseif ($code === 'ru') $style = 'background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb;';
                                                                echo "<span class='tag' style='$style'>$label</span>";
                                                            }
                                                        ?>
                                                        <span class="tag"><?php echo htmlspecialchars($lesson['subject']); ?></span>
                                                    </div>
                                                    
                                                    <h3><?php echo htmlspecialchars($lesson['title']); ?></h3>
                                                    
                                                    <p class="book-desc-short">
                                                        <?php echo htmlspecialchars($lesson['short_description'] ?: 'Интерактивный урок.'); ?>
                                                    </p>

                                                    <div class="book-card-footer">
                                                        <a href="lesson_details.php?id=<?php echo $lesson['id']; ?>" class="book-action-btn primary">Подробнее</a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

        </main>
    </section>

    <div id="addLessonModal" class="modal-overlay">
        <div class="modal-box">
            <h3 style="margin-top:0;">Добавить урок в раздел</h3>
            
            <div style="display:flex; gap:10px; margin: 15px 0;">
                <input type="text" id="lesson-search" placeholder="Поиск моих уроков..." style="flex:1; padding:10px; border:1px solid #e2e8f0; border-radius:8px;">
                
                <a href="create_lesson.php" class="btn-add-lesson" style="text-decoration:none; white-space:nowrap;">
                    <i class="fas fa-plus"></i> Создать в конструкторе
                </a>
            </div>

            <div class="modal-list" id="my-lessons-list" style="height:300px;">
                <div style="padding:20px; text-align:center; color:#999;">Загрузка...</div>
            </div>
            
            <div style="margin-top:20px; text-align:right; display:flex; gap:10px; justify-content:flex-end;">
                <button onclick="closeModal()" style="background:#f1f5f9; border:none; padding:10px 20px; border-radius:8px; cursor:pointer;">Отмена</button>
                <button onclick="confirmAddLesson()" style="background:#7b61ff; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer;">Добавить</button>
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
    <script src="JS/coins.js"></script>
    
    <script src="JS/class_manager.js"></script>

    <script>

        function switchSidebarTab(tabName, btn) {

            document.querySelectorAll('.sidebar-tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            

            document.querySelectorAll('.sidebar-content-pane').forEach(p => p.classList.remove('active'));
            document.getElementById('sidebar-content-' + tabName).classList.add('active');
        }


        function showSection(secId, element) {
            document.querySelectorAll('.section-content-view').forEach(div => div.classList.remove('active'));
            document.querySelectorAll('.section-nav-item').forEach(li => li.classList.remove('active'));
            document.getElementById('section-view-' + secId).classList.add('active');
            element.classList.add('active');
        }


        let currentSectionId = null;
        let selectedLessonId = null;

        function openAddLessonModal(sectionId) {
            currentSectionId = sectionId;
            document.getElementById('addLessonModal').classList.add('active');
            loadMyLessons();
        }

        function closeModal() {
            document.getElementById('addLessonModal').classList.remove('active');
            selectedLessonId = null;
        }

        function loadMyLessons() {
            fetch('class_actions.php?action=get_my_lessons')
            .then(r => r.json())
            .then(data => {
                const list = document.getElementById('my-lessons-list');
                list.innerHTML = '';
                if (data.length === 0) {
                    list.innerHTML = '<div style="padding:15px;">У вас нет скрытых приватных уроков.</div>';
                    return;
                }
                data.forEach(l => {
                    const div = document.createElement('div');
                    div.className = 'modal-item';
                    div.innerHTML = `<span>${l.title}</span> <small style="color:#999">${l.subject}</small>`;
                    div.onclick = () => {
                        document.querySelectorAll('.modal-item').forEach(i => i.classList.remove('selected'));
                        div.classList.add('selected');
                        selectedLessonId = l.id;
                    };
                    list.appendChild(div);
                });
            });
        }

        function confirmAddLesson() {
            if (!selectedLessonId || !currentSectionId) {
                alert('Выберите урок из списка');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_lesson_to_section');
            formData.append('section_id', currentSectionId);
            formData.append('lesson_id', selectedLessonId);

            fetch('class_actions.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert('Ошибка: ' + data.message);
                }
            });
        }

        document.getElementById('lesson-search').addEventListener('keyup', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.modal-item').forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(term) ? 'flex' : 'none';
            });
        });
    </script>
</body>
<audio id="coin-sound" src="sounds/coin.mp3" preload="auto"></audio>
</html>
