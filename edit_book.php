<?php
session_start();
require_once 'db_connect.php';

// 1. Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. Получаем ID книги
if (!isset($_GET['id'])) {
    header("Location: my_books.php");
    exit;
}
$book_id = intval($_GET['id']);

// 3. Получаем данные пользователя (для Сайдбара)
$stmt = $conn->prepare("SELECT username, full_name, email, user_type, avatar, class, points, avatar_border_color, border_style, ef_premium, is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

// 4. Проверка прав (Учитель/Админ)
if (!$user || (strtolower(trim($user['user_type'])) !== 'teacher' && $user['is_admin'] != 1)) {
    echo "<script>alert('Доступ запрещен.'); window.location.href='profile.php';</script>";
    exit;
}

// 5. ПОЛУЧАЕМ ДАННЫЕ КНИГИ
$book_sql = "SELECT * FROM books WHERE id = $book_id AND user_id = $user_id";
$book_res = $conn->query($book_sql);

if ($book_res->num_rows == 0) {
    echo "<script>alert('Книга не найдена или доступ запрещен.'); window.location.href='my_books.php';</script>";
    exit;
}
$book = $book_res->fetch_assoc();
$current_langs = explode(', ', $book['languages']);

// 6. ОБРАБОТКА СОХРАНЕНИЯ
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
    
    $new_cover_name = $book['cover_image'];
    $new_pdf_name = $book['pdf_file'];

    // Новая обложка
    if (!empty($_FILES["cover_image"]["name"])) {
        $cover_name = time() . "_UPD_" . basename($_FILES["cover_image"]["name"]);
        if (move_uploaded_file($_FILES["cover_image"]["tmp_name"], $upload_dir_cover . $cover_name)) {
            if ($book['cover_image'] && file_exists($upload_dir_cover . $book['cover_image'])) {
                @unlink($upload_dir_cover . $book['cover_image']);
            }
            $new_cover_name = $cover_name;
        }
    }

    // Новый PDF
    if (!empty($_FILES["pdf_file"]["name"])) {
        $pdf_name = time() . "_UPD_" . basename($_FILES["pdf_file"]["name"]);
        if (move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $upload_dir_pdf . $pdf_name)) {
            if ($book['pdf_file'] && file_exists($upload_dir_pdf . $book['pdf_file'])) {
                @unlink($upload_dir_pdf . $book['pdf_file']);
            }
            $new_pdf_name = $pdf_name;
        }
    }

    // Update DB
    $update_stmt = $conn->prepare("UPDATE books SET title=?, short_description=?, full_description=?, class_level=?, subject=?, languages=?, cover_image=?, pdf_file=? WHERE id=? AND user_id=?");
    $update_stmt->bind_param("sssisssssi", $title, $short_desc, $full_desc, $class_level, $subject, $langs, $new_cover_name, $new_pdf_name, $book_id, $user_id);
    
    if ($update_stmt->execute()) {
        // ПЕРЕВОД: Добавляем data-i18n
        $msg = "<div class='alert success' data-i18n='alert_book_saved'><i class='fas fa-check-circle'></i> Изменения сохранены!</div>";
        $book['title'] = $title;
        $book['short_description'] = $short_desc;
        $book['full_description'] = $full_desc;
        $book['class_level'] = $class_level;
        $book['subject'] = $subject;
        $book['languages'] = $langs;
        $book['cover_image'] = $new_cover_name;
        $book['pdf_file'] = $new_pdf_name;
        $current_langs = explode(', ', $langs);
    } else {
        // ПЕРЕВОД: Добавляем data-i18n (сохраняем PHP-ошибку)
        $msg = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> <span data-i18n='alert_db_error'>Ошибка:</span> " . $conn->error . "</div>";
    }
}

// Данные для сайдбара (Полные)
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

