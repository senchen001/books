<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактор поля ИРБИС</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .field-group {
            margin: 20px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: #4CAF50;
        }
        .status {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            font-weight: bold;
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
        .status.loading {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .info {
            background-color: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Редактор поля ИРБИС</h1>
        
        <div class="info">
            <strong>Запись:</strong> MFN 89<br>
            <strong>Поле:</strong> 961<br>
            <strong>Текущая дата и время:</strong> <?php echo date('Y-m-d H:i:s'); ?>
        </div>

        <div class="field-group">
            <label for="field961">Поле 961:</label>
            <input type="text" id="field961" name="field961" value="<?php echo htmlspecialchars(getField961Value()); ?>" placeholder="Введите значение...">
        </div>

        <div id="status" class="status" style="display: none;"></div>
    </div>

    <script>
        let originalValue = document.getElementById('field961').value;
        let isUpdating = false;

        // Функция для отображения статуса
        function showStatus(message, type) {
            const statusDiv = document.getElementById('status');
            statusDiv.textContent = message;
            statusDiv.className = 'status ' + type;
            statusDiv.style.display = 'block';
            
            if (type === 'success') {
                setTimeout(() => {
                    statusDiv.style.display = 'none';
                }, 3000);
            }
        }

        // Функция для сохранения значения
        function saveFieldValue(value) {
            if (isUpdating) return;
            
            isUpdating = true;
            showStatus('Сохранение...', 'loading');

            // Отправляем AJAX запрос
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=save_field&field_value=' + encodeURIComponent(value)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showStatus('Значение успешно сохранено', 'success');
                    originalValue = value;
                } else {
                    showStatus('Ошибка: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showStatus('Ошибка соединения: ' + error.message, 'error');
            })
            .finally(() => {
                isUpdating = false;
            });
        }

        // Обработчик потери фокуса поля ввода
        document.getElementById('field961').addEventListener('blur', function() {
            const currentValue = this.value.trim();
            if (currentValue !== originalValue) {
                saveFieldValue(currentValue);
            }
        });

        // Обработчик клика по странице вне поля ввода
        document.addEventListener('click', function(event) {
            const field = document.getElementById('field961');
            if (event.target !== field) {
                const currentValue = field.value.trim();
                if (currentValue !== originalValue) {
                    saveFieldValue(currentValue);
                }
            }
        });

        // Обработчик нажатия Enter
        document.getElementById('field961').addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                this.blur(); // Убираем фокус, что вызовет сохранение
            }
        });
    </script>

    <?php
    // Обработка AJAX запросов
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_field') {
        header('Content-Type: application/json');
        
        try {
            require_once('irbis_class.inc');
            
            $field_value = $_POST['field_value'] ?? '';
            $db_name = 'IBIS';
            $target_mfn = 89;
            $field_num = 961;
            
            $irbis = new irbis64('127.0.0.1', 8888, 1, 1, $db_name);
            
            if (!$irbis->login()) {
                throw new Exception($irbis->error());
            }
            
            $record = $irbis->record_read($target_mfn, true);
            
            if ($irbis->error_code != 0) {
                throw new Exception($irbis->error());
            }
            
            $record->setField($field_value, $field_num, 1);
            
            $write_result = $irbis->record_write($record->getRecordArray(), true, true);
            
            if ($write_result !== '') {
                throw new Exception($irbis->error($write_result));
            }
            
            $irbis->logout();
            
            echo json_encode(['success' => true, 'message' => 'Значение сохранено']);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        
        exit;
    }

    // Функция для получения текущего значения поля 961
    function getField961Value() {
        try {
            require_once('irbis_class.inc');
            
            $db_name = 'IBIS';
            $target_mfn = 89;
            $field_num = 961;
            
            $irbis = new irbis64('127.0.0.1', 8888, 1, 1, $db_name);
            
            if (!$irbis->login()) {
                return '';
            }
            
            $record = $irbis->record_read($target_mfn);
            
            if ($irbis->error_code != 0) {
                $irbis->logout();
                return '';
            }
            
            $value = $record->getField($field_num, 1);
            $irbis->logout();
            
            return $value;
            
        } catch (Exception $e) {
            return '';
        }
    }
    ?>
</body>
</html>