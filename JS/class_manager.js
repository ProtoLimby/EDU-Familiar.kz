// JS/class_manager.js — ФИНАЛЬНАЯ ВЕРСИЯ С МОДАЛКОЙ

// === ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ===
var currentClassId = null;
var globalTeachers = []; 
var pendingJoinClassId = null; // ID класса, в который хотим вступить

// =================================================================
// === 1. ФУНКЦИИ ХАБА (КАТАЛОГ И ВХОД) ===
// =================================================================

// Открытие модального окна "Учебный Центр"
window.openClassesHub = function() {
    const classesModal = document.getElementById('my-classes-modal');
    if (classesModal) {
        classesModal.classList.add('active');
        window.searchPublicClasses('', 1);
    }
};

// Поиск публичных классов (Каталог)
window.searchPublicClasses = function(query, page = 1) {
    const container = document.getElementById('public-classes-results');
    const pagination = document.getElementById('hub-pagination-controls');
    
    if(container) container.innerHTML = '<div class="hub-loading">Загрузка каталога...</div>';
    
    const formData = new FormData();
    formData.append('action', 'search_public_classes');
    formData.append('query', query);
    formData.append('page', page);

    fetch('class_actions.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(container) container.innerHTML = '';
        if(pagination) pagination.innerHTML = '';

        if (!data.classes || data.classes.length === 0) {
            if(container) container.innerHTML = '<div class="hub-loading">Классы не найдены</div>';
            return;
        }

        data.classes.forEach(cls => {
            const div = document.createElement('div');
            div.className = 'public-class-card';
            const iconClass = cls.avatar ? cls.avatar : 'fa-users';
            
            div.innerHTML = `
                <div class="pc-header-row">
                    <div class="pc-icon"><i class="fas ${iconClass}"></i></div>
                    <div class="pc-name">${cls.name}</div>
                </div>
                <div class="pc-teacher">
                    <i class="fas fa-user-tie"></i> ${cls.teacher_name}<br>
                    <span style="color:#94a3b8">${cls.grade} Класс</span>
                </div>
                <div class="pc-join-btn">Вступить</div>
            `;
            
            // === ЗДЕСЬ ИЗМЕНЕНИЕ: ОТКРЫВАЕМ МОДАЛКУ ===
            div.onclick = () => {
                window.openJoinConfirm(cls.id, cls.name);
            };
            
            container.appendChild(div);
        });

        if (data.total_pages > 1) {
            for (let i = 1; i <= data.total_pages; i++) {
                const btn = document.createElement('button');
                btn.className = `page-btn ${i === data.current_page ? 'active' : ''}`;
                btn.innerText = i;
                btn.onclick = () => window.searchPublicClasses(query, i);
                pagination.appendChild(btn);
            }
        }
    })
    .catch(err => {
        console.error(err);
        if(container) container.innerHTML = '<div class="hub-loading">Ошибка загрузки</div>';
    });
};

// === ЛОГИКА МОДАЛКИ ПОДТВЕРЖДЕНИЯ ===

window.openJoinConfirm = function(classId, className) {
    pendingJoinClassId = classId; // Запоминаем ID
    
    // Вставляем имя класса в текст модалки
    const nameSpan = document.getElementById('join-class-name-target');
    if(nameSpan) nameSpan.innerText = className;
    
    const modal = document.getElementById('joinConfirmModal');
    const backdrop = document.getElementById('joinConfirmBackdrop');
    
    if(modal && backdrop) {
        modal.classList.add('active');
        backdrop.classList.add('active');
    }
};

window.closeJoinConfirm = function() {
    const modal = document.getElementById('joinConfirmModal');
    const backdrop = document.getElementById('joinConfirmBackdrop');
    
    if(modal && backdrop) {
        modal.classList.remove('active');
        backdrop.classList.remove('active');
    }
    pendingJoinClassId = null; // Сбрасываем
};


// Вход в ПУБЛИЧНЫЙ класс (API запрос)
window.joinPublicClass = function(classId) {
    // Убрали стандартный confirm(), теперь сразу запрос
    const formData = new FormData();
    formData.append('action', 'join_public_class');
    formData.append('class_id', classId);

    fetch('class_actions.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Можно перенаправить или показать успех
            window.location.href = `class_view.php?id=${data.class_id}`;
        } else {
            alert("Ошибка: " + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert("Ошибка сети при вступлении в класс");
    });
};

