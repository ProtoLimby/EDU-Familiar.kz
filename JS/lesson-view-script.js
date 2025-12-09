document.addEventListener('DOMContentLoaded', () => {
    const submitBtn = document.getElementById('submit-lesson-btn');
    const statusMessage = document.getElementById('lesson-status-message');
    const lessonId = new URLSearchParams(window.location.search).get('id');
    
    // --- ТАЙМЕР (НОВАЯ ЛОГИКА) ---
    // Элементы таймера (могут отсутствовать, если таймер не задан)
    const stickyTimer = document.getElementById('sticky-timer');
    const timerDisplay = document.getElementById('timer-display');
    
    let timerInterval = null;
    let timeRemainingSeconds = 0;
    
    // Получаем глобальную переменную serverTimeRemaining (из PHP)
    // Если она не определена или null, значит серверного таймера нет
    const hasServerTimer = (typeof serverTimeRemaining !== 'undefined' && serverTimeRemaining !== null);

    if (hasServerTimer && stickyTimer) {
        // Если сервер передал время, используем его
        startTimerLogic(serverTimeRemaining);
    } else if (stickyTimer && stickyTimer.dataset.minutes) {
        // Фоллбэк для старой логики (если вдруг нет сервера)
        const minutes = parseInt(stickyTimer.dataset.minutes) || 30;
        startTimerLogic(minutes * 60);
    }

    function startTimerLogic(seconds) {
        timeRemainingSeconds = seconds;
        
        // Показываем таймер (добавляем класс active для анимации)
        if(stickyTimer) {
            stickyTimer.style.display = 'flex';
            setTimeout(() => stickyTimer.classList.add('active'), 100);
        }

        updateTimerUI();

        // Если время уже вышло
        if (timeRemainingSeconds <= 0) {
            handleTimeout();
            return;
        }

        timerInterval = setInterval(() => {
            timeRemainingSeconds--;
            updateTimerUI();

            // Эффект "мало времени" (красный)
            if (timeRemainingSeconds <= 60 && stickyTimer) {
                stickyTimer.classList.add('urgent');
            }

            if (timeRemainingSeconds <= 0) {
                handleTimeout();
            }
        }, 1000);
    }

    function updateTimerUI() {
        if (!timerDisplay) return;
        let t = timeRemainingSeconds;
        if (t < 0) t = 0;
        const minutes = Math.floor(t / 60);
        const seconds = t % 60;
        timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }

    function handleTimeout() {
        if (timerInterval) clearInterval(timerInterval);
        alert('Время вышло! Урок завершается автоматически.');
        
        // Если функция handleSubmit доступна (она обычно определена в view_page.php инлайново)
        // то вызываем её, либо делаем редирект/отправку формы
        if (typeof finishLesson === 'function') {
            finishLesson(true);
        } else {
            // Фолбэк, если функции нет в этой области видимости
            window.location.href = 'training.php';
        }
    }

    // --- ПОДСЧЕТ БАЛЛОВ И ОТПРАВКА ---
    // (Этот блок нужен, если вы используете отдельный скрипт для обработки клика, 
    // но в view_page.php уже есть инлайн-скрипт finishLesson. 
    // Если логика дублируется, убедитесь, что не вызываете дважды)
    
    // Инициализация Drag&Drop (если есть элементы)
    if (typeof Sortable !== 'undefined') {
        document.querySelectorAll('.sequence-sortable-list, .matching-right.sortable-match').forEach(list => {
            new Sortable(list, {
                animation: 150,
                handle: '.fa-grip-vertical',
                group: 'lesson-sort',
                forceFallback: true,
            });
        });
    }
});