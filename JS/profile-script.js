document.addEventListener('DOMContentLoaded', () => {
    // === ЭЛЕМЕНТЫ ===
    const settingsBtn = document.querySelector('.settings-btn');
    const modal = document.querySelector('.settings-modal');
    const closeModalBtn = document.querySelector('.close-modal');
    const closeMenuBtn = document.querySelector('.close-menu-btn');
    const uploadBtn = document.querySelector('.upload-btn');
    const avatarInput = document.querySelector('#avatar-upload');
    const saveNameBtn = document.querySelector('.save-btn');
    const saveBorderBtn = document.querySelector('.save-border-btn');

    // Поля
    const newUsername = document.querySelector('#new-username');
    const newFullName = document.querySelector('#new-full-name');
    const newClass = document.querySelector('#new-class');
    const borderColorInput = document.querySelector('#border-color');

    // Превью в модалке (теперь это <div>)
    const modalAvatar = document.querySelector('#main-avatar-preview');
    const modalBorderPreview = document.querySelector('#avatar-preview');

    // === ГЛАВНАЯ СТРАНИЦА ===
    const profileUsername = document.querySelector('#profile-username');
    const headerUsername = document.querySelector('#header-username');
    const mainFullname = document.querySelector('#main-fullname');
    const mainClassElement = document.querySelector('#main-class');
    const mainAvatar = document.querySelector('.user-info .user-icon'); // Это <div> на главной

    // === НОВЫЕ ЭЛЕМЕНТЫ EFPREMIUM ===
    const allBorderCells = document.querySelectorAll('.border-cell');
    
    // ===================================

    // === ПЕРЕМЕННЫЕ СОСТОЯНИЯ ДЛЯ РАМКИ ===
    let selectedBorderStyle = modal.querySelector('.border-cell.selected')?.dataset.borderStyle || 'solid-default';
    let selectedBorderColor = modal.querySelector('.border-cell.selected')?.dataset.borderColor || borderColorInput.value;

    
    // ==================================================================
    // ============= ОБНОВЛЕННАЯ ФУНКЦИЯ JS (С РАМКАМИ) ==============
    // ==================================================================
    function applyBorderStyles(element, style, color, width = '4px') {
        if (!element) return;
        
        // 1. Сброс ВСЕХ стилей рамки, классов
        element.style.border = '';
        element.style.borderColor = '';
        element.style.borderWidth = '';
        element.style.borderStyle = '';
        element.style.borderImage = ''; 
        
        // === ИЗМЕНЕНИЕ 1: Сброс всех классов ===
        element.classList.remove('border-rgb');
        element.classList.remove('avatar-padded'); // <-- ГЛАВНЫЙ ФИКС (сброс отступа)
        
        // Сброс ВСЕХ классов рамок (используем RegEx для удаления 'border-frame-*')
        element.className = element.className.replace(/\bborder-frame-[^\s]+\b/g, '');

        
        // 2. Применение новых стилей
        
        if (style === 'rgb') {
            element.classList.add('border-rgb');
            element.style.borderWidth = width;
            element.style.borderStyle = 'solid';
            // НЕ добавляем 'avatar-padded'
            
        } else if (style === 'solid-default' && color) {
            // Это обычный сплошной цвет
            element.style.border = `${width} solid ${color}`;
            // НЕ добавляем 'avatar-padded'

        // === ИЗМЕНЕНИЕ 2: Блок для рамок-оверлеев (ловит все 'frame-') ===
        } else if (style.startsWith('frame-')) {
            element.style.border = '2px solid transparent'; // Тонкая рамка, чтобы ::after лег
            
            // Конвертируем 'frame-halloween' (style) в 'border-frame-halloween' (CSS class)
            const cssClass = 'border-' + style;
            element.classList.add(cssClass);
            
            element.classList.add('avatar-padded'); // <-- ГЛАВНЫЙ ФИКС (добавление отступа)
            
        } else {
             // Дефолт 
             element.style.border = `4px solid #ff8c42`;
             // НЕ добавляем 'avatar-padded'
        }
    }
    // ==================================================================
    // ============= КОНЕЦ ОБНОВЛЕННОЙ ФУНКЦИИ JS ========================
    // ==================================================================

    
    // === ОТКРЫТИE / ЗАКРЫТИE (Ваш код) ===
    settingsBtn?.addEventListener('click', () => {
        modal.classList.add('active');
    });

    [closeModalBtn, closeMenuBtn].forEach(btn => {
        btn?.addEventListener('click', () => {
            modal.classList.remove('active');
        });
    });

    window.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('active');
    });