// Вход в ПРИВАТНЫЙ класс (по коду)
window.joinClassByCode = function() {
    const codeInput = document.getElementById('join-class-code');
    const code = codeInput.value.trim();
    
    if (!code) { alert("Введите код класса!"); return; }
    
    const formData = new FormData();
    formData.append('action', 'join_by_code');
    formData.append('code', code);

    fetch('class_actions.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(`Успешно! Вы вступили в класс "${data.class_name}"`);
            window.location.href = `class_view.php?id=${data.class_id}`;
        } else {
            alert("Ошибка: " + data.message);
        }
    })
    .catch(err => {
        console.error("Join Error:", err);
        alert("Ошибка сети при входе в класс");
    });
};

// Переключение вкладок внутри Хаба
window.switchHubTab = function(tabName) {
    document.querySelectorAll('.hub-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.hub-pane').forEach(p => p.classList.remove('active'));
    
    const btn = document.querySelector(`.hub-tab[onclick*="${tabName}"]`);
    if(btn) btn.classList.add('active');
    
    const pane = document.getElementById('hub-tab-' + tabName);
    if(pane) pane.classList.add('active');
    
    if (tabName === 'public-search') {
        window.searchPublicClasses('', 1);
    }
};


// =================================================================
// === 2. УПРАВЛЕНИЕ КЛАССОМ (УЧИТЕЛЬ) ===
// =================================================================
// (Оставляем без изменений старый функционал)

window.openCreateModal = function() { 
    const m = document.getElementById('createClassModal');
    if(m) { m.style.display = 'block'; setTimeout(() => m.classList.add('active'), 10); }
};
window.closeCreateModal = function() { 
    const m = document.getElementById('createClassModal');
    if(m) { m.classList.remove('active'); setTimeout(() => m.style.display = 'none', 300); }
};
window.openSettingsModal = function() { 
    const m = document.getElementById('classSettingsModal');
    if(m) { m.style.display = 'block'; setTimeout(() => m.classList.add('active'), 10); window.loadMembers(); window.loadSections(); }
};
window.closeSettingsModal = function() { 
    const m = document.getElementById('classSettingsModal');
    if(m) { m.classList.remove('active'); setTimeout(() => m.style.display = 'none', 300); }
};
window.switchSettingsTab = function(tabName, btn) {
    document.querySelectorAll('.s-nav-item').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.s-tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('set-tab-' + tabName).classList.add('active');
};

let searchTimeout = null;
window.searchUsers = function(input, role) {
    clearTimeout(searchTimeout);
    const resultsBox = document.getElementById('search-res-' + role);
    searchTimeout = setTimeout(() => {
        const query = input.value;
        if(query.length < 2) { resultsBox.style.display = 'none'; return; }
        const formData = new FormData();
        formData.append('action', 'search_users');
        formData.append('query', query);
        formData.append('role', role);
        fetch('create_class.php', { method: 'POST', body: formData }).then(res => res.json()).then(users => {
            resultsBox.innerHTML = '';
            if(users.length > 0) {
                resultsBox.style.display = 'block';
                users.forEach(u => {
                    const div = document.createElement('div');
                    div.className = 'search-item';
                    div.innerHTML = `<img src="img/avatar/${u.avatar || 'Def_Avatar.jpg'}" style="width:20px; height:20px; border-radius:50%;"> <span>${u.full_name} (${u.username})</span>`;
                    div.onclick = () => window.addMember(u.id, role);
                    resultsBox.appendChild(div);
                });
            } else { resultsBox.style.display = 'none'; }
        });
    }, 300);
};

window.addMember = function(userId, role) {
    if(!currentClassId) return;
    const formData = new FormData(); formData.append('action', 'add_member'); formData.append('class_id', currentClassId); formData.append('user_id', userId); formData.append('role', role);
    fetch('create_class.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
        if(data.success) { window.loadMembers(); document.querySelectorAll('.user-search-input').forEach(i => i.value = ''); document.querySelectorAll('.search-results').forEach(r => r.style.display = 'none'); }
        else { alert('Ошибка: ' + (data.message || 'Не удалось добавить')); }
    });
};

