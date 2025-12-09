<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="title">Educational Platform</title>
    <link rel="stylesheet" href="CSS/styles.css">
    <link rel="stylesheet" href="CSS/header.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="page-home">
    <?php session_start(); ?>
    <header>
        <div class="header-content">
            <div class="site-title">EDU-Familiar.kz</div>
            <nav class="desktop-nav">
                <div class="dropdown">
                    <a href="#home" class="dropbtn" data-i18n="home">Home</a> 
                    <div class="dropdown-content">
                        <a href="#about" data-i18n="about">About</a>
                        <a href="#programs" data-i18n="programs">Programs</a>
                        <a href="#reviews" data-i18n="reviews">Reviews</a>
                        <a href="#team" data-i18n="team">Team</a>
                        <a href="#partners" data-i18n="partners">Partners</a>
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
            <a href="#home" data-i18n="home">Home</a>
            <a href="#about" data-i18n="about">About</a>
            <a href="#programs" data-i18n="programs">Programs</a>
            <a href="#reviews" data-i18n="reviews">Reviews</a>
            <a href="#team" data-i18n="team">Team</a>
            <a href="#partners" data-i18n="partners">Partners</a>
            <div class="mobile-actions">
                <div class="language-switcher">
                    <select id="language-select-mobile" class="lang-select">
                        <option value="en">EN</option>
                        <option value="kz">KZ</option>
                        <option value="ru">RU</option>
                    </select>
                </div>
                <?php
                if (isset($_SESSION['user_id'])) {
                    echo '<a href="profile.php" class="login-btn" data-i18n="profile">Profile</a>';
                } else {
                    echo '<a href="login.php" class="login-btn" data-i18n="login">Login</a>';
                }
                ?>
            </div>
        </nav>
    </header>

    <section id="home" class="banner">
        <div class="banner-content">
            <img src="img/Logo2.png" alt="EDU-Familiar.kz Logo" class="banner-logo">
            <h1 data-i18n="welcome">Welcome to EDU-Familiar.kz</h1>
            <p data-i18n="banner_desc">Empowering learners with cutting-edge skills through innovative online courses designed for the modern world.</p>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="register.php" class="register-btn" data-i18n="register_now">Register Now</a>
            <?php endif; ?>
        </div>
    </section>

    <section id="info-banner" class="info-banner">
        <div class="info-banner-content">
            <b data-i18n="info_banner_title" class="text-banner-inf">About EDU-Familiar.kz</b>
            <div class="info-buttons">
                <button class="info-btn" data-target="register-info" data-i18n="how_to_register">How to Register</button>
                <button class="info-btn" data-target="why-us-info" data-i18n="why_us">Why Us</button>
                <button class="info-btn" data-target="why-choose-info" data-i18n="why_choose_us">Why Choose Us</button>
            </div>
            <div class="info-content">
                <div id="register-info" class="info-item" style="display: none;">
                    <h3 data-i18n="how_to_register">How to Register</h3>
                    <p data-i18n="register_info_desc">To join our platform, click the "Register Now" button, fill in your details including name, email, and password, choose your region and user type, and submit the form. You'll receive a confirmation email to start your learning journey.</p>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="register.php" class="register-btn" data-i18n="register_now">Register Now</a>
                    <?php endif; ?>
                </div>
                <div id="why-us-info" class="info-item" style="display: none;">
                    <h3 data-i18n="why_us">Why Us</h3>
                    <p data-i18n="why_us_desc">EDU-Familiar.kz offers cutting-edge courses tailored to modern industry needs, with a focus on practical skills and accessibility for all learners in Kazakhstan and beyond.</p>
                </div>
                <div id="why-choose-info" class="info-item" style="display: none;">
                    <h3 data-i18n="why_choose_us">Why Choose Us</h3>
                    <p data-i18n="why_choose_desc">Our platform combines expert instruction, flexible learning options, and dedicated career support to help you succeed in the digital age.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="about" class="container about">
        <h2 data-i18n="about_us">About Us</h2>
        <p data-i18n="about_desc">EDU-Familiar.kz is dedicated to providing high-quality education tailored to the needs of Kazakhstani students and professionals.</p>
        <div class="about-grid">
            <div class="card">
                <i class="fas fa-graduation-cap icon"></i>
                <h3 data-i18n="our_mission">Our Mission</h3>
                <p data-i18n="mission_desc">To empower learners with knowledge and skills for success in the digital age.</p>
            </div>
            <div class="card">
                <i class="fas fa-globe icon"></i>
                <h3 data-i18n="global_standards">Global Standards</h3>
                <p data-i18n="global_desc">Our courses meet international standards while addressing local needs.</p>
            </div>
            <div class="card">
                <i class="fas fa-users icon"></i>
                <h3 data-i18n="community">Community</h3>
                <p data-i18n="community_desc">Join a vibrant community of learners and educators.</p>
            </div>
        </div>
    </section>

       <section class="container cta-section">
        <div class="cta-content">
            <h2 data-i18n="cta_title">Start your learning today</h2>
            <p data-i18n="cta_desc">Join thousands of learners in Kazakhstan and beyond. Explore our cutting-edge courses and unlock your potential with EDU-Familiar.kz.</p>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="register.php" class="register-btn" data-i18n="register_now">Register Now</a>
            <?php endif; ?>
        </div>
    </section>

    <section id="programs" class="container programs">
        <h2 data-i18n="our_programs">Our Programs</h2>
        <p data-i18n="programs_desc">Explore our diverse range of courses designed for various skill levels and interests.</p>
        <div class="programs-grid">
            <div class="card">
                <img src="img/program1.jpg" alt="AI & Machine Learning" class="program-image">
                <h3 data-i18n="ai_ml">AI & Machine Learning</h3>
                <p data-i18n="ai_desc">Dive into the world of artificial intelligence and its applications.</p>
            </div>
            <div class="card">
                <img src="img/program2.jpg" alt="Data Science" class="program-image">
                <h3 data-i18n="data_science">Data Science</h3>
                <p data-i18n="data_desc">Learn to analyze and interpret complex data sets.</p>
            </div>
            <div class="card">
                <img src="img/program3.jpg" alt="Web Development" class="program-image">
                <h3 data-i18n="web_dev">Web Development</h3>
                <p data-i18n="web_desc">Build modern, responsive websites and applications.</p>
            </div>
        </div>
    </section>

    <section id="reviews" class="container reviews">
        <h2 data-i18n="student_reviews">Student Reviews</h2>
        <p data-i18n="reviews_desc">Hear what our students have to say about their experience with EDU-Familiar.kz.</p>
        <div class="reviews-grid">
            <div class="card">
                <img src="img/student1.jpg" alt="Student 1" class="review-image">
                <p data-i18n="review1">"The AI course transformed my career prospects!" - Aigerim K.</p>
            </div>
            <div class="card">
                <img src="img/student2.jpg" alt="Student 2" class="review-image">
                <p data-i18n="review2">"Excellent instructors and practical projects." - Yerlan B.</p>
            </div>
            <div class="card">
                <img src="img/student3.jpg" alt="Student 3" class="review-image">
                <p data-i18n="review3">"Flexible learning that fits my schedule." - Dina M.</p>
            </div>
        </div>
    </section>

    <section id="partners" class="container partners">
        <h2 data-i18n="our_partners">Our Partners</h2>
        <p data-i18n="partners_desc">We collaborate with leading organizations to bring you the best learning opportunities.</p>
        <div class="partners-grid">
            <div class="card">
                <img src="img/images (1).jpg" alt="«Торайғыров университеті» Жоғары колледжі" class="partner-image">
                <h3>«Торайғыров университеті» Жоғары колледжі</h3>
                <p data-i18n="partner_desc1">A leading university in Kazakhstan engaged in training qualified specialists and supporting scientific and educational projects.</p>
            </div>
            <div class="card">
                <img src="img/" alt="EduGlobal Logo" class="partner-image">
                <h3>EduGlobal</h3>
                <p data-i18n="partner_desc2">An international education network supporting our curriculum development.</p>
            </div>
            <div class="card">
                <img src="img/i (1).png" alt="Сәтбаев Университеті" class="partner-image">
                <h3>Сәтбаев Университеті</h3>
                <p data-i18n="partner_desc3">A leading technical university in Kazakhstan specializing in engineering, scientific, and innovative developments, supporting education and the technological advancement of the country.</p>
            </div>
        </div>
    </section>

    <section id="faq" class="container faq">
        <h2 data-i18n="faq">Frequently Asked Questions</h2>
        <p data-i18n="faq_desc">Find answers to common questions about our courses and platform.</p>
        <div class="faq-grid">
            <div class="card">
                <h3 data-i18n="faq1_question">How do I enroll?</h3>
                <p data-i18n="faq1_answer">Simply click "Register Now" and follow the steps to sign up for your desired course.</p>
            </div>
            <div class="card">
                <h3 data-i18n="faq2_question">Are the courses online?</h3>
                <p data-i18n="faq2_answer">Yes, all our courses are fully online, allowing you to learn at your own pace.</p>
            </div>
            <div class="card">
                <h3 data-i18n="faq3_question">Do you offer certificates?</h3>
                <p data-i18n="faq3_answer">Yes, you will receive a certificate of completion for each course you finish.</p>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3 data-i18n="about_edu">About EDU-Familiar.kz</h3>
                <p data-i18n="footer_about_desc">We are a leading educational platform in Kazakhstan, offering cutting-edge courses to prepare students for the future.</p>
            </div>
            <div class="footer-section">
                <h3 data-i18n="quick_links">Quick Links</h3>
                <a href="#programs" data-i18n="our_programs">Our Programs</a>
                <a href="#reviews" data-i18n="student_reviews">Student Reviews</a>
                <a href="#team" data-i18n="meet_the_team">Meet the Team</a>
                <a href="#partners" data-i18n="our_partners">Our Partners</a>
                <a href="#faq" data-i18n="faq">FAQ</a>
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

    <div class="scroll-to-top" onclick="scrollToTop()">↑</div>

    <script src="JS/script.js"></script>
    <script src="JS/language.js"></script>
</body>
</html>