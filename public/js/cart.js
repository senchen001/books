// cart.js - Скрипт для работы с корзиной
document.addEventListener('DOMContentLoaded', function() {
    // Получаем CSRF-токен из мета-тега или из Laravel-форм
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    // Если токен не найден в мета-теге, ищем в скрытых полях форм (Laravel автоматически добавляет)
    if (!csrfToken) {
        const csrfField = document.querySelector('input[name="_token"]');
        if (csrfField) {
            csrfToken = csrfField.value;
        }
    }
    
    // Получаем базовые URL из data-атрибутов или из настроек скрипта
    // Для поддержки работы на разных страницах
    const cartRoutes = {
        updateQuantity: document.body.dataset.routeUpdateQuantity || window.cartUpdateQuantityUrl || '/cart/update-quantity',
        updatePrice: document.body.dataset.routeUpdatePrice || window.cartUpdatePriceUrl || '/cart/update-price',
        remove: document.body.dataset.routeRemove || window.cartRemoveUrl || '/cart/remove'
    };
    
    // Обработка изменения количества
    const quantityInputs = document.querySelectorAll('.quantity-input');
    
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            const productId = this.dataset.productId;
            const quantity = parseInt(this.value);
            
            if (quantity <= 0) {
                // Если количество 0 или меньше, удаляем товар
                removeCartItem(productId);
            } else {
                // Иначе обновляем количество
                updateCartItemQuantity(productId, quantity);
            }
        });
    });
    
    // Обработка изменения цены
    const priceInputs = document.querySelectorAll('.price-input');
    
    priceInputs.forEach(input => {
        input.addEventListener('change', function() {
            const productId = this.dataset.productId;
            const price = parseFloat(this.value) || null;
            
            // Обновляем цену
            updateCartItemPrice(productId, price);
        });
    });
    
    // Обработка кнопки удаления
    const removeButtons = document.querySelectorAll('.remove-item');
    
    removeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            removeCartItem(productId);
        });
    });
    
    // Функция для обновления количества товара
    function updateCartItemQuantity(productId, quantity) {
        // Проверка на наличие CSRF-токена
        if (!csrfToken) {
            console.error('CSRF токен не найден. Обновление количества товара невозможно.');
            return;
        }
        
        // Создаем форму для отправки
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity', quantity);
        formData.append('_token', csrfToken);
        
        // Отправляем AJAX запрос
        fetch(cartRoutes.updateQuantity, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Quantity updated successfully', data);
            // При необходимости можно обновить UI здесь
        })
        .catch(error => {
            console.error('Error updating quantity:', error);
        });
    }
    
    // Функция для обновления цены товара
    function updateCartItemPrice(productId, price) {
        // Проверка на наличие CSRF-токена
        if (!csrfToken) {
            console.error('CSRF токен не найден. Обновление цены товара невозможно.');
            return;
        }
        
        // Создаем форму для отправки
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('price', price);
        formData.append('_token', csrfToken);
        
        // Отправляем AJAX запрос
        fetch(cartRoutes.updatePrice, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Price updated successfully', data);
            // При необходимости можно обновить UI здесь
        })
        .catch(error => {
            console.error('Error updating price:', error);
        });
    }
    
    // Функция для удаления товара
    function removeCartItem(productId) {
        // Проверка на наличие CSRF-токена
        if (!csrfToken) {
            console.error('CSRF токен не найден. Удаление товара невозможно.');
            return;
        }
        
        const formData = new FormData();
        formData.append('book_id', productId);  // Используем book_id в соответствии с оригинальным скриптом
        formData.append('_token', csrfToken);
        formData.append('_method', 'DELETE');

        fetch(cartRoutes.remove, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('Item removed successfully', data);
            
            // Удаляем строку (если есть таблица корзины)
            const row = document.getElementById('cart-item-' + productId);
            if (row) row.remove();

            // Проверка: если на главной, меняем кнопку обратно на синюю
            const cartButtonContainer = document.getElementById('cart-button-' + productId);
            if (cartButtonContainer) {
                cartButtonContainer.innerHTML = `
                    <button 
                        type="submit" 
                        class="text-white py-2 px-4 rounded flex flex-col items-center justify-center"
                        style="min-width: 80px; min-height: 60px; background-color:#3b82f6;"
                    >
                        <span class="font-bold select-none">В корзину</span>
                    </button>
                `;
            }

            // Если корзина пуста — обновляем страницу
            if (document.querySelector('tbody') && document.querySelectorAll('tbody tr').length === 0) {
                window.location.reload();
            }
        })
        .catch(error => {
            console.error('Error removing item:', error);
            alert('Ошибка при удалении товара');
        });
    }
});