window.removeMember = function(userId) {
    if(!confirm('Удалить участника?')) return;
    const formData = new FormData(); formData.append('action', 'remove_member'); formData.append('class_id', currentClassId); formData.append('user_id', userId);
    fetch('create_class.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => { if(data.success) window.loadMembers(); });
};

window.loadMembers = function() {
    if(!currentClassId) return;
    const formData = new FormData(); formData.append('action', 'get_members'); formData.append('class_id', currentClassId);
    fetch('create_class.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
        window.renderList('students-list', data.students);
        window.renderList('teachers-list', data.teachers);
        globalTeachers = data.teachers;
    });
};

window.renderList = function(elementId, users) {
    const list = document.getElementById(elementId); list.innerHTML = '';
    if(users.length === 0) { list.innerHTML = '<li class="empty-state">Список пуст</li>'; return; }
    users.forEach(u => {
        const li = document.createElement('li'); li.className = 'p-item';
        let statusBadge = '';
        if(u.status === 'pending') statusBadge = '<span class="status-badge pending">В ожидании</span>';
        else if(u.status === 'accepted') statusBadge = '<span class="status-badge accepted">Участник</span>';
        else if(u.status === 'rejected') statusBadge = '<span class="status-badge rejected">Отклонил</span>';
        li.innerHTML = `<div class="p-info"><img src="img/avatar/${u.avatar || 'Def_Avatar.jpg'}" class="p-ava"><span>${u.full_name}</span></div>${statusBadge}<button class="p-btn-mini btn-rem" onclick="window.removeMember(${u.id})"><i class="fas fa-times"></i></button>`;
        list.appendChild(li);
    });
};

window.createSection = function() {
    const name = document.getElementById('new-section-name').value; if(!name || !currentClassId) return;
    const formData = new FormData(); formData.append('action', 'create_section'); formData.append('class_id', currentClassId); formData.append('title', name);
    fetch('create_class.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => { if(data.success) { document.getElementById('new-section-name').value = ''; window.loadSections(); } });
};

window.loadSections = function() {
    if(!currentClassId) return;
    const formData = new FormData(); formData.append('action', 'get_sections'); formData.append('class_id', currentClassId);
    fetch('create_class.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
        const container = document.getElementById('sections-list-container'); container.innerHTML = '';
        if(!data.sections || data.sections.length === 0) { container.innerHTML = '<div class="empty-state">Разделов пока нет</div>'; return; }
        data.sections.forEach(sec => {
            const div = document.createElement('div'); div.className = 'section-card';
            let options = '<option value="">+ Добавить</option>';
            globalTeachers.forEach(t => {
                const exists = sec.teachers.some(existing => existing.id == t.id);
                if(!exists) options += `<option value="${t.id}">${t.full_name}</option>`;
            });
            let teachersHtml = '';
            sec.teachers.forEach(t => { teachersHtml += `<span class="teacher-badge"><i class="fas fa-user-circle"></i> ${t.full_name} <span class="rem-teacher-x" onclick="window.removeSecTeacher(${sec.id}, ${t.id})">&times;</span></span>`; });
            div.innerHTML = `<div class="sec-head"><span class="sec-title">${sec.title}</span><i class="fas fa-trash" style="color:#cbd5e1; cursor:pointer;" onclick="window.deleteSection(${sec.id})"></i></div><div class="sec-teachers">${teachersHtml}<select class="add-teacher-select" onchange="window.assignTeacher(${sec.id}, this)">${options}</select></div>`;
            container.appendChild(div);
        });
    });
};

