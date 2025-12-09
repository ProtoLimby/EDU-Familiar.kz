
<?php
session_start();
require_once 'db_connect.php';

// 1. Проверка авторизации
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT user_type, is_admin FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if (!$u || (strtolower($u['user_type']) !== 'teacher' && $u['is_admin'] != 1)) {
    header("Location: profile.php"); exit;
}

$pageTitle = 'Новый урок';
if (isset($_GET['id'])) {
    $lid = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT title FROM lessons WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $lid, $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) $pageTitle = htmlspecialchars($res['title']) . ' - Редактор';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | Конструктор</title>
    
    <link rel="stylesheet" href="CSS/header.css">
    <link rel="stylesheet" href="CSS/editor.css"> 
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body { background-color: #f9fafc; margin: 0; padding-top: 80px; }
        .app-container { margin-top: 20px; height: calc(100vh - 120px); }
        #content-panel { top: 100px; height: calc(100vh - 140px); }
        header.editor-header { display: none; } 
        
        .meta-textarea { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 14px; resize: vertical; }
        .meta-textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(255, 140, 66, 0.1); }

        .lang-checkbox-group { display: flex; gap: 15px; flex-wrap: wrap; background: #f9fafc; padding: 10px; border: 2px solid var(--border-color); border-radius: 12px; }
        .lang-checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; font-weight: 500; color: var(--text-color); user-select: none; }
        .lang-checkbox-label input { accent-color: var(--secondary-color); width: 18px; height: 18px; }

        /* Новые стили для переключателей */
        .visibility-toggle { display: flex; gap: 15px; margin-bottom: 15px; }
        .vis-option { flex: 1; cursor: pointer; }
        .vis-option input { display: none; }
        .vis-card { 
            padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; text-align: center; color: #64748b; transition: 0.2s;
            display: flex; flex-direction: column; align-items: center; gap: 5px;
        }
        .vis-card i { font-size: 20px; }
        /* Исправлено name="privacy" */
        .vis-option input:checked + .vis-card { border-color: var(--primary-color); background: #fff7ed; color: var(--primary-color); font-weight: 600; }

        .hidden-toggle-label {
            display: flex; align-items: center; gap: 10px; font-size: 14px; color: #64748b; 
            margin-top: 10px; padding: 10px; background: #f1f5f9; border-radius: 8px; cursor: pointer;
        }
        .hidden-toggle-label input { accent-color: #ef4444; width: 18px; height: 18px; }

        #private-settings { display: none; padding: 15px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; margin-bottom: 15px; }
        #private-settings.active { display: block; animation: fadeIn 0.3s; }
        
        .attempts-row { display: flex; align-items: center; gap: 10px; }
        .unlimited-check { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #64748b; cursor: pointer; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="page-internal">

    <header>
        <div class="header-content">
            <div class="site-title">EDU-Familiar.kz</div>
            <div class="header-actions">
                <button id="edit-meta-btn" class="login-btn" style="background: white; color: #1e293b; margin-right: 10px; border: 1px solid #e2e8f0;">
                    <i class="fas fa-cog"></i> Настройки
                </button>
                <a href="teacher_dashboard.php" class="login-btn" style="background: white; color: #1e293b;">
                    <i class="fas fa-times"></i> Закрыть
                </a>
            </div>
        </div>
    </header>
    
    <div class="app-container">
        <aside id="content-panel">
            <h4>Настройки Теста</h4>
            <div class="tool-draggable" draggable="true" data-type="timer"><i class="fas fa-clock"></i> <span>Таймер (мин)</span></div>
            <h4>Медиа и Текст</h4>
            <div class="tool-draggable" draggable="true" data-type="heading"><i class="fas fa-heading"></i> <span>Заголовок</span></div>
            <div class="tool-draggable" draggable="true" data-type="text"><i class="fas fa-paragraph"></i> <span>Текст</span></div>
            <div class="tool-draggable" draggable="true" data-type="quote"><i class="fas fa-quote-left"></i> <span>Цитата</span></div>
            <div class="tool-draggable" draggable="true" data-type="image_upload"><i class="fas fa-cloud-upload-alt"></i> <span>Картинка (Файл)</span></div>
            <div class="tool-draggable" draggable="true" data-type="video"><i class="fab fa-youtube"></i> <span>Видео (YouTube)</span></div>
            <div class="tool-draggable" draggable="true" data-type="gallery"><i class="fas fa-images"></i> <span>Галерея</span></div>
            <div class="tool-draggable" draggable="true" data-type="audio_player"><i class="fas fa-volume-up"></i> <span>Аудио</span></div>
            <div class="tool-draggable" draggable="true" data-type="file_download"><i class="fas fa-file-download"></i> <span>Файл (Скачать)</span></div>
            <div class="tool-draggable" draggable="true" data-type="separator"><i class="fas fa-minus"></i> <span>Разделитель</span></div>
            <h4>Вопросы и Задания</h4>
            <div class="tool-draggable" draggable="true" data-type="question_mcq"><i class="fas fa-dot-circle"></i> <span>Один вариант</span></div>
            <div class="tool-draggable" draggable="true" data-type="question_checkbox"><i class="fas fa-check-square"></i> <span>Неск. вариантов</span></div>
            <div class="tool-draggable" draggable="true" data-type="question_text"><i class="fas fa-pen"></i> <span>Короткий ответ</span></div>
            <div class="tool-draggable" draggable="true" data-type="question_essay"><i class="fas fa-align-left"></i> <span>Эссе (Длинный)</span></div>
            <div class="tool-draggable" draggable="true" data-type="question_sequence"><i class="fas fa-sort-numeric-down"></i> <span>Последовательность</span></div>
            <div class="tool-draggable" draggable="true" data-type="question_matching"><i class="fas fa-random"></i> <span>Соответствие</span></div>
            <div class="tool-draggable" draggable="true" data-type="file_submission"><i class="fas fa-file-upload"></i> <span>Сбор файлов</span></div>
            <h4>Интерактив</h4>
            <div class="tool-draggable" draggable="true" data-type="button"><i class="fas fa-mouse-pointer"></i> <span>Кнопка-ссылка</span></div>
            <div class="tool-draggable" draggable="true" data-type="contact_form"><i class="fas fa-envelope-open-text"></i> <span>Форма связи</span></div>
        </aside>

        <main class="main-editor">
            <div id="page-frame" class="dropzone" data-accepts="section">
                <div class="placeholder-text">
                    <i class="fas fa-layer-group" style="font-size: 40px; margin-bottom: 20px; color: #cbd5e1;"></i>
                    <h3>Урок пуст</h3>
                    <p>Выберите структуру сетки внизу экрана, чтобы создать первую секцию.</p>
                </div>
            </div>
        </main>
    </div>

    <div class="add-section-toolbar">
        <span>Секции:</span>
        <button class="add-section-btn" title="1 Колонка" data-columns="1"><i class="fas fa-square"></i></button>
        <button class="add-section-btn" title="2 Колонки" data-columns="2"><i class="fas fa-columns"></i></button>
        <button class="add-section-btn" title="3 Колонки" data-columns="3"><i class="fas fa-chart-simple"></i></button>
        <button class="add-section-btn" title="4 Колонки" data-columns="4"><i class="fas fa-table-cells"></i></button>
        <div style="width: 1px; height: 25px; background: rgba(255,255,255,0.2); margin: 0 15px;"></div>
        <button id="import-test-btn" class="add-section-btn import-btn"><i class="fas fa-file-import"></i> Импорт</button>
        <button id="save-btn" class="save-btn"><i class="fas fa-save" style="margin-right: 8px;"></i> Сохранить</button>
    </div>

    <input type="file" id="generic-file-uploader-input" style="display: none;">
    <input type="color" id="text-color-picker-hidden" style="display: none;">
    <input type="color" id="block-bg-picker-hidden" style="display: none;" value="#FFFFFF">
    <input type="color" id="section-bg-picker-hidden" style="display: none;" value="#FFFFFF">
    <input type="color" id="border-color-picker-hidden" style="display: none;" value="#000000">

    <div id="rich-text-toolbar">
        <button class="rich-text-btn" data-command="bold"><i class="fas fa-bold"></i></button>
        <button class="rich-text-btn" data-command="italic"><i class="fas fa-italic"></i></button>
        <button class="rich-text-btn" data-command="underline"><i class="fas fa-underline"></i></button>
        <button class="rich-text-btn" data-command="createLink"><i class="fas fa-link"></i></button>
        <button class="rich-text-btn" data-command="removeFormat"><i class="fas fa-eraser"></i></button>
    </div>

    <div id="style-modal-backdrop" class="style-modal-backdrop"></div>
    <div id="style-modal" class="style-modal">
        <h3>Стиль Элемента</h3>
        <span id="style-modal-close" class="style-modal-close">×</span>
        <div class="style-modal-content">
            <div class="style-group style-group-4-col">
                 <div class="input-wrapper"><label>Отступ Сверху</label><input type="number" id="style-padding-top"></div>
                 <div class="input-wrapper"><label>Справа</label><input type="number" id="style-padding-right"></div>
                 <div class="input-wrapper"><label>Снизу</label><input type="number" id="style-padding-bottom"></div>
                 <div class="input-wrapper"><label>Слева</label><input type="number" id="style-padding-left"></div>
            </div>
            <h4>Граница</h4>
            <div class="style-group style-group-2-col">
                <div class="input-wrapper"><label>Толщина</label><input type="number" id="style-border-width"></div>
                <div class="input-wrapper"><label>Стиль</label>
                    <select id="style-border-style">
                        <option value="none">Нет</option>
                        <option value="solid">Сплошная</option>
                        <option value="dashed">Пунктир</option>
                        <option value="dotted">Точки</option>
                    </select>
                </div>
            </div>
            <div class="style-group style-group-2-col">
                <div class="input-wrapper"><label>Цвет границы</label><div class="color-trigger-btn" id="style-border-color-trigger"></div></div>
                <div class="input-wrapper"><label>Скругление (px)</label><input type="number" id="style-border-radius"></div>
            </div>
            <h4>Тень</h4>
            <div class="input-wrapper">
                <label>Параметры тени (CSS)</label>
                <input type="text" id="style-box-shadow" placeholder="напр: 0 4px 6px rgba(0,0,0,0.1)">
            </div>
        </div>
    </div>

    <div id="meta-modal-backdrop" class="meta-modal-backdrop"></div>
    <div id="meta-modal" class="meta-modal" style="width: 850px; max-width: 95vw;">
        <div class="meta-header">
            <h3>Настройки Урока</h3>
            <p>Заполните информацию об уроке и настройте доступ</p>
        </div>
        
        <div class="meta-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <div class="meta-column">
                <div class="input-wrapper">
                    <label>Название урока</label>
                    <input type="text" id="meta-title" class="meta-input" placeholder="Введите название...">
                </div>
                
                <div class="input-wrapper">
                    <label>Тип публикации</label>
                    <div class="visibility-toggle">
                        <label class="vis-option">
                            <input type="radio" name="privacy" value="public" checked onchange="togglePrivateSettings()">
                            <div class="vis-card">
                                <i class="fas fa-globe"></i>
                                <span>Публичный</span>
                            </div>
                        </label>
                        <label class="vis-option">
                            <input type="radio" name="privacy" value="private" onchange="togglePrivateSettings()">
                            <div class="vis-card">
                                <i class="fas fa-lock"></i>
                                <span>Приватный</span>
                            </div>
                        </label>
                    </div>
                    
                    <div id="private-settings">
                        <small style="display:block; margin-bottom:10px; color:#64748b;">Выберите класс и раздел для публикации:</small>
                        <select id="meta-class-select" class="meta-select" style="margin-bottom:10px;" onchange="loadSectionsForClass(this.value)">
                            <option value="">Выберите класс...</option>
                        </select>
                        <select id="meta-section-select" class="meta-select" disabled>
                            <option value="">Сначала выберите класс</option>
                        </select>
                    </div>

                    <label class="hidden-toggle-label">
                        <input type="checkbox" id="meta-is-hidden">
                        <span><i class="fas fa-eye-slash"></i> Скрыть урок (виден только мне)</span>
                    </label>
                </div>

                <div class="grid-two" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="input-wrapper">
                        <label>Предмет</label>
                        <select id="meta-subject" class="meta-select">
                            <option value="Русский язык">Русский язык</option>
                            <option value="Русская литература">Русская литература</option>
                            <option value="Русский язык и литература">Русский язык и литература</option>
                            <option value="Казахский язык">Казахский язык</option>
                            <option value="Казахский язык мен әдебиеті">Казахский язык и литература</option>
                            <option value="Английский язык">Английский язык</option>
                            <option value="Математика">Математика</option>
                            <option value="Алгебра">Алгебра</option>
                            <option value="Геометрия">Геометрия</option>
                            <option value="Физика">Физика</option>
                            <option value="Химия">Химия</option>
                            <option value="Биология">Биология</option>
                            <option value="Естествознание">Естествознание</option>
                            <option value="История">История</option>
                            <option value="Всемирная История">Всемирная История</option>
                            <option value="История Казахстана">История Казахстана</option>
                            <option value="География">География</option>
                            <option value="Глобальные компетенции">Глобальные компетенции</option>
                            <option value="Основы права">Основы права</option>
                            <option value="Самопознание">Самопознание</option>
                            <option value="Трудовое обучение">Трудовое обучение</option>
                            <option value="Изобразительное искусство">Изобразительное искусство</option>
                            <option value="Музыка">Музыка</option>
                            <option value="Художественный труд">Художественный труд</option>
                            <option value="Физическая культура">Физическая культура</option>
                            <option value="НВТП">Начальная военная и технологическая подготовка</option>
                            <option value="Информатика">Информатика</option>
                        </select>
                    </div>
                    <div class="input-wrapper">
                        <label>Параллель</label>
                        <select id="meta-grade" class="meta-select"></select>
                    </div>
                </div>

                <div class="input-wrapper">
                    <label>Языки урока</label>
                    <div class="lang-checkbox-group" id="meta-lang-container">
                        <label class="lang-checkbox-label"><input type="checkbox" value="ru" checked> RU</label>
                        <label class="lang-checkbox-label"><input type="checkbox" value="kz"> KZ</label>
                        <label class="lang-checkbox-label"><input type="checkbox" value="en"> EN</label>
                    </div>
                </div>
            </div>

            <div class="meta-column">
                <div class="input-wrapper">
                    <label>Обложка</label>
                    <div id="meta-avatar-trigger">
                        <img id="meta-avatar-preview" src="" alt="Превью">
                        <div class="upload-placeholder">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div>Загрузить</div>
                        </div>
                    </div>
                    <input type="file" id="meta-avatar-input" accept="image/*" style="display:none;">
                </div>

                <div class="input-wrapper">
                    <label>Краткое описание</label>
                    <textarea id="meta-short-desc" class="meta-textarea" rows="2" placeholder="О чем этот урок?"></textarea>
                </div>
                <div class="input-wrapper">
                    <label>Подробное описание</label>
                    <textarea id="meta-full-desc" class="meta-textarea" rows="4" placeholder="Подробный план..."></textarea>
                </div>

                <div class="grid-two" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="input-wrapper">
                        <label>Попытки</label>
                        <div class="attempts-row">
                            <input type="number" id="meta-attempts" class="meta-input" min="1" value="1">
                        </div>
                        <label class="unlimited-check">
                            <input type="checkbox" id="meta-unlimited-attempts" onchange="toggleUnlimited(this)"> 
                            Безлимитно
                        </label>
                    </div>
                    <div class="input-wrapper">
                        <label>Награда (EF)</label>
                        <div class="coins-input-wrapper">
                            <i class="fas fa-coins"></i>
                            <input type="number" id="meta-coins" class="meta-input" min="50" max="5000" value="150" step="50">
                        </div>
                    </div>
                </div>
                
                <div class="input-wrapper">
                    <label>Достижение (Название)</label>
                    <input type="text" id="meta-ach-name" class="meta-input" placeholder="Напр: Мастер алгебры">
                </div>
                <div id="meta-icon-grid" style="display: none;"></div> </div>
        </div>

        <div class="meta-footer">
            <button id="start-editor-btn" class="save-btn">Применить</button>
        </div>
    </div>

    <div id="save-confirm-backdrop" class="system-modal-backdrop"></div>
    <div id="save-confirm-modal" class="system-modal">
        <div class="system-modal-icon warning"><i class="fas fa-question"></i></div>
        <h3>Сохранить изменения?</h3>
        <p>Урок будет сохранен согласно выбранным настройкам.</p>
        <div class="system-modal-actions">
            <button id="confirm-save-no" class="system-btn btn-secondary">Нет</button>
            <button id="confirm-save-yes" class="system-btn btn-primary">Да, сохранить</button>
        </div>
    </div>

    <div id="save-success-backdrop" class="system-modal-backdrop"></div>
    <div id="save-success-modal" class="system-modal">
        <div class="system-modal-icon success"><i class="fas fa-check"></i></div>
        <h3>Успешно!</h3>
        <p>Перенаправление через <strong id="redirect-timer" style="color:var(--primary-color)">3</strong> сек.</p>
        <div class="system-modal-actions centered">
            <a href="my_lessons.php" class="system-btn btn-primary">Мои уроки</a>
        </div>
    </div>
    
    <div id="mobile-blocker">
        <i class="fas fa-desktop"></i>
        <h3>Только для ПК</h3>
        <p>Конструктор доступен только на десктопных устройствах.</p>
    </div>

    <script>
        // Переопределение fetch для маршрутизации
        const originalFetch = window.fetch;
        window.fetch = function(url, options) {
            if (url === 'save_page.php') url = 'save_lesson.php';
            if (url.startsWith('get_page.php')) url = url.replace('get_page.php', 'get_lesson.php');
            return originalFetch(url, options);
        };

        // --- НОВАЯ ЛОГИКА ДЛЯ UI ---
        let availableClasses = [];

        function togglePrivateSettings() {
            // ИСПРАВЛЕНО: ищем по name="privacy"
            const isPrivate = document.querySelector('input[name="privacy"][value="private"]').checked;
            const settingsDiv = document.getElementById('private-settings');
            
            if (isPrivate) {
                settingsDiv.classList.add('active');
                // Загружаем классы, если еще не загружены
                if (availableClasses.length === 0) {
                    fetch('class_actions.php?action=get_publishing_options')
                    .then(r => r.json())
                    .then(data => {
                        availableClasses = data;
                        renderClassOptions();
                    })
                    .catch(e => console.error("Ошибка загрузки классов", e));
                }
            } else {
                settingsDiv.classList.remove('active');
            }
        }

        function renderClassOptions() {
            const select = document.getElementById('meta-class-select');
            select.innerHTML = '<option value="">Выберите класс...</option>';
            availableClasses.forEach(cls => {
                const opt = document.createElement('option');
                opt.value = cls.id;
                opt.textContent = `${cls.name} (${cls.role === 'owner' ? 'Владелец' : 'Учитель'})`;
                select.appendChild(opt);
            });
        }

        function loadSectionsForClass(classId) {
            const secSelect = document.getElementById('meta-section-select');
            secSelect.innerHTML = '<option value="">Выберите раздел...</option>';
            
            if (!classId) {
                secSelect.disabled = true;
                return;
            }

            const cls = availableClasses.find(c => c.id == classId);
            if (cls && cls.sections.length > 0) {
                cls.sections.forEach(sec => {
                    const opt = document.createElement('option');
                    opt.value = sec.id;
                    opt.textContent = sec.title;
                    secSelect.appendChild(opt);
                });
                secSelect.disabled = false;
            } else {
                secSelect.innerHTML = '<option value="">Нет доступных разделов</option>';
                secSelect.disabled = true;
            }
        }

        function toggleUnlimited(checkbox) {
            const input = document.getElementById('meta-attempts');
            if (checkbox.checked) {
                input.disabled = true;
                input.value = ''; 
                input.dataset.oldValue = input.value;
            } else {
                input.disabled = false;
                input.value = input.dataset.oldValue || 1;
            }
        }
    </script>
    
    <script src="JS/lesson.js"></script>

</body>
</html>
