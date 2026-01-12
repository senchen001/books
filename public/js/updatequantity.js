// updatequantity.js - Глобальные настройки для скрипта cart.js
// Этот файл должен подключаться ПЕРЕД cart.js

// Глобальные настройки для маршрутов
window.cartUpdateQuantityUrl = '/cart/update-quantity';
window.cartUpdatePriceUrl = '/cart/update-price';
window.cartRemoveUrl = '/cart/remove';

// Динамически получить маршруты из Laravel (если в шаблоне есть блок с id="cart-routes")
document.addEventListener('DOMContentLoaded', function() {
    const routesElement = document.getElementById('cart-routes');
    if (routesElement) {
        if (routesElement.dataset.updateQuantity) {
            window.cartUpdateQuantityUrl = routesElement.dataset.updateQuantity;
        }
        if (routesElement.dataset.updatePrice) {
            window.cartUpdatePriceUrl = routesElement.dataset.updatePrice;
        }
        if (routesElement.dataset.remove) {
            window.cartRemoveUrl = routesElement.dataset.remove;
        }
    }
});