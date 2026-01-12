<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Синхронизация ИРБИС -> MySQL</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .section h3 {
            margin-top: 0;
            color: #555;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }
        button:hover {
            background-color: #0056b3;
        }
        button:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-success {
            background-color: #28a745;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background-color: #e0a800;
        }
        input[type="number"] {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100px;
        }
        #log {
            background-color: #000;
            color: #00ff00;
            padding: 15px;
            border-radius: 4px;
            height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            margin-top: 20px;
        }
        .status {
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .status.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .loading {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .stat-item {
            background: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        .debug-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Синхронизация ИРБИС → MySQL</h1>
        
        <!-- Тест подключения -->
        <div class="section">
            <h3>🔍 Проверка подключений</h3>
            <p>Проверьте подключение к базам данных ИРБИС и MySQL перед началом синхронизации.</p>
            <button onclick="testConnection()" id="testBtn">Тестировать подключения</button>
            <div id="connectionStatus"></div>
        </div>

        <!-- Отладка записи -->
        <div class="section">
            <h3>🐛 Отладка записи</h3>
            <p>Получить подробную информацию о структуре записи из ИРБИС для отладки.</p>
            <label for="debugMfn">MFN для отладки:</label>
            <input type="number" id="debugMfn" value="1" min="1">
            <button onclick="debugRecord()" id="debugBtn" class="btn-warning">Отладить запись</button>
            <div id="debugStatus"></div>
        </div>

        <!-- Полная синхронизация -->
        <div class="section">
            <h3>🔄 Полная синхронизация</h3>
            <p><strong>Внимание!</strong> Эта операция обработает все записи в базе NEB. Может занять много времени.</p>
            <button onclick="startFullSync()" id="fullSyncBtn" class="btn-danger">Запустить полную синхронизацию</button>
        </div>

        <!-- Синхронизация диапазона -->
        <div class="section">
            <h3>🎯 Синхронизация диапазона</h3>
            <p>Синхронизировать только определенный диапазон MFN записей.</p>
            <label for="startMfn">MFN от:</label>
            <input type="number" id="startMfn" value="1" min="1">
            
            <label for="endMfn">MFN до:</label>
            <input type="number" id="endMfn" value="100" min="1">
            
            <button onclick="startRangeSync()" id="rangeSyncBtn" class="btn-success">Синхронизировать диапазон</button>
        </div>

        <!-- Статистика -->
        <div id="statsSection" style="display: none;">
            <h3>📊 Статистика синхронизации</h3>
            <div class="stats" id="stats"></div>
        </div>

        <!-- Лог -->
        <div id="log"></div>
    </div>

    <script>
        // CSRF токен для AJAX запросов
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        function log(message, type = 'info') {
            const logDiv = document.getElementById('log');
            const timestamp = new Date().toLocaleTimeString();
            const colorClass = type === 'error' ? '#ff6b6b' : type === 'success' ? '#51cf66' : '#339af0';
            
            logDiv.innerHTML += `<span style="color: ${colorClass}">[${timestamp}] ${message}</span>\n`;
            logDiv.scrollTop = logDiv.scrollHeight;
            
            // Также выводим в консоль браузера
            console.log(`[${timestamp}] ${message}`);
        }

        function showStatus(message, type = 'info') {
            const statusDiv = document.createElement('div');
            statusDiv.className = `status ${type}`;
            statusDiv.textContent = message;
            
            // Находим последнюю секцию и добавляем статус после неё
            const sections = document.querySelectorAll('.section');
            const lastSection = sections[sections.length - 1];
            lastSection.parentNode.insertBefore(statusDiv, lastSection.nextSibling);
            
            setTimeout(() => statusDiv.remove(), 5000);
        }

        function displayStats(stats) {
            const statsSection = document.getElementById('statsSection');
            const statsDiv = document.getElementById('stats');
            
            statsDiv.innerHTML = `
                <div class="stat-item">
                    <div class="stat-number">${stats.total_records || 0}</div>
                    <div class="stat-label">Всего записей</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">${stats.processed_records || 0}</div>
                    <div class="stat-label">Обработано</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">${stats.updated_records || 0}</div>
                    <div class="stat-label">Обновлено</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">${stats.skipped_records || 0}</div>
                    <div class="stat-label">Пропущено</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">${stats.errors || 0}</div>
                    <div class="stat-label">Ошибок</div>
                </div>
            `;
            
            statsSection.style.display = 'block';
            
            // Выводим статистику в консоль
            console.group('📊 Статистика синхронизации');
            console.log('Всего записей:', stats.total_records || 0);
            console.log('Обработано:', stats.processed_records || 0);
            console.log('Обновлено:', stats.updated_records || 0);
            console.log('Пропущено:', stats.skipped_records || 0);
            console.log('Ошибок:', stats.errors || 0);
            console.groupEnd();
        }

        async function testConnection() {
            const btn = document.getElementById('testBtn');
            const statusDiv = document.getElementById('connectionStatus');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="loading">⏳</span> Тестирование...';
            
            log('Проверка подключений...');
            console.group('🔍 Тест подключения');
            
            try {
                const response = await fetch('/sync/test-connection');
                const data = await response.json();
                
                console.log('Ответ сервера:', data);
                
                if (data.success) {
                    statusDiv.innerHTML = `
                        <div class="status success">
                            ✅ Подключения работают<br>
                            📊 ИРБИС: ${data.irbis_max_mfn} записей<br>
                            📚 MySQL: ${data.books_count} книг в таблице
                        </div>
                    `;
                    log('Подключения успешно проверены', 'success');
                    console.log('✅ ИРБИС записей:', data.irbis_max_mfn);
                    console.log('✅ MySQL книг:', data.books_count);
                } else {
                    throw new Error(data.error || 'Ошибка подключения');
                }
            } catch (error) {
                statusDiv.innerHTML = `<div class="status error">❌ ${error.message}</div>`;
                log(`Ошибка подключения: ${error.message}`, 'error');
                console.error('❌ Ошибка подключения:', error);
            }
            
            console.groupEnd();
            btn.disabled = false;
            btn.innerHTML = 'Тестировать подключения';
        }

        async function debugRecord() {
            const btn = document.getElementById('debugBtn');
            const statusDiv = document.getElementById('debugStatus');
            const mfn = document.getElementById('debugMfn').value;
            
            if (!mfn || mfn < 1) {
                showStatus('Укажите корректный MFN для отладки', 'error');
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<span class="loading">⏳</span> Отладка...';
            
            log(`Отладка записи MFN=${mfn}...`);
            console.group(`🐛 Отладка записи MFN=${mfn}`);
            
            try {
                const response = await fetch(`/sync/debug-record?mfn=${mfn}`);
                const data = await response.json();
                
                console.log('Полный ответ сервера:', data);
                
                if (data.success && data.debug_info) {
                    const debugInfo = data.debug_info;
                    
                    // Выводим всю отладочную информацию в консоль
                    console.log('📋 MFN:', debugInfo.mfn);
                    console.log('🔧 Тип записи:', debugInfo.record_type);
                    
                    if (debugInfo.record_methods) {
                        console.log('⚙️ Методы записи:', debugInfo.record_methods);
                    }
                    
                    if (debugInfo.record_properties) {
                        console.log('📦 Свойства записи:', debugInfo.record_properties);
                    }
                    
                    console.log('🏷️ Поле 803 (ID):', debugInfo.field_803);
                    console.log('📄 Поле 802 (ART):', debugInfo.field_802);
                    
                    if (debugInfo.error) {
                        console.error('❌ Ошибка:', debugInfo.error);
                        console.error('❌ Тип ошибки:', debugInfo.error_type);
                    }
                    
                    // Показываем информацию на странице
                    const debugHtml = `
                        <div class="debug-info">
                            <strong>MFN:</strong> ${debugInfo.mfn}<br>
                            <strong>Тип записи:</strong> ${debugInfo.record_type || 'не определен'}<br>
                            <strong>Поле 803 (ID):</strong> ${debugInfo.field_803 || 'пусто'}<br>
                            <strong>Поле 802 (ART):</strong> ${debugInfo.field_802 || 'пусто'}<br>
                            ${debugInfo.error ? `<strong style="color: red;">Ошибка:</strong> ${debugInfo.error}<br>` : ''}
                            <em>Подробная информация выведена в консоль браузера (F12)</em>
                        </div>
                    `;
                    
                    statusDiv.innerHTML = `<div class="status info">🐛 Отладочная информация получена</div>${debugHtml}`;
                    log(`Отладка MFN=${mfn} завершена - см. консоль`, 'success');
                    
                } else {
                    throw new Error(data.error || 'Ошибка получения отладочной информации');
                }
                
            } catch (error) {
                statusDiv.innerHTML = `<div class="status error">❌ ${error.message}</div>`;
                log(`Ошибка отладки MFN=${mfn}: ${error.message}`, 'error');
                console.error('❌ Ошибка отладки:', error);
            }
            
            console.groupEnd();
            btn.disabled = false;
            btn.innerHTML = 'Отладить запись';
        }

        async function startFullSync() {
            const btn = document.getElementById('fullSyncBtn');
            
            if (!confirm('Вы уверены, что хотите запустить полную синхронизацию? Это может занять много времени.')) {
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<span class="loading">⏳</span> Синхронизация...';
            
            log('Запуск полной синхронизации...');
            console.group('🔄 Полная синхронизация');
            
            try {
                const response = await fetch('/sync/irbis-to-mysql');
                const data = await response.json();
                
                console.log('Ответ сервера:', data);
                
                // Отображаем логи из сервера
                if (data.log && Array.isArray(data.log)) {
                    console.log('📝 Логи с сервера:');
                    data.log.forEach((logMessage, index) => {
                        log(logMessage, 'info');
                        console.log(`${index + 1}. ${logMessage}`);
                    });
                }
                
                if (data.success) {
                    showStatus('Полная синхронизация завершена успешно!', 'success');
                    log('Полная синхронизация завершена успешно', 'success');
                    console.log('✅ Полная синхронизация завершена успешно');
                    displayStats(data.stats);
                } else {
                    throw new Error(data.message || 'Ошибка синхронизации');
                }
            } catch (error) {
                showStatus(`Ошибка синхронизации: ${error.message}`, 'error');
                log(`Ошибка полной синхронизации: ${error.message}`, 'error');
                console.error('❌ Ошибка полной синхронизации:', error);
            }
            
            console.groupEnd();
            btn.disabled = false;
            btn.innerHTML = 'Запустить полную синхронизацию';
        }

        async function startRangeSync() {
            const btn = document.getElementById('rangeSyncBtn');
            const startMfn = document.getElementById('startMfn').value;
            const endMfn = document.getElementById('endMfn').value;
            
            if (!startMfn || !endMfn || parseInt(startMfn) > parseInt(endMfn)) {
                showStatus('Укажите корректный диапазон MFN', 'error');
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<span class="loading">⏳</span> Синхронизация...';
            
            log(`Запуск синхронизации диапазона ${startMfn}-${endMfn}...`);
            console.group(`🎯 Синхронизация диапазона ${startMfn}-${endMfn}`);
            
            try {
                const response = await fetch('/sync/range', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        start_mfn: parseInt(startMfn),
                        end_mfn: parseInt(endMfn)
                    })
                });
                
                const data = await response.json();
                console.log('Ответ сервера:', data);
                
                // Отображаем логи из сервера
                if (data.log && Array.isArray(data.log)) {
                    console.log('📝 Логи с сервера:');
                    data.log.forEach((logMessage, index) => {
                        log(logMessage, 'info');
                        console.log(`${index + 1}. ${logMessage}`);
                    });
                }
                
                if (data.success) {
                    showStatus(`Синхронизация диапазона ${startMfn}-${endMfn} завершена!`, 'success');
                    log(`Синхронизация диапазона завершена успешно`, 'success');
                    console.log(`✅ Синхронизация диапазона ${startMfn}-${endMfn} завершена успешно`);
                    displayStats(data.stats);
                } else {
                    throw new Error(data.message || 'Ошибка синхронизации');
                }
            } catch (error) {
                showStatus(`Ошибка синхронизации: ${error.message}`, 'error');
                log(`Ошибка синхронизации диапазона: ${error.message}`, 'error');
                console.error('❌ Ошибка синхронизации диапазона:', error);
            }
            
            console.groupEnd();
            btn.disabled = false;
            btn.innerHTML = 'Синхронизировать диапазон';
        }

        // При загрузке страницы выводим информацию о консоли
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔄 Страница синхронизации ИРБИС → MySQL загружена');
            console.log('💡 Вся отладочная информация будет выводиться в эту консоль');
            console.log('🔧 Откройте инструменты разработчика (F12) для просмотра всех логов');
            
            log('Страница синхронизации загружена. Откройте консоль браузера (F12) для просмотра отладочной информации.', 'info');
        });
    </script>
</body>
</html>