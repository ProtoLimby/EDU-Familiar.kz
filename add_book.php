<?php
session_start();
require_once 'db_connect.php';

// 1. Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. Получаем полные данные пользователя (для Сайдбара и Прав)
$stmt = $conn->prepare("SELECT username, full_name, email, user_type, avatar, class, points, avatar_border_color, border_style, ef_premium, is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

// 3. Проверка прав (Teacher или Admin)
if (!$user || (strtolower($user['user_type']) !== 'teacher' && $user['is_admin'] != 1)) {
    echo "<script>alert('Доступ запрещен.'); window.location.href='profile.php';</script>";
    exit;
}

// Данные для сайдбара (переменные должны совпадать с profile.php)
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

// Патч класса
if (strpos($class, 'frame-') !== false || strpos($class, '.') === 0) { $class = ''; }

// 4. Загрузка рамок (для CSS)
$frames_for_css = [];
$all_frames_result = $conn->query("SELECT style_key, image_file FROM ef_premium_frames WHERE is_visible = 1");
if ($all_frames_result) {
    while($frame = $all_frames_result->fetch_assoc()) {
        $frames_for_css[] = $frame;
    }
}

// 5. ОБРАБОТКА ФОРМЫ
$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $short_desc = $_POST['short_desc'];
    $full_desc = $_POST['full_desc'];
    $class_level = $_POST['class_level'];
    $subject = $_POST['subject'];
    $langs = isset($_POST['langs']) ? implode(", ", $_POST['langs']) : "";

    $upload_dir_cover = 'uploads/covers/';
    $upload_dir_pdf = 'uploads/books/';
    
    if (!is_dir($upload_dir_cover)) mkdir($upload_dir_cover, 0777, true);
    if (!is_dir($upload_dir_pdf)) mkdir($upload_dir_pdf, 0777, true);

    $cover_name = time() . "_" . basename($_FILES["cover_image"]["name"]);
    $target_cover = $upload_dir_cover . $cover_name;
    
    $pdf_name = time() . "_" . basename($_FILES["pdf_file"]["name"]);
    $target_pdf = $upload_dir_pdf . $pdf_name;

    // Загрузка файлов
    $uploadOk = true;
    if (!move_uploaded_file($_FILES["cover_image"]["tmp_name"], $target_cover)) $uploadOk = false;
    if (!move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $target_pdf)) $uploadOk = false;

    if ($uploadOk) {
        $stmt = $conn->prepare("INSERT INTO books (user_id, title, short_description, full_description, class_level, subject, languages, cover_image, pdf_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssissss", $user_id, $title, $short_desc, $full_desc, $class_level, $subject, $langs, $cover_name, $pdf_name);
        
        if ($stmt->execute()) {
            // ПЕРЕВОД: Добавляем data-i18n
            $msg = "<div class='alert success' data-i18n='alert_book_published'><i class='fas fa-check-circle'></i> Книга успешно опубликована!</div>";
        } else {
            // ПЕРЕВОД: Добавляем data-i18n
            $msg = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> <span data-i18n='alert_db_error'>Ошибка БД:</span> " . $conn->error . "</div>";
        }
    } else {
        // ПЕРЕВОД: Добавляем data-i18n
        $msg = "<div class='alert error' data-i18n='alert_upload_error'><i class='fas fa-exclamation-circle'></i> Ошибка загрузки файлов.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="add_book_page_title">Добавить книгу - EDU-Familiar</title>

    <link rel="stylesheet" href="CSS/header.css"> 
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/settings.css">
    
    <style>
        /* Заголовок страницы */
        .page-header {
            background: linear-gradient(135deg, #ff9f5e 0%, #ff8c42 100%);
            border-radius: 16px;
            padding: 25px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 10px 20px rgba(255, 140, 66, 0.2);
        }
        .page-header h2 { margin: 0; font-size: 22px; display: flex; align-items: center; gap: 10px; }
        
        /* Контейнер формы */
        .form-container {
            background: white;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid #f1f5f9;
        }
        
        .form-group { margin-bottom: 25px; }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }
        
        .form-group label i { margin-right: 8px; color: var(--secondary-color); width: 16px; text-align: center; }

        /* Стили текстовых полей */
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            background: #f8fafc;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            color: var(--text-color);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--secondary-color);
            background: white;
            box-shadow: 0 0 0 4px rgba(123, 97, 255, 0.1);
        }

        textarea.form-control { resize: vertical; min-height: 100px; }

        /* === ДИЗАЙН КНОПКИ ЗАГРУЗКИ ФАЙЛА (Input File) === */
        input[type="file"] {
            padding: 10px;
            background: white;
        }
        input[type="file"]::file-selector-button {
            margin-right: 20px;
            border: none;
            background: var(--secondary-color);
            padding: 10px 20px;
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            transition: background .2s ease-in-out;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 13px;
        }
        input[type="file"]::file-selector-button:hover {
            background: #5f48e0;
        }

        /* Чекбоксы */
        .checkbox-group {
            display: flex; gap: 20px;
            padding: 5px 0;
        }
        .checkbox-wrapper {
            display: flex; align-items: center; gap: 8px;
            cursor: pointer;
            padding: 8px 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .checkbox-wrapper:hover { border-color: var(--primary-color); background: #fff7ed; }
        .checkbox-wrapper input { accent-color: var(--primary-color); width: 16px; height: 16px; cursor: pointer; }

        /* Алерт сообщения */
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 25px; font-size: 14px; display: flex; align-items: center; gap: 10px; animation: fadeIn 0.5s; }
        .alert.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Кнопка отправки */
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(90deg, var(--primary-color), #ff9f5e);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px; font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(255, 140, 66, 0.3);
        }
        .submit-btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 20px rgba(255, 140, 66, 0.4); 
        }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <?php
    // Генерация CSS для рамок
    if (!empty($frames_for_css)) {
        echo "<style id=\"dynamic-frame-styles\">\n";
        $after_selectors = [];
        foreach ($frames_for_css as $frame) $after_selectors[] = '.border-' . htmlspecialchars($frame['style_key']) . '::after';
        echo implode(",\n", $after_selectors) . " { content: ''; position: absolute; top: -2px; left: -2px; right: -2px; bottom: -2px; width: auto; height: auto; background-size: cover; background-position: center; background-repeat: no-repeat; pointer-events: none; }\n";
        foreach ($frames_for_css as $frame) {
            echo ".border-" . htmlspecialchars($frame['style_key']) . "::after { background-image: url('frames/" . htmlspecialchars($frame['image_file']) . "'); }\n";
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


    <section class="profile-container">
        
        <aside class="sidebar">
            <div class="user-info">
                <?php
                    // --- ЛОГИКА АВАТАРА (ТОЧНАЯ КОПИЯ) ---
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
            
            <div class="page-header">
                <h2 data-i18n="add_book_header"><i class="fas fa-plus-circle"></i> Добавить новую книгу</h2>
                <p style="margin-top: 5px; opacity: 0.9;" data-i18n="add_book_subheader">Заполните форму, чтобы опубликовать материал в библиотеке.</p>
            </div>

            <?php echo $msg; ?>

            <div class="form-container">
                <form action="" method="POST" enctype="multipart/form-data">
                    
                    <div class="form-group">
                        <label data-i18n="book_title_label"><i class="fas fa-heading"></i> Название книги</label>
                        <input type="text" name="title" class="form-control" required data-i18n-placeholder="book_title_placeholder" placeholder="Например: Физика 7 класс. Механика">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label data-i18n="subject_label"><i class="fas fa-layer-group"></i> Предмет</label>
                            <select name="subject" class="form-control" required>
                                <option value="" disabled selected data-i18n="select_subject_placeholder">Выберите предмет</option>
                                <option value="Русский язык" data-i18n="subject_russian">Русский язык</option>
                                <option value="Русская литература" data-i18n="subject_russian_lit">Русская литература</option>
                                <option value="Русский язык и литература" data-i18n="subject_russian_lang_lit">Русский язык и литература</option>
                                <option value="Казахский язык" data-i18n="subject_kazakh">Казахский язык</option>
                                <option value="Казахский язык и литература" data-i18n="subject_kazakh_lang_lit">Казахский язык и литература</option>
                                <option value="Английский язык" data-i18n="subject_english">Английский язык</option>
                                <option value="Математика" data-i18n="subject_math">Математика</option>
                                <option value="Алгебра" data-i18n="subject_algebra">Алгебра</option>
                                <option value="Геометрия" data-i18n="subject_geometry">Геометрия</option>
                                <option value="Физика" data-i18n="subject_physics">Физика</option>
                                <option value="Химия" data-i18n="subject_chemistry">Химия</option>
                                <option value="Биология" data-i18n="subject_biology">Биология</option>
                                <option value="Естествознание" data-i18n="subject_natural_science">Естествознание</option>
                                <option value="История" data-i18n="subject_history">История</option>
                                <option value="Всемирная История" data-i18n="subject_world_history">Всемирная История</option>
                                <option value="История Казахстана" data-i18n="subject_kazakh_history">История Казахстана</option>
                                <option value="География" data-i18n="subject_geography">География</option>
                                <option value="Глобальные компетенции" data-i18n="subject_global_comp">Глобальные компетенции</option>
                                <option value="Основы права" data-i18n="subject_law_basics">Основы права</option>
                                <option value="Самопознание" data-i18n="subject_self_knowledge">Самопознание</option>
                                <option value="Трудовое обучение" data-i18n="subject_labor_training">Трудовое обучение</option>
                                <option value="Изобразительное искусство" data-i18n="subject_art">Изобразительное искусство</option>
                                <option value="Музыка" data-i18n="subject_music">Музыка</option>
                                <option value="Художественный труд" data-i18n="subject_art_labor">Художественный труд</option>
                                <option value="Физическая культура" data-i18n="subject_pe">Физическая культура</option>
                                <option value="Начальная военная и технологическая подготовка" data-i18n="subject_military_training">
                                    Начальная военная и технологическая подготовка
                                </option>
                                <option value="Информатика" data-i18n="subject_informatics">Информатика</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label data-i18n="class_label_form"><i class="fas fa-graduation-cap"></i> Класс</label>
                            <select name="class_level" class="form-control" required>
                                <?php for($i=1; $i<=11; $i++) echo "<option value='$i'>$i Класс</option>"; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label data-i18n="languages_label"><i class="fas fa-language"></i> Языки книги</label>
                        <div class="checkbox-group">
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="langs[]" value="RU" checked> <span data-i18n="lang_ru">Русский</span>
                            </label>
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="langs[]" value="KZ"> <span data-i18n="lang_kz">Казахский</span>
                            </label>
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="langs[]" value="EN"> <span data-i18n="lang_en">Английский</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label data-i18n="short_desc_label"><i class="fas fa-align-left"></i> Краткое описание (для карточки)</label>
                        <textarea name="short_desc" class="form-control" rows="2" maxlength="150" required data-i18n-placeholder="short_desc_placeholder" placeholder="Максимум 150 символов..."></textarea>
                    </div>

                    <div class="form-group">
                        <label data-i18n="full_desc_label"><i class="fas fa-align-justify"></i> Полное описание</label>
                        <textarea name="full_desc" class="form-control" rows="5" required data-i18n-placeholder="full_desc_placeholder" placeholder="Подробное описание учебного материала..."></textarea>
                    </div>

                    <div class="form-group">
                        <label data-i18n="cover_label"><i class="fas fa-image"></i> Обложка (JPG, PNG)</label>
                        <input type="file" name="cover_image" class="form-control" accept="image/*" required>
                        <small style="color:#94a3b8; margin-top:5px; display:block;" data-i18n="cover_desc">Рекомендуемый размер: 300x450px (Вертикальный)</small>
                    </div>

                    <div class="form-group">
                        <label data-i18n="file_label"><i class="fas fa-file-pdf"></i> Файл книги (PDF)</label>
                        <input type="file" name="pdf_file" class="form-control" accept="application/pdf" required>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> <span data-i18n="publish_button">Опубликовать книгу</span>
                    </button>
                </form>
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