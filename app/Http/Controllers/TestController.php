<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index()
    {
require_once base_path('irbis_class.inc');

// Настройки
$db_name = 'IBIS';        // Название базы данных
$target_mfn = 89;         // MFN записи для изменения
$field_num = 961;         // Номер поля
$field_value = 'иванова'; // Значение для записи

// Подключение к базе
$irbis = new \irbis64('127.0.0.1', 6666, 1, 1, $db_name);

try {
    // Авторизация
    if (!$irbis->login()) {
        throw new \Exception("Ошибка подключения к базе: " . $irbis->error());
    }
    
    echo "Подключение к базе $db_name успешно" . PHP_EOL;
    echo "Чтение записи с MFN=$target_mfn..." . PHP_EOL;
    
    // Читаем запись с блокировкой для изменения
    $record = $irbis->record_read($target_mfn, true);
    
    if ($irbis->error_code != 0) {
        throw new \Exception("Ошибка чтения записи: " . $irbis->error());
    }
    
    echo "Запись успешно прочитана и заблокирована" . PHP_EOL;
    
    // Проверяем, есть ли уже данные в поле 961
    $existing_value = $record->getField($field_num, 1);
    if (!empty($existing_value)) {
        echo "ВНИМАНИЕ: В поле $field_num уже есть значение: '$existing_value'" . PHP_EOL;
        echo "Перезаписать? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $choice = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($choice) !== 'y') {
            echo "Операция отменена пользователем" . PHP_EOL;
            $irbis->logout();
            exit;
        }
    }
    
    // Записываем новое значение в поле
    $record->setField($field_value, $field_num, 1);
    
    echo "Устанавливаем в поле $field_num значение: '$field_value'" . PHP_EOL;
    
    // Сохраняем запись
    $write_result = $irbis->record_write($record->getRecordArray(), true, true);
    
    if ($write_result !== '') {
        throw new \Exception("Ошибка сохранения записи: " . $irbis->error($write_result));
    }
    
    echo "Запись успешно сохранена!" . PHP_EOL;
    
    // Проверяем результат - читаем запись заново
    echo "Проверка результата..." . PHP_EOL;
    $check_record = $irbis->record_read($target_mfn);
    
    if ($irbis->error_code != 0) {
        echo "Предупреждение: не удалось прочитать запись для проверки" . PHP_EOL;
    } else {
        $saved_value = $check_record->getField($field_num, 1);
        echo "Сохраненное значение в поле $field_num: '$saved_value'" . PHP_EOL;
        
        if ($saved_value === $field_value) {
            echo "✓ Значение успешно сохранено!" . PHP_EOL;
        } else {
            echo "⚠ Внимание: сохраненное значение отличается от ожидаемого" . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . PHP_EOL;
} finally {
    // Завершаем сессию
    $irbis->logout();
    echo "Сессия завершена" . PHP_EOL;
}
    }
}
?>