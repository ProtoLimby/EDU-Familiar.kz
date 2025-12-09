document.addEventListener('DOMContentLoaded', () => {
    const buyButtons = document.querySelectorAll('.buy-btn');
    const notification = document.getElementById('shop-notification');
    const userBalanceEl = document.querySelector('.user-balance strong');
    let currentUserBalance = userBalanceEl ? parseInt(userBalanceEl.textContent.replace(/\D/g, '')) : 0;

    buyButtons.forEach(button => {
        // Пропускаем кнопки "Куплено"
        if (button.classList.contains('owned') || button.disabled) {
            return;
        }

        button.addEventListener('click', () => {
            const itemId = button.dataset.itemId;
            const itemType = button.dataset.itemType;
            const price = parseInt(button.dataset.price);
            const itemName = button.closest('.shop-item').querySelector('h3').textContent;

            if (currentUserBalance < price) {
                showNotification('Недостаточно средств!', 'error');
                return;
            }

            if (!confirm(`Вы уверены, что хотите купить "${itemName}" за ${price} EF?`)) {
                return;
            }

            // Блокируем кнопку
            button.disabled = true;
            button.textContent = 'Обработка...';

            fetch('buy_item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `item_id=${itemId}&item_type=${itemType}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotification('Покупка совершена!', 'success');
                    
                    // Обновляем баланс на странице
                    if (data.new_points !== undefined) {
                        currentUserBalance = data.new_points;
                        userBalanceEl.textContent = `${data.new_points.toLocaleString()} EF`;
                    }
                    
                    // Обновляем кнопку
                    if (itemType === 'frame') {
                        button.textContent = 'Куплено';
                        button.classList.add('owned');
                        // Оставляем disabled
                    } else if (itemType === 'item' && data.is_active_premium) {
                        button.textContent = 'Продлить';
                        button.disabled = false; // Можно продлить еще
                        // (тут можно обновить и дату в .item-status, но это сложнее)
                    }

                } else {
                    showNotification(data.message || 'Ошибка покупки', 'error');
                    button.disabled = false; // Разблокируем, если ошибка
                    button.textContent = 'Купить';
                }
            })
            .catch(() => {
                showNotification('Ошибка сети. Попробуйте снова.', 'error');
                button.disabled = false;
                button.textContent = 'Купить';
            });
        });
    });

    function showNotification(message, type = 'success') {
        if (!notification) return;
        notification.textContent = message;
        notification.className = `shop-notification ${type}`;
        notification.classList.add('show');

        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    }
});