window.assignTeacher = function(secId, select) {
    const tId = select.value; if(!tId) return;
    const formData = new FormData(); formData.append('action', 'assign_section_teacher'); formData.append('section_id', secId); formData.append('teacher_id', tId);
    fetch('create_class.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => { if(data.success) window.loadSections(); });
};

window.removeSecTeacher = function(secId, tId) {
    const formData = new FormData(); formData.append('action', 'remove_section_teacher'); formData.append('section_id', secId); formData.append('teacher_id', tId);
    fetch('create_class.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => { if(data.success) window.loadSections(); });
};

window.deleteSection = function(secId) {
    if(!confirm('Удалить раздел?')) return;
    const formData = new FormData(); formData.append('action', 'delete_section'); formData.append('section_id', secId);
    fetch('create_class.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => { if(data.success) window.loadSections(); });
};

window.applyQuickName = function(select) { if(select.value) { document.getElementById('new-section-name').value = select.value; select.value = ''; } };

window.loadClassDetails = function(element) {
    const container = document.getElementById('class-details-container');
    const name = element.dataset.name;
    const code = element.dataset.code;
    const privacy = element.dataset.privacy;
    const grade = element.dataset.grade;
    const sCount = element.dataset.students;
    const tCount = element.dataset.teachers;
    currentClassId = element.dataset.id;

    document.querySelectorAll('.class-item').forEach(it => it.classList.remove('active'));
    element.classList.add('active');

    let titleHtml = (privacy === 'private') ? `<span style="color:var(--primary-color)">${code}</span>` : name;
    let subTitle = (privacy === 'private') ? `${name} (${grade} Класс)` : `Публичный класс (${grade})`;

    container.innerHTML = `
        <div class="class-details-header">
            <div class="header-top-row">
                <div class="header-title-box"><h2>${titleHtml}</h2><p>${subTitle}</p>${privacy === 'private' ? '<div class="header-code-badge">Код доступа</div>' : ''}</div>
                <button class="class-settings-btn" onclick="window.openSettingsModal()"><i class="fas fa-cog"></i> Настройки класса</button>
            </div>
            <div class="class-stats-row">
                <div class="stat-chip"><i class="fas fa-chalkboard-teacher"></i> Учителей: ${tCount}</div>
                <div class="stat-chip"><i class="fas fa-user-graduate"></i> Учеников: ${sCount}</div>
            </div>
        </div>
        <div class="class-details-body">
            <div class="journal-placeholder"><i class="fas fa-book-reader" style="font-size:40px; margin-bottom:15px; opacity:0.3;"></i><p>Здесь будет Журнал активности и Новости класса.</p></div>
        </div>
    `;
};

// =================================================================
// === 3. ИНИЦИАЛИЗАЦИЯ ПРИ ЗАГРУЗКЕ СТРАНИЦЫ ===
// =================================================================
document.addEventListener('DOMContentLoaded', () => {
    // Привязка событий для кнопок открытия модалок
    const hubBtn = document.getElementById('open-classes-btn');
    if (hubBtn) hubBtn.addEventListener('click', window.openClassesHub);

    const closeClassesX = document.getElementById('close-classes-modal');
    if (closeClassesX) closeClassesX.addEventListener('click', () => { 
        document.getElementById('my-classes-modal').classList.remove('active'); 
    });
    
    // Привязка кнопки "Вступить" внутри модалки подтверждения
    const confirmJoinBtn = document.getElementById('confirm-join-btn');
    if (confirmJoinBtn) {
        confirmJoinBtn.addEventListener('click', () => {
            if(pendingJoinClassId) {
                window.joinPublicClass(pendingJoinClassId); // Вызываем функцию входа
                window.closeJoinConfirm(); // Закрываем модалку
            }
        });
    }

    // Закрытие по клику вне окна
    window.addEventListener('click', (event) => {
        const createModal = document.getElementById('createClassModal');
        const settingsModal = document.getElementById('classSettingsModal');
        const classesModal = document.getElementById('my-classes-modal');
        
        // Новое: закрытие модалки подтверждения при клике на фон
        const joinBackdrop = document.getElementById('joinConfirmBackdrop');
        
        if (event.target == createModal) window.closeCreateModal();
        if (event.target == settingsModal) window.closeSettingsModal();
        if (event.target == classesModal) classesModal.classList.remove('active');
        if (event.target == joinBackdrop) window.closeJoinConfirm();
    });
    
    // Выбор иконки при создании класса
    document.querySelectorAll('.class-icon-option').forEach(opt => {
        opt.addEventListener('click', () => {
            document.querySelectorAll('.class-icon-option').forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
            document.getElementById('selected-icon-input').value = opt.dataset.icon;
        });
    });
});