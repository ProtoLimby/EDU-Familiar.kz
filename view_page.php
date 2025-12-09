<?php
session_start();
require_once 'db_connect.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$start = isset($_GET['start']) ? intval($_GET['start']) : 0;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

$pageData = null;
$lesson = [];
$server_time_remaining = null; 
$timer_minutes = 0;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $lesson = $result->fetch_assoc();

    if ($lesson) {
        // Проверка прав доступа при старте урока
        if ($start === 1 && $user_id > 0) {
            $max_attempts = intval($lesson['max_attempts']);
            
            // Считаем сколько раз пользователь уже прошел урок
            $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM lesson_completions WHERE user_id = ? AND lesson_id = ?");
            $count_stmt->bind_param("ii", $user_id, $id);
            $count_stmt->execute();
            $attempts_done = $count_stmt->get_result()->fetch_assoc()['cnt'];
            
            // --- ИСПРАВЛЕНИЕ ЗДЕСЬ ---
            // Проверяем лимит ТОЛЬКО если max_attempts > 0.
            // Если max_attempts равно 0 (безлимит), условие вернет false и пропустит блокировку.
            if ($max_attempts > 0 && $attempts_done >= $max_attempts) {
                header("Location: lesson_details.php?id=$id&locked=1");
                exit;
            }
        }

        $pageData = $lesson['content_json'];
        
        // Логика таймера
        if ($start === 1 && $pageData) {
            $blocks = json_decode($pageData, true);
            if (is_array($blocks)) {
                foreach ($blocks as $section) {
                    if (!empty($section['columns'])) {
                        foreach ($section['columns'] as $col) {
                            foreach ($col as $block) {
                                if ($block['type'] === 'timer' && !empty($block['content']['minutes'])) {
                                    $timer_minutes = intval($block['content']['minutes']);
                                    break 3; 
                                }
                            }
                        }
                    }
                }
            }

            if ($timer_minutes > 0) {
                $session_key = 'lesson_start_time_' . $id . '_' . $user_id;
                if (!isset($_SESSION[$session_key])) {
                    $_SESSION[$session_key] = time();
                }
                $finish_time = $_SESSION[$session_key] + ($timer_minutes * 60);
                $server_time_remaining = $finish_time - time();
                if ($server_time_remaining < 0) {
                    $server_time_remaining = 0;
                }
            }
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson['title'] ?? 'Просмотр урока'); ?> - EDU-Familiar.kz</title>
    
    <link rel="stylesheet" href="CSS/profile-styles.css">
    <link rel="stylesheet" href="CSS/settings.css">
    <link rel="stylesheet" href="CSS/header.css">
    <link rel="stylesheet" href="CSS/view_page.css"> 
    <link rel="stylesheet" href="CSS/lesson-view-styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    
    <style>
        .generated-cover-view {
            width: 100%; height: 350px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 80px; position: relative; overflow: hidden;
            text-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .generated-cover-view::after {
            content: ''; position: absolute; top: -50%; right: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            pointer-events: none;
        }
        /* Статистика в модалке */
        .stat-box.xp {
            background: linear-gradient(135deg, #e0e7ff, #fff);
            border-color: #c7d2fe;
        }
        .stat-box.xp .stat-value {
            color: #4f46e5;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .theme-math { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .theme-science { background: linear-gradient(135deg, #10b981, #059669); }
        .theme-physics { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .theme-history { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .theme-lang { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .theme-it { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
        .theme-art { background: linear-gradient(135deg, #ec4899, #db2777); }
        .theme-default { background: linear-gradient(135deg, #64748b, #475569); }
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
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
        <nav class="mobile-nav">
            <a href="index.php" data-i18n="home">Home</a>
        </nav>
    </header>

    <div id="sticky-timer" class="sticky-timer">
        <i class="fas fa-clock"></i>
        <span id="timer-display">00:00</span>
    </div>

    <?php if (!$lesson): ?>
        <div style="margin: 150px auto; max-width: 600px; text-align: center; padding: 40px;">
            <h2 style="font-size: 24px; color: #64748b; margin-bottom: 20px;">Урок не найден</h2>
            <a href="training.php" class="settings-btn" style="background: var(--primary-color);">Вернуться в каталог</a>
        </div>
    <?php else: ?>
        
        <div class="lesson-wrapper <?php echo $start === 1 ? 'active-mode' : ''; ?>">
            
            <div class="lesson-content-area">
                
                <?php if ($start === 0): ?>
                    <div class="preview-card">
                        <div class="preview-image">
                            <?php 
                                $cover = $lesson['cover_image'];
                                if (!empty($cover) && $cover !== 'default_lesson.jpg') {
                                    $src = (strpos($cover, 'data:image') === 0) ? $cover : "uploads/lessons/covers/" . htmlspecialchars($cover);
                                    echo '<img src="' . $src . '" alt="Cover">';
                                    echo '<div class="overlay"></div>';
                                } else {
                                    $subj = mb_strtolower($lesson['subject']);
                                    $icon = 'fa-book'; $theme = 'theme-default';
                                    if (strpos($subj, 'математ') !== false) { $icon = 'fa-calculator'; $theme = 'theme-math'; }
                                    elseif (strpos($subj, 'физик') !== false) { $icon = 'fa-atom'; $theme = 'theme-physics'; }
                                    elseif (strpos($subj, 'хим') !== false || strpos($subj, 'биолог') !== false) { $icon = 'fa-flask'; $theme = 'theme-science'; }
                                    elseif (strpos($subj, 'истор') !== false) { $icon = 'fa-globe-africa'; $theme = 'theme-history'; }
                                    elseif (strpos($subj, 'язык') !== false || strpos($subj, 'литерат') !== false) { $icon = 'fa-feather-alt'; $theme = 'theme-lang'; }
                                    elseif (strpos($subj, 'информ') !== false) { $icon = 'fa-laptop-code'; $theme = 'theme-it'; }
                                    elseif (strpos($subj, 'искусств') !== false) { $icon = 'fa-palette'; $theme = 'theme-art'; }
                                    echo '<div class="generated-cover-view ' . $theme . '"><i class="fas ' . $icon . '"></i></div>';
                                }
                            ?>
                        </div>
                        
                        <div class="preview-info">
                            <div class="tags">
                                <span class="tag subject"><?php echo htmlspecialchars($lesson['subject']); ?></span>
                                <span class="tag grade"><?php echo $lesson['grade']; ?> Класс</span>
                            </div>
                            <h1><?php echo htmlspecialchars($lesson['title']); ?></h1>
                            <p class="description">
                                <?php echo !empty($lesson['full_description']) ? nl2br(htmlspecialchars($lesson['full_description'])) : 'Нажмите "Начать урок", чтобы приступить к выполнению заданий.'; ?>
                            </p>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="back-link">
                        <a href="lesson_details.php?id=<?php echo $id; ?>"><i class="fas fa-chevron-left"></i> Вернуться к описанию</a>
                    </div>
                    <div id="page-renderer"></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="result-modal-backdrop" id="resultBackdrop"></div>
    <div class="result-modal" id="resultModal">
        <div class="result-icon-box" id="resultIconBox">
            <i class="fas fa-trophy" id="resultIcon"></i>
        </div>
        <h3 id="resultTitle">Урок завершен!</h3>
        <p class="result-subtitle" id="resultSubtitle">Вот ваши результаты</p>
        
        <div class="result-stats-grid">
            <div class="stat-box">
                <span class="stat-label">Результат</span>
                <span class="stat-value" id="resultPercent">0%</span>
            </div>
            <div class="stat-box">
                <span class="stat-label">Баллы</span>
                <span class="stat-value" id="resultScore">0/0</span>
            </div>
            
            <div class="stat-box coins" id="coinStat" style="display: none;">
                <span class="stat-label">Награда (EF)</span>
                <span class="stat-value"><i class="fas fa-coins"></i> +<span id="resultCoins">0</span></span>
            </div>

            <div class="stat-box xp" id="xpStat" style="display: none;">
                <span class="stat-label">Опыт (XP)</span>
                <span class="stat-value"><i class="fas fa-star"></i> +<span id="resultXP">0</span></span>
            </div>
        </div>

        <button class="result-action-btn" onclick="window.location.href='training.php'">Вернуться в каталог</button>
    </div>

    <div class="lightbox-backdrop" id="lightbox" onclick="closeLightbox()">
        <img id="lightbox-img" class="lightbox-img" src="" alt="Fullscreen">
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3 data-i18n="about_edu">About EDU-Familiar.kz</h3>
                <p data-i18n="footer_about_desc">We are a leading educational platform in Kazakhstan.</p>
            </div>
            </div>
        <div class="footer-bottom">
            <p data-i18n="copyright">2025 EDU-Familiar.kz. All rights reserved.</p>
        </div>
    </footer>

    <script src="JS/profile-script.js"></script>
    <script src="JS/language.js"></script>
    <script src="JS/coins.js"></script>

    <?php if ($start === 1 && $pageData): ?>
    <script>
        const rawData = <?php echo $pageData ?: '[]'; ?>;
        const container = document.getElementById('page-renderer');
        const lessonId = <?php echo $id; ?>;
        const userId = <?php echo $user_id; ?>;
        
        const serverTimeRemaining = <?php echo $server_time_remaining !== null ? $server_time_remaining : 'null'; ?>;
        const startTimeLocal = Date.now(); 
        
        const stickyTimer = document.getElementById('sticky-timer');
        const timerDisplay = document.getElementById('timer-display');
        
        let timerInterval = null;
        let totalLessonTime = 0; 

        if (rawData && rawData.length > 0) {
            findTotalTime(rawData);
            checkForTimer(rawData);
            renderViewer(rawData);
            initSortables();

            const finishDiv = document.createElement('div');
            finishDiv.className = 'finish-section';
            finishDiv.innerHTML = `
                <div style="font-size: 50px; margin-bottom: 10px;">🎉</div>
                <h3 class="finish-title">Поздравляем, вы дошли до конца!</h3>
                <p class="finish-subtitle">Убедитесь, что вы ответили на все вопросы. Нажмите кнопку ниже, чтобы зафиксировать результат.</p>
                <button id="finish-lesson-btn" class="finish-btn" onclick="finishLesson(false)">
                    <i class="fas fa-check-circle"></i> Завершить Урок
                </button>
            `;
            container.appendChild(finishDiv);
        }

        window.moveGallery = function(btn, direction) {
            const wrapper = btn.closest('.viewer-gallery-slider');
            const track = wrapper.querySelector('.viewer-gallery-track');
            const slides = wrapper.querySelectorAll('.viewer-gallery-slide');
            if(slides.length <= 1) return;

            let currentIndex = parseInt(wrapper.dataset.index || 0);
            currentIndex += direction;

            if (currentIndex < 0) currentIndex = 0;
            if (currentIndex >= slides.length) currentIndex = slides.length - 1;

            wrapper.dataset.index = currentIndex;
            track.style.transform = `translateX(-${currentIndex * 100}%)`;
        };

        function findTotalTime(data) {
            data.forEach(sec => {
                if(sec.columns) {
                    sec.columns.forEach(col => {
                        col.forEach(block => {
                            if(block.type === 'timer' && block.content && block.content.minutes) {
                                totalLessonTime = parseInt(block.content.minutes);
                            }
                        });
                    });
                }
            });
        }

        function shuffleArray(array) {
            for (let i = array.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [array[i], array[j]] = [array[j], array[i]];
            }
            return array;
        }

        function initSortables() {
            document.querySelectorAll('.sequence-list').forEach(list => {
                new Sortable(list, { animation: 150, ghostClass: 'sortable-ghost' });
            });
            document.querySelectorAll('.matching-values').forEach(list => {
                new Sortable(list, { animation: 150, ghostClass: 'sortable-ghost' });
            });
        }

        window.openLightbox = function(src) {
            const lb = document.getElementById('lightbox');
            const img = document.getElementById('lightbox-img');
            img.src = src;
            lb.classList.add('active');
        }
        window.closeLightbox = function() {
            document.getElementById('lightbox').classList.remove('active');
        }

        function validateLesson() {
            let isValid = true;
            const requiredBlocks = document.querySelectorAll('[data-mandatory="true"]');
            requiredBlocks.forEach(block => {
                let answered = false;
                const inputs = block.querySelectorAll('input[type="radio"], input[type="checkbox"]');
                if (inputs.length > 0) { inputs.forEach(inp => { if(inp.checked) answered = true; }); }
                
                const textInputs = block.querySelectorAll('input[type="text"], textarea');
                if (textInputs.length > 0) { textInputs.forEach(inp => { if(inp.value.trim() !== '') answered = true; }); }

                if (block.classList.contains('file-submission-block')) {
                    const uploadArea = block.querySelector('.file-submission-area');
                    if (uploadArea && uploadArea.classList.contains('uploaded')) answered = true;
                }
                
                if (block.querySelector('.sequence-list') || block.querySelector('.matching-container-view')) {
                    answered = true;
                }

                if (block.querySelector('.contact-form-view')) {
                    const reqInputs = block.querySelectorAll('input, textarea');
                    let formFilled = false;
                    reqInputs.forEach(inp => { if(inp.value.trim() !== '') formFilled = true; });
                    if(formFilled) answered = true;
                }

                if (!answered) {
                    isValid = false;
                    const card = block.querySelector('.quiz-card, .file-submission-area, .contact-form-view');
                    if(card) {
                        card.classList.add('validation-error');
                        setTimeout(() => card.classList.remove('validation-error'), 1000);
                    }
                }
            });
            return isValid;
        }

        // === ИЗМЕНЕНИЕ: СБОР ДАННЫХ ДЛЯ ОТЧЕТА ===
        function calculateResults() {
            let totalScore = 0;
            let earnedScore = 0;
            const viewerBlocks = document.querySelectorAll('.viewer-block');
            let flatBlocks = [];
            
            // Собираем логи (answers log)
            let answersLog = [];

            rawData.forEach(sec => {
                if(sec.columns) {
                    sec.columns.forEach(col => {
                        col.forEach(b => {
                            if(b.type !== 'timer') flatBlocks.push(b);
                        });
                    });
                }
            });

            flatBlocks.forEach((b, idx) => {
                const domBlock = viewerBlocks[idx];
                
                // Проверяем, является ли блок вопросом
                if (['question_mcq', 'question_checkbox', 'question_sequence', 'question_matching', 'question_text', 'question_essay', 'file_submission'].includes(b.type)) {
                    
                    let isCorrect = null; // null = на проверке (эссе, файл), true/false для тестов
                    let userResponse = '';
                    
                    // Для автоматической проверки
                    if (['question_mcq', 'question_checkbox', 'question_sequence', 'question_matching'].includes(b.type)) {
                        totalScore += 1;
                        isCorrect = false; // По умолчанию
                    }

                    if (domBlock) {
                        // 1. MCQ (Один вариант)
                        if (b.type === 'question_mcq') {
                            const selected = domBlock.querySelector('input:checked');
                            if (selected) {
                                userResponse = selected.closest('.quiz-option').querySelector('.opt-text').innerText;
                                const selectedIdx = b.content.options.indexOf(userResponse);
                                if (b.content.correctAnswers && b.content.correctAnswers.includes(selectedIdx)) {
                                    earnedScore += 1;
                                    isCorrect = true;
                                }
                            } else {
                                userResponse = '(Нет ответа)';
                            }
                        } 
                        // 2. CHECKBOX (Несколько вариантов)
                        else if (b.type === 'question_checkbox') {
                            const checkedInputs = domBlock.querySelectorAll('input:checked');
                            let userSelectedTexts = [];
                            let allCorrect = true;
                            let correctCount = 0;
                            
                            checkedInputs.forEach(inp => {
                                const txt = inp.closest('.quiz-option').querySelector('.opt-text').innerText;
                                userSelectedTexts.push(txt);
                                const idx = b.content.options.indexOf(txt);
                                if (!b.content.correctAnswers.includes(idx)) allCorrect = false;
                                else correctCount++;
                            });
                            
                            userResponse = userSelectedTexts.join(', ');
                            
                            if (correctCount !== b.content.correctAnswers.length) allCorrect = false;
                            if (allCorrect && b.content.correctAnswers.length > 0) {
                                earnedScore += 1;
                                isCorrect = true;
                            }
                        } 
                        // 3. SEQUENCE (Последовательность)
                        else if (b.type === 'question_sequence') {
                            const currentOrder = Array.from(domBlock.querySelectorAll('.seq-text')).map(el => el.innerText);
                            userResponse = currentOrder.join(' -> ');
                            
                            if (JSON.stringify(currentOrder) === JSON.stringify(b.content.options)) {
                                earnedScore += 1;
                                isCorrect = true;
                            }
                        } 
                        // 4. MATCHING (Соответствие)
                        else if (b.type === 'question_matching') {
                            const keys = Array.from(domBlock.querySelectorAll('.match-key-item')).map(el => el.innerText);
                            const values = Array.from(domBlock.querySelectorAll('.match-value-item span')).map(el => el.innerText);
                            
                            let pairsStr = [];
                            let isMatchCorrect = true;
                            
                            for(let i=0; i<keys.length; i++) {
                                const key = keys[i];
                                const val = values[i];
                                pairsStr.push(`${key} = ${val}`);
                                
                                const originalPair = b.content.pairs.find(p => p.left === key);
                                if (!originalPair || originalPair.right !== val) {
                                    isMatchCorrect = false; 
                                }
                            }
                            userResponse = pairsStr.join('; ');
                            
                            if (isMatchCorrect && keys.length > 0) {
                                earnedScore += 1;
                                isCorrect = true;
                            }
                        }
                        // 5. TEXT / ESSAY (Текст)
                        else if (b.type === 'question_text' || b.type === 'question_essay') {
                            const input = domBlock.querySelector('input, textarea');
                            userResponse = input ? input.value : '';
                            isCorrect = null; // Ручная проверка учителем (пока не реализована, но логика верная)
                        }
                        // 6. FILE (Файл)
                        else if (b.type === 'file_submission') {
                            const input = domBlock.querySelector('input[type="file"]');
                            if (input && input.dataset.serverPath) {
                                userResponse = input.dataset.serverPath; // Путь на сервере
                            } else {
                                userResponse = 'Файл не загружен';
                            }
                            isCorrect = null; 
                        }
                    }

                    // Добавляем запись в лог ответов
                    answersLog.push({
                        question: b.content.question || 'Вопрос без заголовка',
                        type: b.type,
                        user_response: userResponse,
                        is_correct: isCorrect
                    });
                }
            });

            let timeSpent = 0;
            if (stickyTimer && stickyTimer.classList.contains('active')) {
                const text = timerDisplay.innerText;
                const [min, sec] = text.split(':').map(Number);
                const timeLeftSec = (min * 60) + sec;
                const totalSec = totalLessonTime * 60;
                timeSpent = totalSec - timeLeftSec;
                if (timeSpent < 0) timeSpent = 0;
            } else {
                timeSpent = Math.floor((Date.now() - startTimeLocal) / 1000);
            }

            let percent = 0;
            if (totalScore > 0) {
                percent = Math.round((earnedScore / totalScore) * 100);
            } else {
                percent = 100;
            }

            // ВОЗВРАЩАЕМ И answersLog ТОЖЕ
            return { 
                score: earnedScore, 
                max_score: totalScore, 
                percentage: percent, 
                time_spent: timeSpent,
                answers: answersLog 
            };
        }

        function finishLesson(isTimeout = false) {
            if(userId === 0) { alert("Вы не авторизованы!"); return; }
            if (!isTimeout && !validateLesson()) { alert("Пожалуйста, ответьте на все обязательные вопросы."); return; }

            const btn = document.getElementById('finish-lesson-btn');
            if(btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Считаем...'; }

            const stats = calculateResults();

            fetch('finish_lesson.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    lesson_id: lessonId,
                    score: stats.score,
                    max_score: stats.max_score,
                    percentage: stats.percentage,
                    time_spent: stats.time_spent,
                    answers: stats.answers // <--- ОТПРАВЛЯЕМ ОТВЕТЫ
                })
            })
            .then(async response => {
                const text = await response.text();
                try { return JSON.parse(text); } catch (e) { throw new Error("Server Error"); }
            })
            .then(data => {
                if(data.success) {
                    const percentEl = document.getElementById('resultPercent');
                    if(percentEl) percentEl.innerText = stats.percentage + '%';
                    document.getElementById('resultScore').innerText = stats.score + '/' + stats.max_score;
                    const iconBox = document.getElementById('resultIconBox');
                    const icon = document.getElementById('resultIcon');
                    const subtitle = document.getElementById('resultSubtitle');

                    if (stats.percentage >= 80) {
                        iconBox.className = 'result-icon-box success'; icon.className = 'fas fa-trophy';
                        subtitle.innerText = 'Отличная работа! Вы справились превосходно.';
                    } else if (stats.percentage >= 50) {
                        iconBox.className = 'result-icon-box average'; icon.className = 'fas fa-thumbs-up';
                        subtitle.innerText = 'Неплохо! Но есть куда расти.';
                    } else {
                        iconBox.className = 'result-icon-box'; iconBox.style.background = '#f1f5f9'; iconBox.style.color = '#64748b'; icon.className = 'fas fa-book-reader';
                        subtitle.innerText = 'Попробуйте пройти материал еще раз.';
                    }

                    if (data.coins_added > 0) {
                        document.getElementById('resultCoins').innerText = data.coins_added;
                        document.getElementById('coinStat').style.display = 'block';
                    } else {
                        document.getElementById('coinStat').style.display = 'none';
                    }

                    if (data.xp_added > 0) {
                        document.getElementById('resultXP').innerText = data.xp_added;
                        document.getElementById('xpStat').style.display = 'block';
                    } else {
                        document.getElementById('xpStat').style.display = 'none';
                    }

                    document.getElementById('resultModal').classList.add('active');
                    document.getElementById('resultBackdrop').classList.add('active');
                } else {
                    alert("Ошибка сервера: " + data.message);
                    if(btn) { btn.disabled = false; btn.innerText = "Завершить Урок"; }
                }
            })
            .catch(err => {
                console.error(err);
                alert("Ошибка: " + err.message);
                if(btn) { btn.disabled = false; btn.innerText = "Завершить Урок"; }
            });
        }

        function renderViewer(sectionsData) {
            sectionsData.forEach(secData => {
                const section = document.createElement('div');
                section.className = 'viewer-section'; 
                if(secData.styles?.backgroundColor && secData.styles.backgroundColor !== 'default') {
                    section.style.backgroundColor = secData.styles.backgroundColor;
                }
                const columnsContainer = document.createElement('div');
                columnsContainer.className = 'viewer-columns';
                if(secData.columns) {
                    secData.columns.forEach(colBlocks => {
                        const colDiv = document.createElement('div');
                        colDiv.className = 'viewer-col';
                        if(colBlocks) {
                            colBlocks.forEach(blockData => {
                                if(blockData.type === 'timer') return;
                                const block = createViewerBlock(blockData);
                                if(block) colDiv.appendChild(block);
                            });
                        }
                        columnsContainer.appendChild(colDiv);
                    });
                }
                section.appendChild(columnsContainer);
                container.appendChild(section);
            });
        }

        function createViewerBlock(data) {
            const block = document.createElement('div');
            block.className = 'viewer-block'; 
            const content = data.content;
            if(!content) return null;

            if(content.isMandatory) {
                block.setAttribute('data-mandatory', 'true');
            }

            let html = '';
            
            const align = data.styles?.align || 'left';
            const color = data.styles?.color !== 'default' ? data.styles.color : 'inherit';
            const fontFamily = data.styles?.fontFamily !== 'default' ? data.styles.fontFamily : 'inherit';
            const fontSize = data.styles?.fontSize !== 'default' ? data.styles.fontSize : 'inherit';

            const styleStr = `text-align: ${align}; color: ${color}; font-family: ${fontFamily}; font-size: ${fontSize};`;

            switch (data.type) {
                case 'heading': 
                    html = `<h2 style="${styleStr}">${content.text || ''}</h2>`; 
                    break;
                case 'text': 
                    html = `<div style="${styleStr} line-height: 1.8;">${content.text || ''}</div>`; 
                    break;
                case 'quote': 
                    html = `<blockquote style="border-left: 4px solid var(--primary-color); padding-left: 20px; font-style: italic; color: #555;">${content.text || ''}</blockquote>`; 
                    break;
                case 'separator': html = `<hr class="viewer-hr">`; break;
                case 'image_upload': case 'image_url':
                    if(content.src) { html = `<figure style="text-align:${data.styles?.align || 'center'}; margin:0;"><img src="${content.src}" style="width: ${data.styles?.width || '100%'}; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);"> ${content.caption ? `<figcaption style="color: #888; margin-top: 8px; font-size: 14px;">${content.caption}</figcaption>` : ''}</figure>`; }
                    break;
                case 'video':
                    if(content.src) { html = `<div style="position: relative; padding-bottom: 56.25%; height: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);"><iframe src="${content.src}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" frameborder="0" allowfullscreen></iframe></div>`; }
                    break;
                case 'audio_player':
                    if(content.src) { html = `<h4 style="margin-bottom:10px;">${content.title || 'Аудио'}</h4><audio controls src="${content.src}" class="viewer-audio-player"></audio>`; }
                    break;
                case 'file_download':
                    if(content.href) { html = `<a href="${content.href}" download="${content.fileName}" class="viewer-download-card"><i class="fas fa-file-download viewer-download-icon"></i><div><div style="font-weight:600; color:var(--text-color);">${content.title || 'Скачать файл'}</div><div style="font-size:13px; color:var(--text-light);">${content.fileName}</div></div></a>`; }
                    break;
                case 'table':
                    let tableHtml = content.html || '';
                    tableHtml = tableHtml.replace(/contenteditable="?true"?/gi, '');
                    if(tableHtml) { html = `<div class="viewer-table-wrapper"><table class="viewer-table">${tableHtml}</table></div>`; }
                    break;
                
                case 'button':
                    const btnBg = data.styles?.backgroundColor !== 'default' ? data.styles.backgroundColor : 'linear-gradient(90deg, #ff8c42, #ff9f5e)';
                    const btnColor = data.styles?.color !== 'default' ? data.styles.color : '#ffffff';
                    
                    html = `
                        <div style="text-align: ${data.styles?.align || 'left'}">
                            <a href="${content.url}" target="_blank" class="primary-btn" 
                               style="background: ${btnBg}; color: ${btnColor}; font-size: ${fontSize}; font-family: ${fontFamily}; display: inline-block; width: auto; padding: 12px 24px; border-radius: 25px;">
                               ${content.text || 'Кнопка'}
                            </a>
                        </div>
                    `;
                    break;
                
                case 'gallery':
                    if (content.images && content.images.length > 0) {
                        html = `
                        <div class="viewer-gallery-slider" data-index="0">
                            <div class="viewer-gallery-track">
                                ${content.images.map(src => `<div class="viewer-gallery-slide"><img src="${src}" onclick="openLightbox(this.src)"></div>`).join('')}
                            </div>
                            <button class="gallery-nav-btn prev" onclick="moveGallery(this, -1)">❮</button>
                            <button class="gallery-nav-btn next" onclick="moveGallery(this, 1)">❯</button>
                        </div>`;
                    }
                    break;

                case 'contact_form':
                    html = `
                        <div class="lesson-block contact-form-view" style="padding:20px; border:1px solid #e2e8f0; border-radius:12px; background:#fff;">
                            <h4 style="${styleStr}">${content.title || 'Форма обратной связи'}</h4>
                            <div style="margin-top:15px; display:grid; gap:10px;">
                                <input type="text" class="viewer-input contact-name" placeholder="Ваше Имя">
                                <input type="email" class="viewer-input contact-email" placeholder="Ваш Email">
                                <textarea class="viewer-textarea contact-message" rows="4" placeholder="Сообщение..."></textarea>
                            </div>
                        </div>
                    `;
                    break;

                case 'question_mcq': case 'question_checkbox':
                    const inputType = data.type === 'question_mcq' ? 'radio' : 'checkbox';
                    const qName = 'q_' + Math.random().toString(36).substr(2, 9);
                    const mandatory = content.isMandatory ? '<i class="fas fa-asterisk mandatory-star"></i>' : '';
                    let options = [...(content.options || [])];
                    shuffleArray(options);
                    html = `<div class="quiz-card"><div class="quiz-question"><i class="fas fa-question-circle"></i><span>${content.question || 'Вопрос'} ${mandatory}</span></div><div class="quiz-options">`;
                    options.forEach(opt => {
                        html += `<label class="quiz-option"><input type="${inputType}" name="${qName}"><div class="custom-check"></div><span class="opt-text">${opt}</span></label>`;
                    });
                    html += `</div></div>`;
                    break;

                case 'question_sequence':
                    const seqMandatory = content.isMandatory ? '<i class="fas fa-asterisk mandatory-star"></i>' : '';
                    let seqOptions = [...(content.options || [])];
                    shuffleArray(seqOptions);
                    html = `<div class="quiz-card"><div class="quiz-question"><i class="fas fa-sort-numeric-down"></i><span>${content.question || 'Расставьте в правильном порядке'} ${seqMandatory}</span></div><p style="font-size:13px; color:#64748b; margin-bottom:10px;">Перетащите элементы, чтобы изменить порядок:</p><ul class="sequence-list">`;
                    seqOptions.forEach((opt) => {
                        html += `<li class="sequence-item-view"><i class="fas fa-grip-lines drag-handle-view"></i><span class="seq-text">${opt}</span></li>`;
                    });
                    html += `</ul></div>`;
                    break;

                case 'question_matching':
                    const matchMandatory = content.isMandatory ? '<i class="fas fa-asterisk mandatory-star"></i>' : '';
                    html = `<div class="quiz-card"><div class="quiz-question"><i class="fas fa-random"></i><span>${content.question || 'Сопоставьте пары'} ${matchMandatory}</span></div>`;
                    if(content.pairs) {
                        let lefts = content.pairs.map(p => p.left);
                        let rights = content.pairs.map(p => p.right);
                        shuffleArray(rights); 
                        html += `<div class="matching-container-view"><div class="matching-keys">`;
                        lefts.forEach(l => { html += `<div class="match-key-item">${l}</div>`; });
                        html += `</div><ul class="matching-values">`;
                        rights.forEach(r => { html += `<li class="match-value-item"><i class="fas fa-grip-lines drag-handle-view"></i><span>${r}</span></li>`; });
                        html += `</ul></div>`;
                    }
                    html += `</div>`;
                    break;

                case 'question_text':
                    html = `<div class="quiz-card"><div class="quiz-question"><i class="fas fa-pen"></i> <span>${content.question || 'Вопрос'}</span></div><input type="text" class="viewer-input" placeholder="Ваш ответ..."></div>`;
                    break;
                case 'question_essay':
                    html = `<div class="quiz-card"><div class="quiz-question"><i class="fas fa-align-left"></i> <span>${content.question || 'Эссе'}</span></div><textarea class="viewer-textarea" rows="5" placeholder="Развернутый ответ..."></textarea></div>`;
                    break;

                case 'file_submission':
                    block.classList.add('file-submission-block');
                    const fileMandatory = content.isMandatory ? '<i class="fas fa-asterisk mandatory-star"></i>' : '';
                    const uniqueId = 'file_' + Math.random().toString(36).substr(2, 9);
                    html = `<div class="quiz-card"><div class="quiz-question"><i class="fas fa-file-upload"></i> <span>${content.question || 'Загрузите файл'} ${fileMandatory}</span></div><div class="file-submission-area" id="area_${uniqueId}"><label class="file-upload-label" for="${uniqueId}"><i class="fas fa-cloud-upload-alt"></i> Выбрать файл</label><input type="file" id="${uniqueId}" class="file-submission-input" onchange="uploadFile(this, '${uniqueId}')"><div class="file-status-text" id="status_${uniqueId}">Файл не выбран</div></div></div>`;
                    break;
            }
            block.innerHTML = html;
            return block;
        }

        function checkForTimer(data) {
            if (serverTimeRemaining !== null) {
                startTimer(serverTimeRemaining); 
            } else {
                let minutes = 0;
                data.forEach(sec => {
                    if(sec.columns) {
                        sec.columns.forEach(col => {
                            col.forEach(block => {
                                if(block.type === 'timer' && block.content && block.content.minutes) {
                                    minutes = parseInt(block.content.minutes);
                                }
                            });
                        });
                    }
                });
                if(minutes > 0) startTimer(minutes * 60);
            }
        }

        function startTimer(seconds) {
            let timeRemaining = seconds;
            stickyTimer.classList.add('active'); 
            stickyTimer.style.display = 'flex';
            updateTimerUI(timeRemaining);
            if (timeRemaining <= 0) { finishLesson(true); return; }
            timerInterval = setInterval(() => {
                timeRemaining--;
                updateTimerUI(timeRemaining);
                if(timeRemaining <= 60) stickyTimer.classList.add('urgent');
                if(timeRemaining <= 0) { clearInterval(timerInterval); finishLesson(true); }
            }, 1000);
        }

        function updateTimerUI(seconds) {
            if (seconds < 0) seconds = 0;
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            timerDisplay.innerText = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        }

        window.uploadFile = function(input, id) {
            const file = input.files[0];
            if(!file) return;
            const statusText = document.getElementById('status_' + id);
            const area = document.getElementById('area_' + id);
            statusText.innerText = "Загрузка...";
            statusText.className = "file-status-text";
            const formData = new FormData();
            formData.append('assignment_file', file);
            fetch('upload_assignment.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    statusText.innerText = "Успешно: " + file.name;
                    statusText.classList.add('success');
                    area.classList.add('uploaded');
                    input.dataset.serverPath = data.filepath; 
                } else {
                    statusText.innerText = "Ошибка: " + data.message;
                    statusText.classList.add('error');
                    input.value = ''; 
                }
            })
            .catch(err => {
                console.error(err);
                statusText.innerText = "Ошибка сети";
                statusText.classList.add('error');
            });
        };
    </script>
    <?php endif; ?>

</body>
</html>