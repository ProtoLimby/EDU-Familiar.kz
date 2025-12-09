/**
 * coins.js — Исправленная версия
 * Теперь корона выдается даже если на странице нет таблицы лидеров.
 */

class EFCoinSystem {
    constructor() {
        this.currentPoints = 0;
        this.highestScore = 0;
        this.pointsDisplay = document.querySelector('.points-value');
        this.notification = document.getElementById('ef-notification');
        this.coinSound = document.getElementById('coin-sound');
        this.highestScoreElement = document.querySelector('.highest-score p');
        this.updateInterval = null;
        this.firstLoad = true;

        this.init();
    }

    init() {
        const initial = this.pointsDisplay?.dataset?.initialPoints;
        const initialHighest = document.querySelector('.highest-score p')?.textContent.match(/[\d,]+/)?.[0].replace(/,/g, '');

        this.currentPoints = initial ? parseInt(initial, 10) : 0;
        this.highestScore = initialHighest ? parseInt(initialHighest, 10) : 0;

        if (this.pointsDisplay) {
            this.pointsDisplay.textContent = this.currentPoints.toLocaleString();
        }

        this.startPolling();
    }

    startPolling() {
        this.fetchPoints();
        this.updateInterval = setInterval(() => this.fetchPoints(), 3000);
    }

    fetchPoints() {
        fetch('get_points.php', {
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => {
            if (!r.ok) throw new Error('Network error');
            return r.json();
        })
        .then(data => {
            const newPoints = Number(data.points) || 0;
            const newHighestScore = Number(data.highest_score) || 0;

            if (!this.firstLoad && newPoints > this.currentPoints) {
                const diff = newPoints - this.currentPoints;
                this.showNotification(diff);
                this.playSound();
            }

            this.currentPoints = newPoints;
            this.highestScore = newHighestScore;

            if (this.pointsDisplay) {
                this.pointsDisplay.textContent = newPoints.toLocaleString();
            }
            
            if (this.highestScoreElement) {
                this.highestScoreElement.innerHTML = `Best Score: <strong>${this.highestScore.toLocaleString()} EF</strong>`;
            }

            this.firstLoad = false;
        })
        .catch(err => {
            console.warn('EF update failed:', err.message);
            this.firstLoad = false;
        });
    }

    showNotification(amount) {
        if (!this.notification) return;
        this.notification.innerHTML = `<i class="fas fa-coins"></i><span class="plus">+${amount}</span><span>EF</span>`;
        this.notification.classList.add('show');
        clearTimeout(this.notification.hideTimeout);
        this.notification.hideTimeout = setTimeout(() => {
            this.notification.classList.remove('show');
        }, 5000);
    }

    playSound() {
        if (!this.coinSound) return;
        this.coinSound.currentTime = 0;
        this.coinSound.play().catch(() => {});
    }

    destroy() {
        if (this.updateInterval) clearInterval(this.updateInterval);
    }
}

// Leaderboard класс
class Leaderboard {
    constructor() {
        this.listElement = document.getElementById('leaderboard-list'); // Может быть null
        this.positionElement = document.getElementById('user-position');
        this.profileNameEl = document.getElementById('profile-username'); // Главная цель
        this.username = this.profileNameEl?.dataset?.username || '';
        this.page = 1;
        this.totalPages = 1;
        this.isTable = !!document.getElementById('leaderboard-table');
        this.nextBtn = document.querySelector('.pagination-btn.next');
        this.prevBtn = document.querySelector('.pagination-btn.prev');
        this.updateInterval = null;

        this.perPage = this.isTable ? 100 : 3;

        this.init();
    }

    init() {
        this.update(this.page);
        this.startPolling();
        if (!this.isTable && this.listElement) {
            if (this.nextBtn) this.nextBtn.addEventListener('click', () => this.loadNextPage());
            if (this.prevBtn) this.prevBtn.addEventListener('click', () => this.loadPrevPage());
        }
    }

    startPolling() {
        this.updateInterval = setInterval(() => this.update(this.page), 10000);
    }

    update(page = 1) {
        const per = this.perPage;
        const contextParam = this.isTable ? '&context=best' : '';
        fetch(`get_leaderboard.php?page=${page}&per_page=${per}${contextParam}`, { cache: 'no-store' })
            .then(r => {
                if (!r.ok) throw new Error('Network error');
                return r.json();
            })
            .then(data => {
                if (data.per_page) this.perPage = Number(data.per_page) || this.perPage;

                this.render(data);
                this.page = data.page || page;
                this.totalPages = data.total_pages || 1;
                
                if (this.listElement) {
                    this.updatePaginationButtons();
                }
            })
            .catch((err) => {
                console.warn('Leaderboard update error:', err);
            });
    }

