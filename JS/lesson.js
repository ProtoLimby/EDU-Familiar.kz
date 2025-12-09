document.addEventListener('DOMContentLoaded', () => {

    // ============================================================
    // 1. ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ И ИНИЦИАЛИЗАЦИЯ
    // ============================================================
    const pageFrame = document.getElementById('page-frame');
    const contentPanel = document.getElementById('content-panel');
    const genericFileUploader = document.getElementById('generic-file-uploader-input');
    
    const urlParams = new URLSearchParams(window.location.search);
    let currentPageId = urlParams.get('id');

    // ============================================================
    // 2. ЛОГИКА МЕТА-ДАННЫХ (НАСТРОЙКИ УРОКА)
    // ============================================================
    const metaModal = document.getElementById('meta-modal');
    const metaBackdrop = document.getElementById('meta-modal-backdrop');
    const startEditorBtn = document.getElementById('start-editor-btn');
    const gradeSelect = document.getElementById('meta-grade');
    const iconGrid = document.getElementById('meta-icon-grid');
    
    // Кнопка открытия настроек из шапки
    const editMetaBtn = document.getElementById('edit-meta-btn');
    if (editMetaBtn) {
        editMetaBtn.addEventListener('click', () => {
            metaModal.classList.add('active');
            metaBackdrop.classList.add('active');
        });
    }

    // Заполняем селект классов (1-11)
    if (gradeSelect) {
        for(let i=1; i<=11; i++) {
            const opt = document.createElement('option');
            opt.value = i; 
            opt.innerText = i + ' Класс';
            gradeSelect.appendChild(opt);
        }
    }

    // Логика выбора иконки достижения
    let selectedIcon = 'fa-star';
    if(iconGrid) {
        iconGrid.querySelectorAll('.icon-option').forEach(opt => {
            opt.addEventListener('click', () => {
                iconGrid.querySelectorAll('.icon-option').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                selectedIcon = opt.dataset.icon;
            });
        });
    }

    // Логика загрузки Обложки (Preview Base64)
    const avatarInput = document.getElementById('meta-avatar-input');
    const avatarTrigger = document.getElementById('meta-avatar-trigger');
    const avatarPreview = document.getElementById('meta-avatar-preview');
    let avatarBase64 = '';

    if(avatarTrigger) {
        avatarTrigger.addEventListener('click', () => avatarInput.click());
        avatarInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    avatarPreview.src = ev.target.result;
                    avatarPreview.style.display = 'block';
                    avatarTrigger.querySelector('.upload-placeholder').style.display = 'none';
                    avatarBase64 = ev.target.result; 
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Показываем модалку сразу, если это новая страница (нет ID)
    if (!currentPageId && metaModal) {
        metaModal.classList.add('active');
        metaBackdrop.classList.add('active');
    }

    // Кнопка "Применить" в модалке настроек
    if(startEditorBtn) {
        startEditorBtn.addEventListener('click', () => {
            const title = document.getElementById('meta-title').value;
            if(!title) { alert('Пожалуйста, введите название урока!'); return; }
            
            metaModal.classList.remove('active');
            metaBackdrop.classList.remove('active');
        });
    }

    // Сбор метаданных перед сохранением
    function getPageMetadata() {
        const selectedLangs = Array.from(document.querySelectorAll('#meta-lang-container input:checked'))
                                   .map(cb => cb.value);
        const langString = selectedLangs.length > 0 ? selectedLangs.join(',') : 'ru';

        let attempts = document.getElementById('meta-attempts').value;
        const isUnlimited = document.getElementById('meta-unlimited-attempts').checked;
        if(isUnlimited) attempts = 0;

        // ИСПРАВЛЕНО: берем значение из name="privacy"
        const privacy = document.querySelector('input[name="privacy"]:checked').value;
        const isHidden = document.getElementById('meta-is-hidden').checked;
        const classId = document.getElementById('meta-class-select').value;
        const sectionId = document.getElementById('meta-section-select').value;

        return {
            title: document.getElementById('meta-title').value || 'Новый урок',
            subject: document.getElementById('meta-subject').value,
            grade: document.getElementById('meta-grade').value,
            language: langString,
            lesson_avatar: avatarBase64,
            short_description: document.getElementById('meta-short-desc').value || '',
            full_description: document.getElementById('meta-full-desc').value || '',
            achievement_name: document.getElementById('meta-ach-name').value || '',
            achievement_icon: selectedIcon,
            coins: document.getElementById('meta-coins').value,
            max_attempts: attempts,
            // ИСПРАВЛЕНО: отправляем privacy
            privacy: privacy,
            is_hidden: isHidden,
            class_id: classId,
            section_id: sectionId
        };
    }
    
    function fillMetadata(meta) {
        if (!meta) return;

        // 1. Основные текстовые поля
        document.getElementById('meta-title').value = meta.page_name || '';
        if (meta.subject) document.getElementById('meta-subject').value = meta.subject;
        if (meta.grade) document.getElementById('meta-grade').value = meta.grade;

        // 2. Языки
        if (meta.language) {
            const savedLangs = meta.language.split(',').map(lang => lang.trim().toLowerCase());
            document.querySelectorAll('#meta-lang-container input').forEach(cb => {
                cb.checked = savedLangs.includes(cb.value.toLowerCase());
            });
        } else {
            // Дефолт - русский
            document.querySelectorAll('#meta-lang-container input').forEach(cb => {
                cb.checked = (cb.value === 'ru');
            });
        }

        // 3. Описания и Награды
        if (meta.short_description) document.getElementById('meta-short-desc').value = meta.short_description;
        if (meta.full_description) document.getElementById('meta-full-desc').value = meta.full_description;
        if (meta.achievement_name) document.getElementById('meta-ach-name').value = meta.achievement_name;
        if (meta.coins_reward) document.getElementById('meta-coins').value = meta.coins_reward;

        // 4. Попытки (0 = безлимит)
        const attemptsInput = document.getElementById('meta-attempts');
        const unlimitedCheck = document.getElementById('meta-unlimited-attempts');
        // Важно: проверяем именно как число
        if (parseInt(meta.max_attempts) === 0) {
            unlimitedCheck.checked = true;
            attemptsInput.disabled = true;
            attemptsInput.value = ''; // Визуально пусто
        } else {
            unlimitedCheck.checked = false;
            attemptsInput.disabled = false;
            attemptsInput.value = meta.max_attempts || 1;
        }

        // 5. Приватность (Public/Private)
        if (meta.privacy) {
            // Ищем радио-кнопку с нужным value (public или private)
            const rad = document.querySelector(`input[name="privacy"][value="${meta.privacy}"]`);
            if (rad) {
                rad.checked = true;
                // Запускаем функцию переключения интерфейса (показать/скрыть выбор класса)
                if (typeof togglePrivateSettings === 'function') {
                    togglePrivateSettings();
                }
            }
        }

        // 6. Скрытый урок (Галочка)
        if (meta.is_hidden == 1) {
            document.getElementById('meta-is-hidden').checked = true;
        } else {
            document.getElementById('meta-is-hidden').checked = false;
        }

        // 7. Логика Класса и Раздела (Для приватных уроков)
        // Нам нужно дождаться, пока AJAX загрузит список классов, прежде чем выбирать нужный.
        if (meta.privacy === 'private' && meta.class_id) {
            const checkInterval = setInterval(() => {
                const classSelect = document.getElementById('meta-class-select');
                
                // Ждем, пока в селекте появится что-то кроме "Выберите класс..."
                if (classSelect && classSelect.options.length > 1) {
                    clearInterval(checkInterval);
                    
                    // 1. Выбираем класс
                    classSelect.value = meta.class_id;
                    
                    // 2. Запускаем загрузку разделов для этого класса
                    if (typeof loadSectionsForClass === 'function') {
                        loadSectionsForClass(meta.class_id);
                    }
                    
                    // 3. Ждем чуть-чуть, пока загрузятся разделы, и выбираем раздел
                    setTimeout(() => {
                        const secSelect = document.getElementById('meta-section-select');
                        if (secSelect && meta.section_id) {
                            secSelect.value = meta.section_id;
                        }
                    }, 500); // 500мс обычно хватает для второго AJAX запроса
                }
            }, 200); // Проверяем каждые 200мс
        }

        // 8. Иконка достижения
        if (meta.achievement_icon) {
            selectedIcon = meta.achievement_icon;
            const opts = document.querySelectorAll('.icon-option');
            opts.forEach(o => {
                o.classList.remove('selected');
                if (o.dataset.icon === selectedIcon) o.classList.add('selected');
            });
        }

        // 9. Обложка (Base64)
        if (meta.lesson_avatar) {
            avatarBase64 = meta.lesson_avatar;
            const ap = document.getElementById('meta-avatar-preview');
            if (ap) {
                ap.src = avatarBase64;
                ap.style.display = 'block';
                const ph = document.querySelector('.upload-placeholder');
                if (ph) ph.style.display = 'none';
            }
        }
    }

    // ============================================================
    // 3. УПРАВЛЕНИЕ СТИЛЯМИ И ИНСТРУМЕНТАМИ
    // ============================================================
    const textColorPicker = document.getElementById('text-color-picker-hidden');
    const blockBgPicker = document.getElementById('block-bg-picker-hidden');
    const sectionBgPicker = document.getElementById('section-bg-picker-hidden'); 
    const borderColorPicker = document.getElementById('border-color-picker-hidden');
    
    let targetTextElementForColor = null;
    let targetBlockForBg = null; 
    let targetSectionForBg = null; 
    let targetElementForStyling = null; 
    let targetButtonForBg = null;
    let targetButtonForColor = null;
    
    let draggingElement = null; 
    let draggingSequenceItem = null; 
    const richTextToolbar = document.getElementById('rich-text-toolbar');
    
    let dragType = null; 
    let toolType = null; 
    const dragPlaceholder = document.createElement('div');
    dragPlaceholder.className = 'drag-placeholder';
    
    const styleModal = document.getElementById('style-modal');
    const styleModalBackdrop = document.getElementById('style-modal-backdrop');
    const styleModalClose = document.getElementById('style-modal-close');
    const styleInputs = {
        paddingTop: document.getElementById('style-padding-top'),
        paddingRight: document.getElementById('style-padding-right'),
        paddingBottom: document.getElementById('style-padding-bottom'),
        paddingLeft: document.getElementById('style-padding-left'),
        borderWidth: document.getElementById('style-border-width'),
        borderStyle: document.getElementById('style-border-style'),
        borderColorBtn: document.getElementById('style-border-color-trigger'),
        borderRadius: document.getElementById('style-border-radius'),
        boxShadow: document.getElementById('style-box-shadow'),
    };
    
    let currentUploadCallback = null;

    if (genericFileUploader) {
        genericFileUploader.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (file && typeof currentUploadCallback === 'function') {
                currentUploadCallback(file);
            }
            genericFileUploader.value = '';
            genericFileUploader.accept = '';
            currentUploadCallback = null;
        });
    }

    // Обработчики пикеров цвета
    textColorPicker.addEventListener('input', (e) => {
        if (targetTextElementForColor) targetTextElementForColor.style.color = e.target.value;
        if (targetButtonForColor) targetButtonForColor.style.color = e.target.value;
    });
    blockBgPicker.addEventListener('input', (e) => {
        if (targetBlockForBg) targetBlockForBg.style.backgroundColor = e.target.value;
        if (targetButtonForBg) targetButtonForBg.style.backgroundColor = e.target.value;
    });
    sectionBgPicker.addEventListener('input', (e) => {
        if (targetSectionForBg) targetSectionForBg.style.backgroundColor = e.target.value;
    });
    borderColorPicker.addEventListener('input', (e) => {
        if (targetElementForStyling) {
            targetElementForStyling.style.borderColor = e.target.value;
            styleInputs.borderColorBtn.style.backgroundColor = e.target.value;
        }
    });

    function checkPlaceholders() {
        const mainPlaceholder = pageFrame.querySelector('.placeholder-text');
        const sections = pageFrame.querySelectorAll('.content-section');
        if (mainPlaceholder) {
            mainPlaceholder.style.display = sections.length > 0 ? 'none' : 'block';
        }
        document.querySelectorAll('.column-dropzone').forEach(zone => {
            const placeholder = zone.querySelector('.column-placeholder');
            const blocks = zone.querySelectorAll('.content-block');
            if (placeholder) {
                placeholder.style.display = blocks.length > 0 ? 'none' : 'block';
            }
        });
    }
    
    function rgbToHex(rgb) {
        if (!rgb || !rgb.startsWith('rgb')) return rgb; 
        const match = rgb.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)$/);
        if (!match) return rgb;
        function componentToHex(c) {
            var hex = Number(c).toString(16);
            return hex.length == 1 ? "0" + hex : hex;
        }
        return "#" + componentToHex(match[1]) + componentToHex(match[2]) + componentToHex(match[3]);
    }

    // Создание секций из нижней панели
    document.querySelectorAll('.add-section-btn:not(.save-btn):not(.import-btn)').forEach(btn => {
        btn.addEventListener('click', () => {
            const numColumns = parseInt(btn.dataset.columns);
            const section = createSectionBlock(numColumns);
            pageFrame.appendChild(section);
            checkPlaceholders();
            window.scrollTo(0, document.body.scrollHeight);
        });
    });
    
    document.getElementById('import-test-btn').addEventListener('click', () => {
        genericFileUploader.accept = '.csv, text/csv';
        currentUploadCallback = importTestFromCSV; 
        genericFileUploader.click();
    });

    // ============================================================
    // 4. ФУНКЦИИ СОЗДАНИЯ БЛОКОВ
    // ============================================================

    function createSectionBlock(numColumns) {
        const section = document.createElement('div');
        section.className = 'content-section';
        section.setAttribute('draggable', 'true');
        section.dataset.dragType = 'section';
        // Установка дефолтных стилей
        section.style.backgroundColor = '#FFFFFF';
        section.style.padding = '20px';
        section.style.borderWidth = '1px';
        section.style.borderStyle = 'solid';
        section.style.borderColor = 'var(--border-color)';
        section.style.borderRadius = '8px';
        section.style.boxShadow = 'none';
        
        let columnsHTML = '';
        for (let i = 0; i < numColumns; i++) {
            columnsHTML += `
                <div class="column-dropzone dropzone" data-accepts="content" data-column="${i+1}">
                    <div class="column-placeholder">Перетащите блок сюда</div>
                </div>
            `;
        }
        
        const controlsHTML = `
            <div class="section-controls">
                <span class="section-control-btn drag-handle" title="Перетащить секцию">⠿</span>
                <span class="section-control-btn style-btn" title="Стили">🎨</span>
                <span class="section-control-btn bg-color-picker-trigger-btn" title="Фон секции">🖌️</span>
                <span class="section-control-btn delete-btn" title="Удалить секцию">&times;</span>
            </div>
        `;
        
        section.innerHTML = `${controlsHTML}<div class="section-columns-container">${columnsHTML}</div>`;
        
        section.querySelector('.delete-btn').addEventListener('click', () => {
            if (confirm('Удалить эту секцию со всем содержимым?')) {
                section.remove();
                checkPlaceholders();
            }
        });
        section.querySelector('.bg-color-picker-trigger-btn').addEventListener('click', () => {
            targetSectionForBg = section;
            sectionBgPicker.value = rgbToHex(section.style.backgroundColor) || '#FFFFFF';
            sectionBgPicker.click();
        });
        section.querySelector('.style-btn').addEventListener('click', () => {
            targetElementForStyling = section;
            openStyleModal();
        });
        
        addDragEventsToDraggable(section);
        section.querySelectorAll('.column-dropzone').forEach(zone => {
            addDropzoneEvents(zone);
        });
        return section;
    }

    function createContentBlock(type) {
        const block = document.createElement('div');
        block.className = 'content-block';
        block.setAttribute('draggable', 'true');
        block.dataset.type = type;
        block.dataset.dragType = 'content';
        
        // Дефолтные стили
        block.style.padding = '20px';
        block.style.borderWidth = '1px';
        block.style.borderStyle = 'solid';
        block.style.borderColor = 'var(--border-color)';
        block.style.borderRadius = '6px';
        block.style.boxShadow = 'none';
        
        let innerHTML = '';
        let controlsHTML = `
            <span class="control-btn drag-handle" title="Перетащить блок">⠿</span>
            <span class="control-btn delete-btn" title="Удалить">&times;</span>
        `;
        
        let baseControls = `
            <span class="control-btn style-btn" title="Стили">🎨</span>
            <span class="control-btn bg-color-picker-trigger-btn" title="Фон блока">🖌️</span>
        `;
        
        switch (type) {
            case 'heading':
            case 'text':
                innerHTML = (type === 'heading') 
                    ? '<h1 contenteditable="true">Новый заголовок</h1>'
                    : '<p contenteditable="true">Введите ваш параграф здесь...</p>';
                controlsHTML = `
                    <select class="font-family-select" title="Выбрать шрифт">
                        <option value="'Poppins', sans-serif">Poppins</option>
                        <option value="'Open Sans', sans-serif">Open Sans</option>
                        <option value="'Georgia', serif">Georgia</option>
                        <option value="'Arial', sans-serif">Arial</option>
                        <option value="'Courier New', monospace">Courier New</option>
                    </select>
                    <span class="control-btn align-btn" data-align="left" title="По левому краю">L</span>
                    <span class="control-btn align-btn" data-align="center" title="По центру">C</span>
                    <span class="control-btn align-btn" data-align="right" title="По правому краю">R</span>
                    <span class="control-btn font-size-btn" data-action="increase" title="Увеличить (A+)">A+</span>
                    <span class="control-btn font-size-btn" data-action="decrease" title="Уменьшить (A-)">A-</span>
                    <span class="control-btn color-picker-trigger-btn" title="Цвет текста">🎨</span>
                    ${baseControls}
                ` + controlsHTML;
                break;
            case 'quote':
                innerHTML = '<blockquote contenteditable="true">Цитата...</blockquote>';
                controlsHTML = baseControls + controlsHTML;
                break;
            case 'separator':
                innerHTML = '<hr>';
                block.style.padding = '10px 25px';
                block.style.borderWidth = '0';
                break;
            case 'image_upload':
            case 'image_url':
            case 'video':
                if(type === 'image_upload') {
                    innerHTML = `<div class="image-upload-wrapper"><p class="image-upload-placeholder" style="text-align: center; padding: 20px; border: 2px dashed #ccc; border-radius: 5px;">Нажмите 📤, чтобы выбрать файл...</p></div>`;
                    controlsHTML = `<span class="control-btn" id="trigger-image-upload" title="Загрузить">📤</span>`;
                } else if(type === 'image_url') {
                    innerHTML = `<div class="url-embed-ui"><p>Вставьте URL изображения и нажмите Enter:</p><input type="text" class="url-input image-url-input" placeholder="https://..."></div><div class="image-content-wrapper"></div>`;
                    controlsHTML = '';
                } else {
                    innerHTML = `<div class="url-embed-ui"><p>Вставьте URL (YouTube/Vimeo) и нажмите Enter:</p><input type="text" class="url-input video-url-input" placeholder="https://youtube.com/watch?v=..."></div><div class="video-content-wrapper"></div>`;
                    controlsHTML = '';
                }
                controlsHTML = `
                    ${controlsHTML}
                    <span class="control-btn media-align-btn" data-align="left" title="По левому краю">L</span>
                    <span class="control-btn media-align-btn" data-align="center" title="По центру">C</span>
                    <span class="control-btn media-align-btn" data-align="right" title="По правому краю">R</span>
                    <span class="control-btn media-width-btn" data-action="decrease" title="Уменьшить (W-)">W-</span>
                    <span class="control-btn media-width-btn" data-action="increase" title="Увеличить (W+)">W+</span>
                    ${baseControls}
                    <span class="control-btn drag-handle" title="Перетащить блок">⠿</span>
                    <span class="control-btn delete-btn" title="Удалить">&times;</span>
                `;
                break;
            case 'gallery':
                innerHTML = `
                    <div class="gallery-viewport">
                        <div class="gallery-track"></div>
                        <button class="gallery-nav-btn prev" title="Назад" style="display:none;">&lt;</button>
                        <button class="gallery-nav-btn next" title="Вперед" style="display:none;">&gt;</button>
                        <div class="gallery-dots-container"></div>
                    </div>
                    <button class="add-gallery-image-btn">+ Добавить изображение</button>
                `;
                break;
            case 'file_download':
                innerHTML = `
                    <h4 contenteditable="true">Заголовок для файла</h4>
                    <div class="file-download-wrapper">
                        <p class="file-download-placeholder">Нажмите 📤, чтобы загрузить файл...</p>
                    </div>
                `;
                controlsHTML = `
                    <span class="control-btn" id="trigger-file-download" title="Загрузить файл">📤</span>
                    ${baseControls}
                ` + controlsHTML;
                break;
            case 'audio_player':
                innerHTML = `
                    <h4 contenteditable="true">Название аудио</h4>
                    <div class="audio-player-wrapper">
                        <p class="audio-player-placeholder">Нажмите 📤, чтобы загрузить MP3/WAV...</p>
                    </div>
                `;
                controlsHTML = `
                    <span class="control-btn" id="trigger-audio-upload" title="Загрузить аудио">📤</span>
                    ${baseControls}
                ` + controlsHTML;
                break;
            case 'table':
                innerHTML = `
                    <div class="table-controls">
                        <button class="table-control-btn" data-action="add-row">+ Ряд</button>
                        <button class="table-control-btn" data-action="add-col">+ Колонка</button>
                        <button class="table-control-btn" data-action="del-row">- Ряд</button>
                        <button class="table-control-btn" data-action="del-col">- Колонка</button>
                    </div>
                    <div class="table-wrapper">
                        <table class="editable-table">
                            <thead>
                                <tr>
                                    <th contenteditable="true">Заголовок 1</th>
                                    <th contenteditable="true">Заголовок 2</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td contenteditable="true">Ячейка 1</td>
                                    <td contenteditable="true">Ячейка 2</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                `;
                controlsHTML = baseControls + controlsHTML;
                break;
            case 'file_submission':
                innerHTML = `
                    <h4 contenteditable="true">Задание (требует отправки файла)</h4>
                    <div class="file-submission-wrapper">
                        <div class="file-submission-placeholder">📥 Поле для отправки файла</div>
                    </div>
                `;
                 controlsHTML = `
                    <span class="control-btn mandatory-btn" title="Обязательный вопрос">*</span>
                    ${baseControls}
                ` + controlsHTML;
                break;
            case 'contact_form':
                innerHTML = `
                    <h4 contenteditable="true">Форма обратной связи</h4>
                    <div class="contact-form-placeholder">
                        <div class="contact-form-placeholder-group">
                            <label>Ваше Имя</label>
                            <input type="text" placeholder="Имя..." disabled>
                        </div>
                        <div class="contact-form-placeholder-group">
                            <label>Ваш Email</label>
                            <input type="email" placeholder="email@example.com" disabled>
                        </div>
                        <div class="contact-form-placeholder-group">
                            <label>Сообщение</label>
                            <textarea rows="4" placeholder="Ваше сообщение..." disabled></textarea>
                        </div>
                        <div class="contact-form-placeholder-group">
                            <button disabled>Отправить</button>
                        </div>
                    </div>
                `;
                controlsHTML = baseControls + controlsHTML;
                break;
            case 'button':
                innerHTML = `
                    <div class="button-wrapper">
                        <a class="editable-button" contenteditable="true">Текст кнопки</a>
                    </div>
                    <input type="text" class="button-url-input" placeholder="Вставьте URL (ссылку) сюда...">
                `;
                controlsHTML = `
                    <select class="font-family-select" title="Выбрать шрифт">
                        <option value="'Poppins', sans-serif">Poppins</option>
                        <option value="'Open Sans', sans-serif">Open Sans</option>
                        <option value="'Georgia', serif">Georgia</option>
                        <option value="'Arial', sans-serif">Arial</option>
                        <option value="'Courier New', monospace">Courier New</option>
                    </select>
                    <span class="control-btn font-size-btn" data-action="increase" title="Увеличить (A+)">A+</span>
                    <span class="control-btn font-size-btn" data-action="decrease" title="Уменьшить (A-)">A-</span>
                    <span class="control-btn align-btn" data-align="left" title="По левому краю">L</span>
                    <span class="control-btn align-btn" data-align="center" title="По центру">C</span>
                    <span class="control-btn align-btn" data-align="right" title="По правому краю">R</span>
                    <span class="control-btn color-picker-trigger-btn" title="Цвет текста">🎨</span>
                    <span class="control-btn bg-color-picker-trigger-btn" title="Цвет кнопки">🖌️</span>
                    <span class="control-btn style-btn" title="Стили">🎨</span>
                    <span class="control-btn drag-handle" title="Перетащить блок">⠿</span>
                    <span class="control-btn delete-btn" title="Удалить">&times;</span>
                `;
                break;
            case 'question_mcq':
            case 'question_checkbox':
            case 'question_text':
            case 'question_essay':
                const questionId = `q_${Date.now()}`;
                block.dataset.questionId = questionId; 
                if (type === 'question_mcq' || type === 'question_checkbox') {
                    innerHTML = `<h4 contenteditable="true">Введите ваш вопрос...</h4><ul class="options-container" data-qtype="${type}"></ul><button class="add-option-btn">+ Добавить вариант</button>`;
                } else if (type === 'question_text') {
                    innerHTML = `<h4 contenteditable="true">Вопрос (короткий ответ)</h4><input type="text" placeholder="Поле для ответа" disabled>`;
                } else {
                    innerHTML = `<h4 contenteditable="true">Вопрос (эссе)</h4><textarea placeholder="Поле для ответа" disabled rows="4" style="width: 95%;"></textarea>`;
                }
                controlsHTML = `
                    <span class="control-btn mandatory-btn" title="Обязательный вопрос">*</span>
                    ${baseControls}
                ` + controlsHTML;
                break;
            case 'question_sequence':
                const seq_questionId = `q_${Date.now()}`;
                block.dataset.questionId = seq_questionId;
                innerHTML = `
                    <h4 contenteditable="true">Задание на последовательность...</h4>
                    <p class="sequence-helper-text">Элементы в этом списке находятся в **правильном** порядке. Перетащите их, чтобы изменить правильный ответ.</p>
                    <ul class="sequence-options-container"></ul>
                    <button class="add-option-btn">+ Добавить элемент</button>
                `;
                controlsHTML = `
                    <span class="control-btn mandatory-btn" title="Обязательный вопрос">*</span>
                    ${baseControls}
                ` + controlsHTML;
                break;
            case 'question_matching':
                const match_questionId = `q_${Date.now()}`;
                block.dataset.questionId = match_questionId;
                innerHTML = `
                    <h4 contenteditable="true">Задание на соответствие...</h4>
                    <p class="matching-helper-text">Это **правильные** пары. При прохождении теста они будут перемешаны. Перетащите, чтобы изменить порядок.</p>
                    <ul class="matching-options-container"></ul>
                    <button class="add-option-btn">+ Добавить пару</button>
                `;
                controlsHTML = `
                    <span class="control-btn mandatory-btn" title="Обязательный вопрос">*</span>
                    ${baseControls}
                ` + controlsHTML;
                break;
            case 'timer':
                innerHTML = `
                    <div class="timer-block-wrapper">
                        <i class="fas fa-clock timer-block-icon"></i>
                        <div class="timer-block-content">
                            <p>Установить время теста (в минутах - Распространяется на всё!):</p>
                            <input type="number" class="timer-block-input" value="30" min="1">
                        </div>
                    </div>
                `;
                controlsHTML = `
                    <span class="control-btn drag-handle" title="Перетащить блок">⠿</span>
                    <span class="control-btn delete-btn" title="Удалить">&times;</span>
                `;
                block.style.padding = '0'; 
                block.style.border = 'none';
                block.style.background = 'none';
                block.style.boxShadow = 'none';
                break;

            default:
                innerHTML = `<p>Неизвестный блок: ${type}</p>`;
                break;
        }

        const controlsWrapper = document.createElement('div');
        controlsWrapper.className = 'block-controls';
        controlsWrapper.innerHTML = controlsHTML;
        block.innerHTML = innerHTML;
        block.appendChild(controlsWrapper); 
        addBlockControlsListeners(block, controlsWrapper);
        addDragEventsToDraggable(block);
        return block;
    }

    function addBlockControlsListeners(block, controlsWrapper) {
        controlsWrapper.querySelector('.delete-btn')?.addEventListener('click', () => {
            if (confirm('Удалить этот блок?')) {
                block.remove();
                checkPlaceholders();
            }
        });
        controlsWrapper.querySelectorAll('.font-size-btn').forEach(btn => {
            btn.onclick = () => {
                let target = block.querySelector('h1, p');
                if (!target) target = block.querySelector('.editable-button');
                if (target) changeFontSize(target, btn.dataset.action);
            };
        });
        controlsWrapper.querySelector('.font-family-select')?.addEventListener('change', (e) => {
            let target = block.querySelector('h1, p');
            if (!target) target = block.querySelector('.editable-button');
            if (target) target.style.fontFamily = e.target.value;
        });
        controlsWrapper.querySelectorAll('.align-btn').forEach(btn => {
            btn.onclick = () => {
                let target = block.querySelector('h1, p');
                if (!target) target = block.querySelector('.button-wrapper');
                if (target) target.style.textAlign = btn.dataset.align;
            };
        });
        controlsWrapper.querySelector('.color-picker-trigger-btn')?.addEventListener('click', () => {
            targetTextElementForColor = null; 
            targetButtonForColor = null;
            if(block.dataset.type === 'button') {
                targetButtonForColor = block.querySelector('.editable-button');
                const currentColor = window.getComputedStyle(targetButtonForColor).color;
                textColorPicker.value = rgbToHex(currentColor) || '#FFFFFF';
            } else {
                targetTextElementForColor = block.querySelector('h1, p');
                const currentColor = window.getComputedStyle(targetTextElementForColor).color;
                textColorPicker.value = rgbToHex(currentColor) || '#000000';
            }
            textColorPicker.click();
        });
        controlsWrapper.querySelector('.bg-color-picker-trigger-btn')?.addEventListener('click', () => {
            targetBlockForBg = null; 
            targetButtonForBg = null;
            if(block.dataset.type === 'button') {
                targetButtonForBg = block.querySelector('.editable-button');
                const currentBgColor = window.getComputedStyle(targetButtonForBg).backgroundColor;
                blockBgPicker.value = rgbToHex(currentBgColor) || '#ff8c42';
            } else {
                targetBlockForBg = block; 
                const currentBgColor = window.getComputedStyle(targetBlockForBg).backgroundColor;
                blockBgPicker.value = rgbToHex(currentBgColor) || '#FFFFFF';
            }
            blockBgPicker.click();
        });
        controlsWrapper.querySelector('.style-btn')?.addEventListener('click', () => {
            targetElementForStyling = block;
            openStyleModal();
        });
        
        const videoInput = block.querySelector('.video-url-input');
        if (videoInput) {
            const handler = () => embedVideo(videoInput, block.querySelector('.video-content-wrapper'));
            videoInput.addEventListener('blur', handler);
            videoInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') handler(); });
        }
        const imageInput = block.querySelector('.image-url-input');
        if (imageInput) {
            const handler = () => embedImageUrl(imageInput, block.querySelector('.image-content-wrapper'));
            imageInput.addEventListener('blur', handler);
            imageInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') handler(); });
        }
        const mediaWrapper = block.querySelector('.video-content-wrapper, .image-content-wrapper, .image-upload-wrapper');
        if (mediaWrapper) {
            controlsWrapper.querySelectorAll('.media-align-btn').forEach(btn => {
                btn.onclick = () => { mediaWrapper.style.textAlign = btn.dataset.align; };
            });
            controlsWrapper.querySelectorAll('.media-width-btn').forEach(btn => {
                btn.onclick = () => {
                    const target = mediaWrapper.querySelector('iframe, figure');
                    if (target) changeMediaWidth(target, btn.dataset.action);
                };
            });
        }
        
        controlsWrapper.querySelector('#trigger-image-upload')?.addEventListener('click', () => {
            const wrapper = block.querySelector('.image-upload-wrapper');
            if (!wrapper) return;
            
            genericFileUploader.accept = 'image/*';
            currentUploadCallback = (file) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    wrapper.innerHTML = `<figure style="width: 100%;"><img src="${e.target.result}" alt="Загруженное изображение"><figcaption contenteditable="true">Добавьте подпись...</figcaption></figure>`;
                };
                reader.readAsDataURL(file);
            };
            genericFileUploader.click();
        });

        const addGalleryBtn = block.querySelector('.add-gallery-image-btn');
        if (addGalleryBtn) {
            const track = block.querySelector('.gallery-track');
            const prevBtn = block.querySelector('.gallery-nav-btn.prev');
            const nextBtn = block.querySelector('.gallery-nav-btn.next');

            addGalleryBtn.addEventListener('click', () => {
                genericFileUploader.accept = 'image/*';
                currentUploadCallback = (file) => {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const newImage = createGalleryImage(e.target.result, block);
                        track.appendChild(newImage);
                        updateSlider(block);
                    };
                    reader.readAsDataURL(file);
                };
                genericFileUploader.click();
            });
            
            prevBtn.addEventListener('click', () => {
                let index = parseInt(block.dataset.currentIndex || 0);
                if (index > 0) {
                    block.dataset.currentIndex = index - 1;
                    updateSlider(block);
                }
            });
            nextBtn.addEventListener('click', () => {
                let index = parseInt(block.dataset.currentIndex || 0);
                const total = block.querySelectorAll('.gallery-image-wrapper').length;
                if (index < total - 1) {
                    block.dataset.currentIndex = index + 1;
                    updateSlider(block);
                }
            });
            updateSlider(block);
        }

        controlsWrapper.querySelector('#trigger-file-download')?.addEventListener('click', () => {
            const wrapper = block.querySelector('.file-download-wrapper');
            if (!wrapper) return;
            
            genericFileUploader.accept = '';
            currentUploadCallback = (file) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const dataUrl = e.target.result;
                    const fileName = file.name;
                    wrapper.innerHTML = `
                        <a href="${dataUrl}" class="file-download-link" download="${fileName}">
                            <i class="fas fa-file-alt"></i>
                            ${fileName}
                        </a>
                    `;
                };
                reader.readAsDataURL(file);
            };
            genericFileUploader.click();
        });
        
        controlsWrapper.querySelector('#trigger-audio-upload')?.addEventListener('click', () => {
            const wrapper = block.querySelector('.audio-player-wrapper');
            if (!wrapper) return;
            
            genericFileUploader.accept = 'audio/mp3, audio/mpeg, audio/wav, audio/ogg';
            currentUploadCallback = (file) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const dataUrl = e.target.result;
                    const oldPlayer = wrapper.querySelector('audio');
                    const oldPlaceholder = wrapper.querySelector('.audio-player-placeholder');
                    if(oldPlayer) oldPlayer.remove();
                    if(oldPlaceholder) oldPlaceholder.remove();
                    
                    const audioEl = document.createElement('audio');
                    audioEl.controls = true;
                    audioEl.src = dataUrl;
                    wrapper.appendChild(audioEl);
                };
                reader.readAsDataURL(file);
            };
            genericFileUploader.click();
        });

        block.querySelectorAll('.table-control-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const table = block.querySelector('.editable-table');
                if (!table) return;
                const action = btn.dataset.action;
                const header = table.tHead;
                const body = table.tBodies[0];
                const colCount = header.rows[0].cells.length;

                if (action === 'add-row') {
                    const newRow = body.insertRow(); 
                    for (let i = 0; i < colCount; i++) {
                        const newCell = newRow.insertCell();
                        newCell.innerHTML = "Ячейка";
                        newCell.contentEditable = "true";
                    }
                } else if (action === 'add-col') {
                    const newTh = document.createElement('th');
                    newTh.innerHTML = "Заголовок";
                    newTh.contentEditable = "true";
                    header.rows[0].appendChild(newTh);
                    
                    for (const row of body.rows) {
                        const newCell = row.insertCell();
                        newCell.innerHTML = "Ячейка";
                        newCell.contentEditable = "true";
                    }
                } else if (action === 'del-row') {
                    if (body.rows.length > 1) { 
                        body.deleteRow(-1); 
                    } else {
                        alert("Нельзя удалить последний ряд.");
                    }
                } else if (action === 'del-col') {
                    if (colCount > 1) { 
                        header.rows[0].deleteCell(-1); 
                        for (const row of body.rows) {
                            row.deleteCell(-1);
                        }
                    } else {
                        alert("Нельзя удалить последнюю колонку.");
                    }
                }
            });
        });

        controlsWrapper.querySelector('.mandatory-btn')?.addEventListener('click', () => {
            block.classList.toggle('is-mandatory');
            const title = block.querySelector('h4');
            if (title) {
                title.querySelector('.mandatory-indicator')?.remove();
                if (block.classList.contains('is-mandatory')) {
                    title.insertAdjacentHTML('beforeend', ' <span class="mandatory-indicator">*</span>');
                }
            }
        });
        
        block.querySelector('.add-option-btn')?.addEventListener('click', (e) => {
            const mcqContainer = block.querySelector('.options-container');
            const seqContainer = block.querySelector('.sequence-options-container');
            const matchContainer = block.querySelector('.matching-options-container');

            if (mcqContainer) { // Это MCQ или Checkbox
                const qType = mcqContainer.dataset.qtype === 'question_mcq' ? 'radio' : 'checkbox';
                const name = block.dataset.questionId;
                addEditableOption(mcqContainer, qType, name, 'Новый вариант');
            } else if (seqContainer) { // Это Задание на Последовательность
                addSequenceOption(seqContainer, 'Новый элемент');
            } else if (matchContainer) { // Это Задание на Соответствие
                addMatchingPair(matchContainer, 'Термин', 'Определение');
            }
        });

        const seqContainer = block.querySelector('.sequence-options-container');
        if (seqContainer) {
            addSequenceContainerDropEvents(seqContainer);
        }
        
        const matchContainer = block.querySelector('.matching-options-container');
        if (matchContainer) {
            addSequenceContainerDropEvents(matchContainer); 
        }
    }
    
    function createGalleryImage(src, galleryBlock) { 
        const wrapper = document.createElement('div');
        wrapper.className = 'gallery-image-wrapper';
        wrapper.innerHTML = `
            <img src="${src}" alt="Gallery image">
            <button class="gallery-image-delete" title="Удалить">&times;</button>
        `;
        wrapper.querySelector('.gallery-image-delete').addEventListener('click', () => {
            wrapper.remove();
            updateSlider(galleryBlock); 
        });
        return wrapper;
    }

    function updateSlider(galleryBlock) {
        const track = galleryBlock.querySelector('.gallery-track');
        const prevBtn = galleryBlock.querySelector('.gallery-nav-btn.prev');
        const nextBtn = galleryBlock.querySelector('.gallery-nav-btn.next');
        const dotsContainer = galleryBlock.querySelector('.gallery-dots-container');
        const slides = galleryBlock.querySelectorAll('.gallery-image-wrapper');
        const totalSlides = slides.length;
        let currentIndex = parseInt(galleryBlock.dataset.currentIndex || 0);

        if (currentIndex >= totalSlides && totalSlides > 0) {
            currentIndex = totalSlides - 1;
            galleryBlock.dataset.currentIndex = currentIndex;
        }

        if (totalSlides === 0) {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
            dotsContainer.innerHTML = '';
            track.style.transform = 'translateX(0%)'; 
            return;
        }

        prevBtn.style.display = (totalSlides > 1) ? 'block' : 'none';
        nextBtn.style.display = (totalSlides > 1) ? 'block' : 'none';
        prevBtn.disabled = (currentIndex === 0);
        nextBtn.disabled = (currentIndex === totalSlides - 1);
        track.style.transform = `translateX(-${currentIndex * 100}%)`;

        dotsContainer.innerHTML = '';
        if (totalSlides > 1) {
            for (let i = 0; i < totalSlides; i++) {
                const dot = document.createElement('button');
                dot.className = 'gallery-dot';
                if (i === currentIndex) dot.classList.add('active');
                dot.dataset.index = i;
                dot.onclick = () => {
                    galleryBlock.dataset.currentIndex = i;
                    updateSlider(galleryBlock);
                };
                dotsContainer.appendChild(dot);
            }
        }
    }
    
    function changeFontSize(targetElement, action) {
        let currentSize = window.getComputedStyle(targetElement).fontSize;
        if (!currentSize) currentSize = '16px'; 
        let newSize = parseFloat(currentSize);
        if (action === 'increase') newSize += 2;
        else if (action === 'decrease') newSize = Math.max(10, newSize - 2); 
        targetElement.style.fontSize = `${newSize}px`;
    }
    
    function addEditableOption(container, qType, name, text) {
        const li = document.createElement('li');
        li.className = 'question-option';
        li.innerHTML = `
            <input type="${qType}" name="${name}" title="Отметить как правильный ответ">
            <span class="option-text" contenteditable="true">${text}</span>
            <span class="delete-option-btn" title="Удалить">&times;</span>
        `;
        li.querySelector('.delete-option-btn').addEventListener('click', () => {
            li.remove();
        });
        container.appendChild(li);
    }

    function addSequenceOption(container, text) {
        const li = document.createElement('li');
        li.className = 'sequence-option';
        li.setAttribute('draggable', 'true');
        li.innerHTML = `
            <span class="sequence-drag-handle" title="Перетащить">::</span>
            <span class="option-text" contenteditable="true">${text}</span>
            <span class="delete-option-btn" title="Удалить">&times;</span>
        `;
        li.querySelector('.delete-option-btn').addEventListener('click', () => {
            li.remove();
        });
        addSequenceDragEvents(li);
        container.appendChild(li);
    }

    function addMatchingPair(container, leftText, rightText) {
        const li = document.createElement('li');
        li.className = 'matching-option';
        li.setAttribute('draggable', 'true');
        li.innerHTML = `
            <span class="matching-drag-handle" title="Перетащить">::</span>
            <span class="matching-text" contenteditable="true">${leftText}</span>
            <span class="matching-separator">↔</span>
            <span class="matching-text" contenteditable="true">${rightText}</span>
            <span class="delete-option-btn" title="Удалить">&times;</span>
        `;
        li.querySelector('.delete-option-btn').addEventListener('click', () => {
            li.remove();
        });
        addSequenceDragEvents(li);
        container.appendChild(li);
    }
    
    function changeMediaWidth(targetElement, action) {
        let currentWidth = targetElement.style.width || '100%';
        let newWidth = parseFloat(currentWidth);
        if (action === 'increase') newWidth = Math.min(100, newWidth + 10);
        else if (action === 'decrease') newWidth = Math.max(20, newWidth - 10); 
        targetElement.style.width = `${newWidth}%`;
        if (targetElement.tagName === 'IFRAME') targetElement.style.aspectRatio = '16 / 9';
        else targetElement.style.height = 'auto';
    }
    
    function embedVideo(inputElement, wrapper) {
        const url = inputElement.value;
        const embedUrl = parseVideoURL(url);
        if (embedUrl) {
            wrapper.innerHTML = `<iframe src="${embedUrl}" style="width: 100%; aspect-ratio: 16/9; border-radius: 8px;" frameborder="0" allow="autoplay; fullscreen; picture-in-picture"></iframe>`;
            inputElement.parentElement.style.display = 'none'; 
            wrapper.style.width = '100%'; 
        } else if (url.trim() !== '') {
            alert('Не удалось распознать URL. Поддерживаются YouTube и Vimeo.');
        }
    }
    
    function embedImageUrl(inputElement, wrapper) {
        const url = inputElement.value;
        if (url) {
            wrapper.innerHTML = `<figure style="width: 100%;"><img src="${url}" alt="Изображение по URL"><figcaption contenteditable="true">Добавьте подпись...</figcaption></figure>`;
            inputElement.parentElement.style.display = 'none'; 
        }
    }
    
    function parseVideoURL(url) {
        let embedUrl = null;
        try {
            const urlObj = new URL(url);
            if (urlObj.hostname.includes('youtube.com') || urlObj.hostname.includes('youtu.be')) {
                let videoId = urlObj.searchParams.get('v');
                if (urlObj.hostname.includes('youtu.be')) videoId = urlObj.pathname.slice(1);
                embedUrl = `https://www.youtube.com/embed/${videoId}`;
            }
            else if (urlObj.hostname.includes('vimeo.com')) {
                const videoId = urlObj.pathname.split('/').pop();
                embedUrl = `https://player.vimeo.com/video/${videoId}`;
            }
        } catch (e) { console.error("Invalid URL:", e); }
        return embedUrl;
    }

    // ============================================================
    // 5. ЛОГИКА DRAG & DROP
    // ============================================================
    contentPanel.querySelectorAll('.tool-draggable').forEach(tool => {
        tool.addEventListener('dragstart', (e) => {
            draggingElement = tool; dragType = 'tool'; toolType = tool.dataset.type;
        });
        tool.addEventListener('dragend', dragCleanup);
    });
    
    function addDragEventsToDraggable(element) {
        element.addEventListener('dragstart', (e) => {
            e.stopPropagation(); 
            draggingElement = element;
            dragType = element.dataset.dragType; 
            toolType = null;
            setTimeout(() => element.classList.add('dragging'), 0);
        });
        element.addEventListener('dragend', dragCleanup);
    }
    
    function addDropzoneEvents(zone) {
        const accepts = zone.dataset.accepts; 
        zone.addEventListener('dragover', (e) => {
            e.preventDefault(); e.stopPropagation();
            const isValidDrop = (dragType === 'tool' && accepts === 'content') || (dragType === accepts);
            if (isValidDrop) {
                zone.classList.add('drag-over-active');
                const afterElement = getDragAfterElement(zone, e.clientY);
                if (afterElement) zone.insertBefore(dragPlaceholder, afterElement);
                else zone.appendChild(dragPlaceholder);
            }
        });
        zone.addEventListener('dragleave', (e) => {
            e.stopPropagation();
            zone.classList.remove('drag-over-active');
        });
        zone.addEventListener('drop', (e) => {
            e.preventDefault(); e.stopPropagation();
            const isValidDrop = (dragType === 'tool' && accepts === 'content') || (dragType === accepts);
            if (isValidDrop && dragPlaceholder.parentElement) {
                const targetZone = dragPlaceholder.parentElement;
                const afterElement = dragPlaceholder.nextElementSibling;
                if (dragType === 'tool') {
                    const newBlock = createContentBlock(toolType);
                    if (newBlock) targetZone.insertBefore(newBlock, afterElement);
                } else {
                    targetZone.insertBefore(draggingElement, afterElement);
                }
            }
            dragCleanup();
        });
    }
    
    function dragCleanup() {
        draggingElement?.classList.remove('dragging');
        dragPlaceholder.remove();
        document.querySelectorAll('.drag-over-active').forEach(z => z.classList.remove('drag-over-active'));
        draggingElement = null; dragType = null; toolType = null;
        checkPlaceholders(); 
    }
    
    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll(':scope > .content-block, :scope > .content-section')];
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) return { offset: offset, element: child };
            else return closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function addSequenceDragEvents(item) {
        item.addEventListener('dragstart', (e) => {
            e.stopPropagation();
            draggingSequenceItem = item;
            setTimeout(() => item.classList.add('dragging'), 0);
        });
    
        item.addEventListener('dragend', (e) => {
            e.stopPropagation();
            draggingSequenceItem?.classList.remove('dragging');
            draggingSequenceItem = null;
        });
    }
    
    function addSequenceContainerDropEvents(container) {
        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (!draggingSequenceItem || draggingSequenceItem.parentElement !== container) return;

            const afterElement = getSequenceDragAfterElement(container, e.clientY);
            if (afterElement) {
                container.insertBefore(draggingSequenceItem, afterElement);
            } else {
                container.appendChild(draggingSequenceItem);
            }
        });
    }
    
    function getSequenceDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.sequence-option:not(.dragging), .matching-option:not(.dragging)')];
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }


    // ============================================================
    // 6. ИНИЦИАЛИЗАЦИЯ ИНСТРУМЕНТОВ
    // ============================================================
    addDropzoneEvents(pageFrame);
    checkPlaceholders(); 
    initRichTextToolbar();

    function initRichTextToolbar() {
        pageFrame.addEventListener('mouseup', (e) => {
            setTimeout(() => { 
                const selection = window.getSelection();
                if (selection && !selection.isCollapsed) {
                    const range = selection.getRangeAt(0);
                    const target = range.commonAncestorContainer;
                    const targetElement = (target.nodeType === 3) ? target.parentElement : target;
                    const validParent = targetElement.closest('h1, p, blockquote, h4, .option-text, .matching-text, figcaption');
                    
                    if (validParent && validParent.isContentEditable) {
                        const rect = range.getBoundingClientRect();
                        richTextToolbar.style.display = 'flex';
                        richTextToolbar.style.top = `${window.scrollY + rect.top - richTextToolbar.offsetHeight - 8}px`;
                        richTextToolbar.style.left = `${window.scrollX + rect.left + (rect.width / 2) - (richTextToolbar.offsetWidth / 2)}px`;
                    } else {
                        richTextToolbar.style.display = 'none';
                    }
                } else {
                    richTextToolbar.style.display = 'none';
                }
            }, 10);
        });

        document.addEventListener('mousedown', (e) => {
            if (!e.target.closest('#rich-text-toolbar')) {
                setTimeout(() => {
                    if (document.activeElement !== e.target) {
                         richTextToolbar.style.display = 'none';
                    }
                }, 150);
            }
        });

        richTextToolbar.querySelectorAll('.rich-text-btn').forEach(btn => {
            btn.addEventListener('mousedown', (e) => { 
                e.preventDefault();
                const command = btn.dataset.command;
                let value = null;
                if (command === 'createLink') {
                    value = prompt('Введите URL:', 'https://');
                    if (value === null || value === 'https://' || value === '') return;
                }
                document.execCommand(command, false, value);
            });
        });
    }

    function openStyleModal() {
        if (!targetElementForStyling) return;
        const styles = window.getComputedStyle(targetElementForStyling);
        styleInputs.paddingTop.value = parseInt(styles.paddingTop) || 0;
        styleInputs.paddingRight.value = parseInt(styles.paddingRight) || 0;
        styleInputs.paddingBottom.value = parseInt(styles.paddingBottom) || 0;
        styleInputs.paddingLeft.value = parseInt(styles.paddingLeft) || 0;
        styleInputs.borderWidth.value = parseInt(styles.borderWidth) || 0;
        styleInputs.borderStyle.value = styles.borderStyle || 'none';
        const borderColor = styles.borderColor || '#000000';
        borderColorPicker.value = rgbToHex(borderColor);
        styleInputs.borderColorBtn.style.backgroundColor = borderColor;
        styleInputs.borderRadius.value = parseInt(styles.borderRadius) || 0;
        styleInputs.boxShadow.value = styles.boxShadow || 'none';
        styleModal.classList.add('active');
        styleModalBackdrop.classList.add('active');
    }
    function closeStyleModal() {
        styleModal.classList.remove('active');
        styleModalBackdrop.classList.remove('active');
        targetElementForStyling = null;
    }
    styleModalClose.addEventListener('click', closeStyleModal);
    styleModalBackdrop.addEventListener('click', closeStyleModal);
    
    styleInputs.paddingTop.addEventListener('input', (e) => { if(targetElementForStyling) targetElementForStyling.style.paddingTop = `${e.target.value}px`; });
    styleInputs.paddingRight.addEventListener('input', (e) => { if(targetElementForStyling) targetElementForStyling.style.paddingRight = `${e.target.value}px`; });
    styleInputs.paddingBottom.addEventListener('input', (e) => { if(targetElementForStyling) targetElementForStyling.style.paddingBottom = `${e.target.value}px`; });
    styleInputs.paddingLeft.addEventListener('input', (e) => { if(targetElementForStyling) targetElementForStyling.style.paddingLeft = `${e.target.value}px`; });
    styleInputs.borderWidth.addEventListener('input', (e) => { if(targetElementForStyling) targetElementForStyling.style.borderWidth = `${e.target.value}px`; });
    styleInputs.borderStyle.addEventListener('input', (e) => { if(targetElementForStyling) targetElementForStyling.style.borderStyle = e.target.value; });
    styleInputs.borderRadius.addEventListener('input', (e) => { if(targetElementForStyling) targetElementForStyling.style.borderRadius = `${e.target.value}px`; });
    styleInputs.boxShadow.addEventListener('input', (e) => { if(targetElementForStyling) targetElementForStyling.style.boxShadow = e.target.value; });
    styleInputs.borderColorBtn.addEventListener('click', () => { borderColorPicker.click(); });
    

    // ============================================================
    // 7. ЛОГИКА СОХРАНЕНИЯ (НОВАЯ)
    // ============================================================
    
    const saveBtn = document.getElementById('save-btn'); 
    
    const confirmModal = document.getElementById('save-confirm-modal');
    const confirmBackdrop = document.getElementById('save-confirm-backdrop');
    const confirmYesBtn = document.getElementById('confirm-save-yes');
    const confirmNoBtn = document.getElementById('confirm-save-no');

    const successModal = document.getElementById('save-success-modal');
    const successBackdrop = document.getElementById('save-success-backdrop');
    const timerSpan = document.getElementById('redirect-timer');

    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            confirmModal.classList.add('active');
            confirmBackdrop.classList.add('active');
        });
    }

    if (confirmNoBtn) {
        confirmNoBtn.addEventListener('click', () => {
            confirmModal.classList.remove('active');
            confirmBackdrop.classList.remove('active');
        });
    }

    if (confirmYesBtn) {
        confirmYesBtn.addEventListener('click', () => {
            const originalText = confirmYesBtn.innerText;
            confirmYesBtn.innerText = 'Сохранение...';
            confirmYesBtn.disabled = true;

            saveLessonData()
                .then(success => {
                    confirmModal.classList.remove('active');
                    confirmBackdrop.classList.remove('active');
                    confirmYesBtn.innerText = originalText;
                    confirmYesBtn.disabled = false;

                    if(success) {
                        showSuccessModal();
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Произошла ошибка при сохранении!');
                    confirmYesBtn.innerText = originalText;
                    confirmYesBtn.disabled = false;
                });
        });
    }

    // Функция отправки данных
    function saveLessonData() {
        return new Promise((resolve, reject) => {
            try {
                const pageData = serializeSections(pageFrame);
                const metadata = getPageMetadata();
                const payload = { id: currentPageId, blocks: pageData, meta: metadata };

                fetch('save_lesson.php', { 
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/json' }, 
                    body: JSON.stringify(payload) 
                })
                .then(response => response.text()) // 1. Получаем как текст
                .then(text => {
                    console.log("Raw server response:", text); // Для отладки
                    
                    // 2. Ищем JSON внутри текста (игнорируем PHP Warnings до и после)
                    const jsonStart = text.indexOf('{');
                    const jsonEnd = text.lastIndexOf('}');
                    
                    if (jsonStart === -1 || jsonEnd === -1) {
                        throw new Error("Сервер вернул не JSON. Ответ: " + text.substring(0, 50) + "...");
                    }
                    
                    const cleanJson = text.substring(jsonStart, jsonEnd + 1);
                    return JSON.parse(cleanJson); // 3. Парсим чистый JSON
                })
                .then(data => {
                    if (data.success) {
                        if (!currentPageId && data.id) { 
                            currentPageId = data.id; 
                            const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?id=' + data.id;
                            window.history.pushState({},'', newUrl); 
                        }
                        resolve(true);
                    } else {
                        alert('Ошибка сервера: ' + data.message);
                        resolve(false);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Критическая ошибка сохранения: ' + err.message);
                    reject(err);
                });
            } catch (e) { reject(e); }
        });
    }

    // Функция показа успеха с таймером
    function showSuccessModal() {
        successModal.classList.add('active');
        successBackdrop.classList.add('active');
        
        let timeLeft = 3;
        timerSpan.innerText = timeLeft;

        const interval = setInterval(() => {
            timeLeft--;
            timerSpan.innerText = timeLeft;
            if(timeLeft <= 0) {
                clearInterval(interval);
                window.location.href = 'my_lessons.php';
            }
        }, 1000);
    }

    // Сериализация (Сборка данных со страницы)
    function serializeSections(container) {
        const sectionsData = [];
        container.querySelectorAll(':scope > .content-section').forEach((section, index) => {
            const columns = section.querySelectorAll('.column-dropzone');
            const sectionData = {
                type: 'section',
                order: index,
                styles: { 
                    backgroundColor: section.style.backgroundColor || 'default',
                    padding: section.style.padding || 'default',
                    borderWidth: section.style.borderWidth || 'default',
                    borderStyle: section.style.borderStyle || 'default',
                    borderColor: section.style.borderColor || 'default',
                    borderRadius: section.style.borderRadius || 'default',
                    boxShadow: section.style.boxShadow || 'default',
                },
                columns: []
            };
            columns.forEach((column, colIndex) => {
                sectionData.columns[colIndex] = serializeContentBlocks(column);
            });
            sectionsData.push(sectionData);
        });
        return sectionsData;
    }
    
    function serializeContentBlocks(column) {
        const blocksData = [];
        column.querySelectorAll(':scope > .content-block').forEach((block, index) => {
            const blockData = {
                type: block.dataset.type,
                order: index,
                content: {},
                styles: { 
                    backgroundColor: block.style.backgroundColor || 'default',
                    padding: block.style.padding || 'default',
                    borderWidth: block.style.borderWidth || 'default',
                    borderStyle: block.style.borderStyle || 'default',
                    borderColor: block.style.borderColor || 'default',
                    borderRadius: block.style.borderRadius || 'default',
                    boxShadow: block.style.boxShadow || 'default',
                }
            };
            
            const textEl = block.querySelector('h1, p');
            if (textEl) {
                blockData.styles.align = textEl.style.textAlign || 'left';
                blockData.styles.fontSize = textEl.style.fontSize || 'default';
                blockData.styles.color = textEl.style.color || 'default';
                blockData.styles.fontFamily = textEl.style.fontFamily || 'default'; 
            }
            
            switch(blockData.type) {
                case 'heading': case 'text':
                    blockData.content.text = textEl?.innerHTML;
                    break;
                case 'quote':
                    blockData.content.text = block.querySelector('blockquote')?.innerHTML;
                    break;
                case 'image_upload': case 'image_url': case 'video':
                    const wrapper = block.querySelector('.video-content-wrapper, .image-content-wrapper, .image-upload-wrapper');
                    const mediaEl = wrapper?.querySelector('iframe, figure');
                    blockData.styles.align = wrapper?.style.textAlign || 'left';
                    blockData.styles.width = mediaEl?.style.width || '100%';
                    if (blockData.type === 'video') blockData.content.src = mediaEl?.src;
                    else {
                        blockData.content.src = mediaEl?.querySelector('img')?.src;
                        blockData.content.caption = mediaEl?.querySelector('figcaption')?.innerHTML;
                    }
                    break;
                case 'gallery':
                    blockData.content.images = [];
                    block.querySelectorAll('.gallery-track .gallery-image-wrapper img').forEach(img => {
                        if (img) {
                            blockData.content.images.push(img.src);
                        }
                    });
                    break;
                case 'button':
                    const btnEl = block.querySelector('.editable-button');
                    const wrapperEl = block.querySelector('.button-wrapper');
                    blockData.content.text = btnEl?.innerHTML;
                    blockData.content.url = block.querySelector('.button-url-input')?.value;
                    blockData.styles.align = wrapperEl?.style.textAlign || 'left';
                    blockData.styles.backgroundColor = btnEl?.style.backgroundColor || 'default';
                    blockData.styles.color = btnEl?.style.color || 'default';
                    blockData.styles.fontSize = btnEl?.style.fontSize || 'default';
                    blockData.styles.fontFamily = btnEl?.style.fontFamily || 'default';
                    break;
                case 'file_download':
                    const link = block.querySelector('.file-download-link');
                    blockData.content.title = block.querySelector('h4')?.innerHTML;
                    blockData.content.href = link ? link.href : '';
                    blockData.content.fileName = link ? link.download : '';
                    break;
                case 'file_submission':
                    const subTitle = block.querySelector('h4')?.innerText;
                    blockData.content.question = subTitle ? subTitle.replace(' *', '') : '';
                    blockData.content.isMandatory = block.classList.contains('is-mandatory');
                    break;
                case 'audio_player':
                    const audioEl = block.querySelector('audio');
                    blockData.content.title = block.querySelector('h4')?.innerHTML;
                    blockData.content.src = audioEl ? audioEl.src : ''; 
                    break;
                case 'table':
                    const tableEl = block.querySelector('.editable-table');
                    blockData.content.html = tableEl ? tableEl.innerHTML : '';
                    break;
                case 'contact_form':
                    blockData.content.title = block.querySelector('h4')?.innerHTML;
                    break;
                case 'question_mcq':
                case 'question_checkbox':
                case 'question_text':
                case 'question_essay':
                    const qTitle = block.querySelector('h4')?.innerText;
                    blockData.content.question = qTitle ? qTitle.replace(' *', '') : '';
                    blockData.content.isMandatory = block.classList.contains('is-mandatory');
                    if(blockData.type === 'question_mcq' || blockData.type === 'question_checkbox') {
                        blockData.content.options = [];
                        blockData.content.correctAnswers = [];
                        block.querySelectorAll('.question-option').forEach((opt, optIndex) => {
                            blockData.content.options.push(opt.querySelector('.option-text').innerHTML);
                            if (opt.querySelector('input').checked) {
                                blockData.content.correctAnswers.push(optIndex);
                            }
                        });
                    }
                    break;
                case 'question_sequence':
                    const qSeqTitle = block.querySelector('h4')?.innerText;
                    blockData.content.question = qSeqTitle ? qSeqTitle.replace(' *', '') : '';
                    blockData.content.isMandatory = block.classList.contains('is-mandatory');
                    blockData.content.options = [];
                    // Сбор опций (без :first-of-type, просто класс)
                    block.querySelectorAll('.sequence-option .option-text').forEach((opt) => {
                        blockData.content.options.push(opt.innerHTML);
                    });
                    break;
                case 'question_matching':
                    const qMatchTitle = block.querySelector('h4')?.innerText;
                    blockData.content.question = qMatchTitle ? qMatchTitle.replace(' *', '') : '';
                    blockData.content.isMandatory = block.classList.contains('is-mandatory');
                    blockData.content.pairs = [];
                    
                    // === ИСПРАВЛЕННАЯ ЛОГИКА СБОРА ДЛЯ MATCHING ===
                    block.querySelectorAll('.matching-option').forEach((pair) => {
                        const textFields = pair.querySelectorAll('.matching-text');
                        // Берем по индексу 0 и 1, игнорируя другие спаны (drag handle, delete btn)
                        if (textFields.length >= 2) {
                            blockData.content.pairs.push({
                                left: textFields[0].innerHTML,
                                right: textFields[1].innerHTML
                            });
                        }
                    });
                    break;
                case 'timer':
                    const timerInput = block.querySelector('.timer-block-input');
                    blockData.content.minutes = timerInput ? parseInt(timerInput.value, 10) : 0;
                    blockData.styles = { padding: '0', border: 'none', background: 'none', boxShadow: 'none' };
                    break;
            }
            blocksData.push(blockData);
        });
        return blocksData;
    }
    
    // ============================================================
    // 8. АВТОЗАГРУЗКА И ИМПОРТ
    // ============================================================
    if (currentPageId) {
        fetch(`get_lesson.php?id=${currentPageId}`) 
            .then(res => res.json())
            .then(response => {
                if (response.success && response.data) {
                    pageFrame.innerHTML = ''; 
                    renderPageFromJSON(response.data.blocks);
                    fillMetadata(response.data.meta);
                    checkPlaceholders();
                }
            })
            .catch(err => console.error("Ошибка загрузки страницы:", err));
    }

    function renderPageFromJSON(sectionsData) {
        if (!Array.isArray(sectionsData)) return;

        sectionsData.forEach(secData => {
            const colCount = secData.columns ? secData.columns.length : 1;
            const section = createSectionBlock(colCount);

            if (secData.styles) applyStyles(section, secData.styles);

            const dropzones = section.querySelectorAll('.column-dropzone');
            secData.columns.forEach((colBlocks, index) => {
                const targetZone = dropzones[index];
                if (targetZone && Array.isArray(colBlocks)) {
                    colBlocks.forEach(blockData => {
                        const block = createContentBlock(blockData.type);
                        
                        if (blockData.styles) {
                            applyStyles(block, blockData.styles);
                            const textEl = block.querySelector('h1, p, h4, .editable-button');
                            if (textEl) {
                                if(blockData.styles.fontSize !== 'default') textEl.style.fontSize = blockData.styles.fontSize;
                                if(blockData.styles.color !== 'default') textEl.style.color = blockData.styles.color;
                                if(blockData.styles.fontFamily !== 'default') textEl.style.fontFamily = blockData.styles.fontFamily;
                                if(blockData.styles.align) {
                                     if(blockData.type === 'button') block.querySelector('.button-wrapper').style.textAlign = blockData.styles.align;
                                     else textEl.style.textAlign = blockData.styles.align;
                                }
                            }
                        }
                        fillBlockContent(block, blockData);
                        targetZone.appendChild(block);
                    });
                }
            });
            pageFrame.appendChild(section);
        });
    }

    function applyStyles(element, styles) {
        if (styles.backgroundColor && styles.backgroundColor !== 'default') element.style.backgroundColor = styles.backgroundColor;
        if (styles.padding && styles.padding !== 'default') element.style.padding = styles.padding;
        if (styles.borderWidth && styles.borderWidth !== 'default') element.style.borderWidth = styles.borderWidth;
        if (styles.borderStyle && styles.borderStyle !== 'default') element.style.borderStyle = styles.borderStyle;
        if (styles.borderColor && styles.borderColor !== 'default') element.style.borderColor = styles.borderColor;
        if (styles.borderRadius && styles.borderRadius !== 'default') element.style.borderRadius = styles.borderRadius;
        if (styles.boxShadow && styles.boxShadow !== 'default') element.style.boxShadow = styles.boxShadow;
    }

    function fillBlockContent(block, data) {
        const content = data.content;
        if (!content) return;

        switch (data.type) {
            case 'heading': 
            case 'text':
                if (content.text) block.querySelector('h1, p').innerHTML = content.text;
                break;
            case 'quote':
                if (content.text) block.querySelector('blockquote').innerHTML = content.text;
                break;
            case 'image_url':
            case 'image_upload':
                if (content.src) {
                    const wrapper = block.querySelector('.image-content-wrapper, .image-upload-wrapper');
                    wrapper.innerHTML = `<figure style="width:${data.styles.width || '100%'}; text-align:${data.styles.align || 'left'}"><img src="${content.src}"><figcaption contenteditable="true">${content.caption || ''}</figcaption></figure>`;
                    const input = block.querySelector('.url-embed-ui, .image-upload-placeholder');
                    if(input && input.parentElement) input.parentElement.style.display = 'none';
                }
                break;
            case 'video':
                if (content.src) {
                     const wrapper = block.querySelector('.video-content-wrapper');
                     wrapper.innerHTML = `<iframe src="${content.src}" style="width: ${data.styles.width || '100%'}; aspect-ratio: 16/9;" frameborder="0" allowfullscreen></iframe>`;
                     block.querySelector('.url-embed-ui').style.display = 'none';
                }
                break;
            case 'button':
                if (content.text) block.querySelector('.editable-button').innerHTML = content.text;
                if (content.url) block.querySelector('.button-url-input').value = content.url;
                break;
            case 'file_download':
                if (content.title) block.querySelector('h4').innerHTML = content.title;
                if (content.href) {
                     block.querySelector('.file-download-wrapper').innerHTML = `
                        <a href="${content.href}" class="file-download-link" download="${content.fileName}">
                            <i class="fas fa-file-alt"></i> ${content.fileName}
                        </a>`;
                }
                break;
            case 'gallery':
                if (content.images && Array.isArray(content.images)) {
                    const track = block.querySelector('.gallery-track');
                    content.images.forEach(src => {
                        const newImage = createGalleryImage(src, block);
                        track.appendChild(newImage);
                    });
                    updateSlider(block);
                }
                break;
            case 'table':
                if (content.html) {
                    const table = block.querySelector('.editable-table');
                    if(table) table.innerHTML = content.html;
                }
                break;
            case 'question_mcq':
            case 'question_checkbox':
                if (content.question) block.querySelector('h4').innerText = content.question;
                if (content.isMandatory) block.classList.add('is-mandatory');
                const optContainer = block.querySelector('.options-container');
                optContainer.innerHTML = ''; 
                if (content.options) {
                    const qType = data.type === 'question_mcq' ? 'radio' : 'checkbox';
                    content.options.forEach((optText, idx) => {
                         addEditableOption(optContainer, qType, block.dataset.questionId, optText);
                         if(content.correctAnswers && content.correctAnswers.includes(idx)) {
                             optContainer.lastElementChild.querySelector('input').checked = true;
                         }
                    });
                }
                break;
            case 'question_sequence':
                 if (content.question) block.querySelector('h4').innerText = content.question;
                 const seqContainer = block.querySelector('.sequence-options-container');
                 if (content.options) {
                     content.options.forEach(text => addSequenceOption(seqContainer, text));
                 }
                 break;
            case 'question_matching':
                if (content.question) block.querySelector('h4').innerText = content.question;
                const matchContainer = block.querySelector('.matching-options-container');
                if (content.pairs) {
                    content.pairs.forEach(pair => addMatchingPair(matchContainer, pair.left, pair.right));
                }
                break;
            case 'timer':
                if(content.minutes) block.querySelector('.timer-block-input').value = content.minutes;
                break;
        }
    }

    function importTestFromCSV(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const csvText = e.target.result;
            const newSection = createSectionBlock(1);
            const targetColumn = newSection.querySelector('.column-dropzone');
            pageFrame.appendChild(newSection);
            try {
                processCSVData(csvText, targetColumn);
                alert(`Импорт завершен! Добавлено ${targetColumn.children.length - 1} вопросов.`);
            } catch (err) {
                console.error("Ошибка парсинга CSV:", err);
                alert("Не удалось прочитать CSV. Ошибка: " + err.message);
                newSection.remove();
            }
            checkPlaceholders();
        };
        reader.readAsText(file);
    }
    
    function processCSVData(csvText, targetColumn) {
        const lines = csvText.trim().split(/\r?\n/); 
        if (lines.length === 0) throw new Error("Файл пуст.");
        for (const line of lines) {
            if (line.trim() === '') continue; 
            const columns = line.split(';'); 
            if (columns.length < 3) continue; 
            const type = columns[0].trim().toLowerCase();
            const questionText = columns[1].trim();
            let block;
            
            if (type === 'mcq') block = createContentBlock('question_mcq');
            else if (type === 'check') block = createContentBlock('question_checkbox');
            else if (type === 'text') block = createContentBlock('question_text');
            else if (type === 'essay') block = createContentBlock('question_essay');
            else continue; 

            const titleEl = block.querySelector('h4');
            if (titleEl) titleEl.innerText = questionText;
            
            if (type === 'mcq' || type === 'check') {
                const optionsContainer = block.querySelector('.options-container');
                const qType = (type === 'mcq') ? 'radio' : 'checkbox';
                const name = block.dataset.questionId;
                const options = columns.slice(2, -1); 
                const correctAnswers = columns[columns.length - 1].trim().split(','); 

                options.forEach((optionText, index) => {
                    const text = optionText.trim();
                    if (text === '') return; 
                    addEditableOption(optionsContainer, qType, name, text);
                    if (correctAnswers.includes(String(index + 1))) {
                        const lastOptionLI = optionsContainer.lastElementChild;
                        if(lastOptionLI) {
                             const input = lastOptionLI.querySelector('input');
                            if (input) input.checked = true;
                        }
                    }
                });
            }
            targetColumn.appendChild(block);
        }
    }
});