// Рамки CSS
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
    <title data-i18n="edit_book_page_title">Редактировать книгу - EDU-Familiar</title>
    
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/settings.css">
    <link rel="stylesheet" href="CSS/online-book-styles.css">
    <link rel="stylesheet" href="CSS/header.css"> 
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* Стили для формы редактирования (как в add_book) */
        .page-header {
            background: linear-gradient(135deg, #ff9f5e 0%, #ff8c42 100%);
            border-radius: 16px; padding: 25px; color: white; margin-bottom: 25px;
            box-shadow: 0 10px 20px rgba(255, 140, 66, 0.2);
        }
        .page-header h2 { margin: 0; font-size: 22px; display: flex; align-items: center; gap: 10px; }
        
        .form-container {
            background: white; padding: 35px; border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;
            max-width: 900px; margin: 0 auto;
        }
        
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 10px; font-weight: 600; color: #334155; font-size: 14px; }
        .form-group label i { margin-right: 8px; color: var(--primary-color); width: 16px; text-align: center; }

        .form-control {
            width: 100%; padding: 14px 18px; border: 2px solid #e2e8f0; border-radius: 12px;
            font-size: 14px; background: #f8fafc; transition: all 0.3s ease; font-family: 'Poppins', sans-serif; color: var(--text-color);
        }
        .form-control:focus { outline: none; border-color: var(--secondary-color); background: white; box-shadow: 0 0 0 4px rgba(123, 97, 255, 0.1); }

        textarea.form-control { resize: vertical; min-height: 100px; }

        input[type="file"] { padding: 10px; background: white; }
        input[type="file"]::file-selector-button {
            margin-right: 20px; border: none; background: var(--secondary-color);
            padding: 10px 20px; border-radius: 8px; color: #fff; cursor: pointer;
            font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 13px; transition: background .2s;
        }
        input[type="file"]::file-selector-button:hover { background: #5f48e0; }

        .checkbox-group { display: flex; gap: 20px; padding: 5px 0; }
        .checkbox-wrapper { display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 8px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; transition: all 0.2s; }
        .checkbox-wrapper:hover { border-color: var(--primary-color); background: #fff7ed; }
        .checkbox-wrapper input { accent-color: var(--primary-color); width: 16px; height: 16px; cursor: pointer; }

        .alert { padding: 15px; border-radius: 12px; margin-bottom: 25px; font-size: 14px; display: flex; align-items: center; gap: 10px; animation: fadeIn 0.5s; }
        .alert.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .submit-btn {
            width: 100%; padding: 16px; background: linear-gradient(90deg, var(--primary-color), #ff9f5e);
            color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 700;
            cursor: pointer; transition: all 0.3s; margin-top: 10px; box-shadow: 0 4px 15px rgba(255, 140, 66, 0.3);
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255, 140, 66, 0.4); }

        .file-preview { display: flex; align-items: center; gap: 15px; margin-bottom: 10px; padding: 10px; background: #f1f5f9; border-radius: 8px; border: 1px dashed #cbd5e1; }
        .current-cover-thumb { width: 50px; height: 75px; object-fit: cover; border-radius: 4px; border: 1px solid #ccc; }
        .file-info-text { font-size: 13px; color: #64748b; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
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
                <button class="burger-menu"><span></span><span></span><span></span></button>
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
                <div class="user-icon <?php echo $main_avatar_class; ?>" 
                     style="background-image: url('img/avatar/<?php echo htmlspecialchars($avatar); ?>'); <?php echo $main_avatar_style; ?>">
                </div>

                <h3 id="profile-username" data-username="<?php echo htmlspecialchars($username); ?>">
                    <?php echo htmlspecialchars($username); ?>
                </h3>
                
                <p class="class-display"><span data-i18n="class_label">Класс:</span> <?php echo htmlspecialchars($class); ?></p>
                <p><span data-i18n="user_type_label">Тип:</span> <?php echo htmlspecialchars(ucfirst($user_type)); ?></p>
                <p><span data-i18n="email_label">Email:</span> <?php echo htmlspecialchars($email); ?></p>
                
                <div class="ef-points"><i class="fas fa-coins"></i> <span class="points-value"><?php echo htmlspecialchars($points); ?></span> EF</div>
                <div class="level-progress">
                    <span data-i18n="level_1">Уровень 1</span>
                    <div class="progress-bar"><div class="fill" style="width: 83%"></div></div>
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
            <?php endif; ?>

            <?php if ($is_admin == 1): ?>
                <div class="admin-buttons-container" style="margin-top: 10px;">
                    <a href="admin_frames.php" class="admin-panel-btn" data-i18n="control_server">Control Server</a>
                </div>
            <?php endif; ?>

            <button class="logout-btn" onclick="window.location.href='logout.php'" data-i18n="logout">Logout</button>
        </aside>

        <main class="main-content">
            
            <div class="page-header">
                <h2 data-i18n="edit_book_header"><i class="fas fa-pen-to-square"></i> Редактировать книгу</h2>
                <p style="margin-top: 5px; opacity: 0.9;" data-i18n="edit_book_subheader">Измените информацию или загрузите обновленные файлы.</p>
            </div>

            <?php echo $msg; ?>

            <div class="form-container">
                <form action="" method="POST" enctype="multipart/form-data">
                    
                    <div class="form-group">
                        <label data-i18n="book_title_label"><i class="fas fa-heading"></i> Название книги</label>
                        <input type="text" name="title" class="form-control" required 
                               value="<?php echo htmlspecialchars($book['title']); ?>">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label data-i18n="subject_label"><i class="fas fa-layer-group"></i> Предмет</label>
                            <select name="subject" class="form-control" required>
                                <?php
                                $subjects = ["Математика", "Алгебра", "Геометрия", "Физика", "Химия", "Биология", "Английский", "История", "Информатика", "Литература", "География"];
                                // TODO: Сами названия предметов тоже должны быть переведены (subject_math, subject_algebra...)
                                // Это требует более сложной логики PHP, чем просто data-i18n
                                foreach ($subjects as $sub) {
                                    $selected = ($book['subject'] == $sub) ? 'selected' : '';
                                    echo "<option value='$sub' $selected>$sub</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label data-i18n="class_label_form"><i class="fas fa-graduation-cap"></i> Класс</label>
                            <select name="class_level" class="form-control" required>
                                <?php for($i=1; $i<=11; $i++) {
                                    $selected = ($book['class_level'] == $i) ? 'selected' : '';
                                    echo "<option value='$i' $selected>$i Класс</option>";
                                } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label data-i18n="languages_label"><i class="fas fa-language"></i> Языки книги</label>
                        <div class="checkbox-group">
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="langs[]" value="RU" <?php echo in_array("RU", $current_langs) ? 'checked' : ''; ?>> <span data-i18n="lang_ru">Русский</span>
                            </label>
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="langs[]" value="KZ" <?php echo in_array("KZ", $current_langs) ? 'checked' : ''; ?>> <span data-i18n="lang_kz">Казахский</span>
                            </label>
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="langs[]" value="EN" <?php echo in_array("EN", $current_langs) ? 'checked' : ''; ?>> <span data-i18n="lang_en">Английский</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label data-i18n="short_desc_label"><i class="fas fa-align-left"></i> Краткое описание</label>
                        <textarea name="short_desc" class="form-control" rows="2" maxlength="150" required><?php echo htmlspecialchars($book['short_description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label data-i18n="full_desc_label"><i class="fas fa-align-justify"></i> Полное описание</label>
                        <textarea name="full_desc" class="form-control" rows="5" required><?php echo htmlspecialchars($book['full_description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-image"></i> <span data-i18n="cover_label">Обложка</span> (<span data-i18n="edit_book_leave_empty" style="font-weight:normal; opacity: 0.8;">Оставьте пустым, если не меняете</span>)</label>
                        
                        <div class="file-preview">
                            <img src="uploads/covers/<?php echo htmlspecialchars($book['cover_image']); ?>" class="current-cover-thumb">
                            <div class="file-info-text">
                                <strong data-i18n="current_cover_label">Текущая обложка:</strong> <?php echo htmlspecialchars($book['cover_image']); ?>
                            </div>
                        </div>

                        <input type="file" name="cover_image" class="form-control" accept="image/*">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-file-pdf"></i> <span data-i18n="file_label">Файл книги</span> (<span data-i18n="edit_book_leave_empty" style="font-weight:normal; opacity: 0.8;">Оставьте пустым, если не меняете</span>)</label>
                        
                        <div class="file-preview">
                            <i class="fas fa-file-pdf" style="font-size:24px; color:#ef4444;"></i>
                            <div class="file-info-text">
                                <strong data-i18n="current_file_label">Текущий файл:</strong> <?php echo htmlspecialchars($book['pdf_file']); ?>
                            </div>
                        </div>

                        <input type="file" name="pdf_file" class="form-control" accept="application/pdf">
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> <span data-i18n="save_button">Сохранить изменения</span>
                    </button>
                    
                    <a href="my_books.php" style="display:block; text-align:center; margin-top:15px; color:#64748b; text-decoration:none; font-size:14px;" data-i18n="cancel_button">
                        Отмена
                    </a>
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
<audio id="coin-sound" src="sounds/coin.mp3" preload="auto"></audio>
</html>