    render(data) {
        const per = this.perPage;

        // 1. Рендер списка (Только если список существует на странице)
        if (this.listElement) {
            this.listElement.innerHTML = '';
            if (this.isTable) {
                let leaders = data.leaders || [];
                for (let i = 0; i < 100; i++) {
                    let user = leaders[i];
                    const globalRank = i + 1;
                    const crown = this.getCrown(globalRank);
                    const points = user ? this.formatPoints(user.points) : '-';
                    const username = user ? this.escape(user.username) : '-';
                    const avatar = user ? `img/avatar/${user.avatar}` : '';

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${globalRank}</td>
                        <td>${user ? `<img src="${avatar}" alt="Avatar" class="avatar-icon">` : '-'}</td>
                        <td>${crown}${username}</td>
                        <td>${points}</td>
                    `;
                    if (globalRank === 1) tr.classList.add('rank-1');
                    else if (globalRank === 2) tr.classList.add('rank-2');
                    else if (globalRank === 3) tr.classList.add('rank-3');

                    this.listElement.appendChild(tr);
                }
            } else {
                data.leaders?.forEach((user, i) => {
                    const globalRank = (this.page - 1) * per + i + 1;
                    const crown = this.getCrown(globalRank);
                    const points = this.formatPoints(user.points);

                    const li = document.createElement('li');
                    li.innerHTML = `${crown}<strong>${this.escape(user.username)}</strong> <span class="points">${points}</span>`;
                    this.listElement.appendChild(li);
                });
            }
        }

        // 2. Ваша позиция (если есть блок)
        if (this.positionElement && data.user_position) {
            const suf = this.suffix(data.user_position);
            this.positionElement.innerHTML = `Your Position: <strong>${data.user_position}${suf}</strong>`;
        }

        // 3. КОРОНА В ПРОФИЛЕ (Главное исправление: работает независимо от списка)
        if (this.profileNameEl && data.user_position && this.username) {
            const rank = data.user_position;
            let crown = '';
            if (rank === 1) crown = '<i class="fas fa-crown rainbow-crown"></i> ';
            else if (rank === 2) crown = '<i class="fas fa-crown silver-crown"></i> ';
            else if (rank === 3) crown = '<i class="fas fa-crown bronze-crown"></i> ';

            this.profileNameEl.innerHTML = crown + this.escape(this.username);
        }
    }

    getCrown(rank) {
        if (rank === 1) return `<i class="fas fa-crown rainbow-crown"></i>`;
        if (rank === 2) return `<i class="fas fa-crown silver-crown"></i>`;
        if (rank === 3) return `<i class="fas fa-crown bronze-crown"></i>`;
        return '';
    }

    formatPoints(p) {
        if (p >= 1_000_000) return (p / 1_000_000).toFixed(1).replace('.0', '') + 'M';
        if (p >= 1_000) return (p / 1_000).toFixed(1).replace('.0', '') + 'K';
        return Number(p).toLocaleString();
    }

    loadNextPage() {
        if (this.page < this.totalPages) {
            this.update(this.page + 1);
        }
    }

    loadPrevPage() {
        if (this.page > 1) {
            this.update(this.page - 1);
        }
    }

    updatePaginationButtons() {
        if (this.isTable) {
            if (this.prevBtn) this.prevBtn.style.display = 'none';
            if (this.nextBtn) this.nextBtn.style.display = 'none';
        } else {
            if (this.prevBtn) this.prevBtn.style.display = this.page > 1 ? 'inline-block' : 'none';
            if (this.nextBtn) this.nextBtn.style.display = this.page < this.totalPages ? 'inline-block' : 'none';
        }
    }

    suffix(n) {
        if (n > 10 && n < 20) return 'th';
        return {1:'st',2:'nd',3:'rd'}[n % 10] || 'th';
    }

    escape(t) {
        const div = document.createElement('div');
        div.textContent = t;
        return div.innerHTML;
    }

    destroy() {
        if (this.updateInterval) clearInterval(this.updateInterval);
    }
}

// === ЗАПУСК ===
document.addEventListener('DOMContentLoaded', () => {
    // EF-монеты
    if (document.querySelector('.points-value')) {
        window.efCoins = new EFCoinSystem();
    }

    // Лидерборд + корона в нике
    // ИСПРАВЛЕНИЕ: Запускаем, если есть список ИЛИ если есть имя профиля (для короны)
    if (document.getElementById('leaderboard-list') || document.getElementById('profile-username')) {
        window.lb = new Leaderboard();
    }
});

// === ОЧИСТКА ===
window.addEventListener('beforeunload', () => {
    if (window.efCoins) window.efCoins.destroy();
    if (window.lb) window.lb.destroy();
});