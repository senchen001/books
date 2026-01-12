document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('hideEmptySeqNum');
    const STORAGE_KEY = 'hideEmptySeqNum';
    
    // Восстанавливаем состояние чекбокса из localStorage
    function restoreCheckboxState() {
        const savedState = localStorage.getItem(STORAGE_KEY);
        if (savedState !== null) {
            checkbox.checked = savedState === 'true';
        } else {
            // По умолчанию включен
            checkbox.checked = true;
        }
        
        // Синхронизируем с URL параметром
        syncWithUrlParam();
    }
    
    // Сохраняем состояние в localStorage
    function saveCheckboxState() {
        localStorage.setItem(STORAGE_KEY, checkbox.checked.toString());
    }
    
    // Синхронизируем состояние чекбокса с URL параметром
    function syncWithUrlParam() {
        const urlParams = new URLSearchParams(window.location.search);
        const hideEmptyParam = urlParams.get('hide_empty_seqnum');
        
        // Если в URL нет параметра, но чекбокс включен - добавляем параметр
        if (checkbox.checked && hideEmptyParam !== '1') {
            updateUrl(true);
        }
        // Если в URL есть параметр, но чекбокс выключен - убираем параметр  
        else if (!checkbox.checked && hideEmptyParam === '1') {
            updateUrl(false);
        }
    }
    
    // Обновляем URL с параметром hide_empty_seqnum
    function updateUrl(hideEmpty) {
        const url = new URL(window.location);
        
        if (hideEmpty) {
            url.searchParams.set('hide_empty_seqnum', '1');
        } else {
            url.searchParams.delete('hide_empty_seqnum');
        }
        
        // Перезагружаем страницу с новыми параметрами
        window.location.href = url.toString();
    }
    
    // Обработчик изменения состояния чекбокса
    checkbox.addEventListener('change', function() {
        saveCheckboxState();
        updateUrl(checkbox.checked);
    });
    
    // Инициализация при загрузке страницы
    restoreCheckboxState();
});