// === ЗАГРУЗКА АВАТАРА (Ваш код) ===
    uploadBtn?.addEventListener('click', () => avatarInput.click());

    avatarInput?.addEventListener('change', () => {
        const file = avatarInput.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('avatar', file);

        fetch('update_avatar.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const newAvatarFilename = data.avatar;
                const newAvatarPath = `img/avatar/${newAvatarFilename}`;
                const newBgImage = `url('${newAvatarPath}')`;

                if (modalAvatar) modalAvatar.style.backgroundImage = newBgImage;
                if (modalBorderPreview) modalBorderPreview.style.backgroundImage = newBgImage;
                if (mainAvatar) mainAvatar.style.backgroundImage = newBgImage;

                showNotification('Аватар обновлён!', 'success');
            } else {
                showNotification(data.message || 'Ошибка', 'error');
            }
        })
        .catch(() => showNotification('Ошибка сети', 'error'));
    });

    // === СОХРАНЕНИЕ ИМЕНИ / КЛАССА (Ваш код) ===
    saveNameBtn?.addEventListener('click', () => {
        const username = newUsername.value.trim();
        const fullName = newFullName.value.trim();
        const classVal = newClass.value;

        if (!username || !fullName) {
            showNotification('Заполните логин и ФИО', 'error');
            return;
        }

        fetch('update_name.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `new_username=${encodeURIComponent(username)}&new_full_name=${encodeURIComponent(fullName)}&new_class=${encodeURIComponent(classVal)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // === МОДАЛКА ===
                const displayUsername = document.querySelector('#display-username');
                const displayFullname = document.querySelector('#display-fullname');
                const displayClass = document.querySelector('#display-class');

                if (displayUsername) displayUsername.textContent = username;
                if (displayFullname) displayFullname.textContent = fullName;
                if (displayClass) displayClass.textContent = classVal || 'Не указан';

                // === ГЛАВНАЯ СТРАНИЦА ===
                if (profileUsername) {
                    profileUsername.textContent = username;
                    profileUsername.setAttribute('data-username', username);
                }
                if (headerUsername) headerUsername.textContent = username;
                document.querySelectorAll('.rainbow-username').forEach(el => {
                    if (el.id !== 'profile-username' && el.id !== 'header-username') {
                        el.textContent = username;
                    }
                });
                if (mainFullname) mainFullname.textContent = fullName;
                if (mainClassElement) {
                    mainClassElement.textContent = `Класс: ${classVal || 'Не указан'}`;
                }

                showNotification('Данные сохранены!', 'success');

                // Таймер
                let time = 15;
                saveNameBtn.disabled = true;
                saveNameBtn.textContent = `Сохранено (${time})`;
                const interval = setInterval(() => {
                    time--;
                    if (time > 0) {
                        saveNameBtn.textContent = `Сохранено (${time})`;
                    } else {
                        clearInterval(interval);
                        saveNameBtn.disabled = false;
                        saveNameBtn.textContent = 'Сохранить изменения';
                    }
                }, 1000);
            } else {
                showNotification(data.message || 'Ошибка', 'error');
            }
        })
        .catch(() => showNotification('Ошибка сети', 'error'));
    });

    // === ТАБЫ (Ваш код) ===
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-tab');
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(`${target}-tab`).classList.add('active');
        });
    });

    // === ОБРАБОТЧИК: Клик по ячейке EFPremium (УПРОЩЕНО) ===
    allBorderCells.forEach(cell => {
        cell.addEventListener('click', () => {
            allBorderCells.forEach(c => c.classList.remove('selected'));
            cell.classList.add('selected');

            selectedBorderStyle = cell.dataset.borderStyle;
            selectedBorderColor = cell.dataset.borderColor;
            const width = cell.dataset.borderWidth || '4px';

            applyBorderStyles(modalAvatar, selectedBorderStyle, selectedBorderColor, width);
            applyBorderStyles(modalBorderPreview, selectedBorderStyle, selectedBorderColor, width);
            
            // --- УПРОЩЕННАЯ ЛОГИКА СИНХРОНИЗАЦИИ ---
            if (selectedBorderStyle === 'solid-default' && selectedBorderColor.startsWith('#')) {
                borderColorInput.value = selectedBorderColor; 
            } else {
                 borderColorInput.value = '#ff8c42'; 
            }
            // --- КОНЕЦ ЛОГИКИ СИНХРОНИЗАЦИИ ---
        });
    });


    // === ПРЕВЬЮ ЦВЕТА (Color Picker) ===
    borderColorInput?.addEventListener('input', () => {
        const color = borderColorInput.value;
        
        allBorderCells.forEach(c => c.classList.remove('selected'));
        
        selectedBorderStyle = 'solid-default';
        selectedBorderColor = color;

        applyBorderStyles(modalAvatar, selectedBorderStyle, color);
        applyBorderStyles(modalBorderPreview, selectedBorderStyle, color);
    });

    // === СОХРАНЕНИЕ РАМКИ ===
    saveBorderBtn?.addEventListener('click', () => {
        const color = selectedBorderColor; 
        const style = selectedBorderStyle;

        fetch('update_border.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `border_color=${encodeURIComponent(color)}&border_style=${encodeURIComponent(style)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                applyBorderStyles(mainAvatar, data.border_style, data.border_color);
                showNotification('Рамка сохранена!', 'success');
            } else {
                showNotification(data.message || 'Ошибка', 'error');
            }
        })
        .catch(() => showNotification('Ошибка сети', 'error'));
    });

    // === УВЕДОМЛЕНИЯ (Ваш код) ===
    function showNotification(message, type = 'info') {
        const notif = document.createElement('div');
        notif.className = `ef-notification ${type}`; 
        notif.textContent = message;
        document.body.appendChild(notif);

        setTimeout(() => notif.classList.add('show'), 10);
        setTimeout(() => {
            notif.classList.remove('show');
            setTimeout(() => notif.remove(), 300);
        }, 3000);
    }
});

