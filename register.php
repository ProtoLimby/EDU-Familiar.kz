<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $region = $_POST['region'];
    $userType = $_POST['userType'];

    if ($password !== $confirmPassword) {
        $error = "Пароли не совпадают!";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (email, password, region, user_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $email, $hashedPassword, $region, $userType);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id; // Получаем ID только что созданного пользователя
            $username = "Guest_" . $user_id; // Генерируем никнейм Guest_(номер аккаунта)
            $avatar = "Def_Avatar.jpg"; // Дефолтная аватарка

            // Обновляем пользователя, добавляя никнейм и аватарку
            $updateStmt = $conn->prepare("UPDATE users SET username = ?, avatar = ? WHERE id = ?");
            $updateStmt->bind_param("ssi", $username, $avatar, $user_id);
            $updateStmt->execute();
            $updateStmt->close();

            // Автоматическая авторизация
            session_start();
            $_SESSION['user_id'] = $user_id;

            header("Location: profile.php"); // Перенаправляем на profile.php
            exit;
        } else {
            $error = "Ошибка регистрации: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="register_title">Register - EDU-Familiar.kz</title>
    <link rel="stylesheet" href="CSS/auth-styles.css">
    <link rel="stylesheet" href="CSS/header.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                <a href="best-students.html" data-i18n="best_students">Best Students</a> 
                <a href="online-book.html" data-i18n="online_book">Online Book</a>
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
                <a href="login.php" class="login-btn" data-i18n="login">Login</a>
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
                <a href="login.php" class="login-btn" data-i18n="login">Login</a>
            </div>
        </nav>
    </header>

    <section class="container register-form">
        <h2>Register</h2>
        <?php if (!empty($error)): ?>
            <p style="color:red; text-align:center;"><?= $error ?></p>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required class="text-size">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required class="text-size">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirmPassword" required class="text-size">
            </div>
            <div class="form-group">
                <label>Region of Residence</label>
                <select name="region" required class="text-size">
                    <option value="">Select Region</option>
                    <option value="pavlodar">Павлодарская область</option>
                    <option value="astana">Астана (город)</option>
                    <option value="almaty">Алматы (город)</option>
                    <option value="karaganda">Карагандинская область</option>
                </select>
            </div>
            <div class="form-group">
                <label>User Type</label>
                <select name="userType" required class="text-size">
                    <option value="">Select Type</option>
                    <option value="student">Ученик/ца</option>
                    <option value="teacher">Учитель/Преподаватель</option>
                    <option value="university-student">Студент</option>
                </select>
            </div>
            <button type="submit" class="register-btn text-size">Register Now</button>
        </form>
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

    <script src="JS/auth-script.js"></script>
    <script src="JS/language.js"></script>
</body>
</html>