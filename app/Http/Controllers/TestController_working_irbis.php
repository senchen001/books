<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index()
    {
        require_once base_path('irbis_class.inc');
$source_db = 'NEB';      // Исходная база
$target_db = 'TESTNEB'; // Целевая база (замените на нужное название)
$record_id = 44;          // ID записи для переноса
$id_field_num = 1;        // Номер поля для ID записи

// Подключение к исходной базе
$irbis_source = new \irbis64('127.0.0.1', 6666, 1, 1, $source_db);

// Подключение к целевой базе
$irbis_target = new \irbis64('127.0.0.1', 6666, 1, 1, $target_db);

try {
    // Авторизация в исходной базе
    if (!$irbis_source->login()) {
        throw new \Exception("Ошибка подключения к исходной базе: " . $irbis_source->error());
    }

    
    echo "Поиск записи с ID=$record_id в базе $source_db..." . PHP_EOL;
    
    // Поиск записи в исходной базе
    $search_result = $irbis_source->term_records("ID=" . $record_id, 0, 1);
    
    if ($irbis_source->error_code != 0) {
        throw new \Exception("Ошибка поиска: " . $irbis_source->error());
    }
    
    if (!$search_result || empty($search_result)) {
        throw new \Exception("Запись с ID=$record_id не найдена в базе $source_db");
    }
    
    // Получаем MFN первой найденной записи
    $source_mfn = $search_result[0];
    echo "Найдена запись с MFN=$source_mfn" . PHP_EOL;
    
    // Читаем запись из исходной базы
    $record = $irbis_source->record_read($source_mfn);
    
    if ($irbis_source->error_code != 0) {
        throw new \Exception("Ошибка чтения записи: " . $irbis_source->error());
    }
    
    echo "Запись успешно прочитана из исходной базы" . PHP_EOL;
    
    // Проверяем, существует ли уже запись с таким ID в целевой базе
    $existing_check = $irbis_target->term_records("ID=" . $record_id, 0, 1);
    
    // Ошибка -202 "Термин не существует" - это нормально, значит записи с таким ID нет
    if ($irbis_target->error_code != 0 && $irbis_target->error_code != -202) {
        throw new \Exception("Ошибка проверки целевой базы: " . $irbis_target->error());
    }
    
    if ($existing_check && !empty($existing_check) && $irbis_target->error_code != -202) {
        echo "ВНИМАНИЕ: Запись с ID=$record_id уже существует в базе $target_db" . PHP_EOL;
        echo "Выберите действие: перезаписать существующую запись? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $choice = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($choice) !== 'y') {
            echo "Операция отменена пользователем" . PHP_EOL;
            exit;
        }
        
        // Если решили перезаписать, читаем существующую запись чтобы сохранить её MFN
        $existing_mfn = $existing_check[0];
        $existing_record = $irbis_target->record_read($existing_mfn);
        
        // Подготавливаем массив записи для сохранения с существующим MFN
        $record_array = $record->getRecordArray();
        $record_array['mfn'] = $existing_record->getMFN();
        $record_array['ver'] = $existing_record->getRecordArray()['ver'];
        
        echo "Перезапись существующей записи с MFN=$existing_mfn..." . PHP_EOL;
    } else {
        // Создаем новую запись (MFN=0 означает новую запись)
        $record_array = $record->getRecordArray();
        $record_array['mfn'] = 0;
        $record_array['ver'] = 0;
        
        echo "Создание новой записи в базе $target_db..." . PHP_EOL;
    }
    
    // Сохраняем запись в целевой базе
    $write_result = $irbis_target->record_write($record_array, false, true);
    
    if ($write_result !== '') {
        throw new \Exception("Ошибка сохранения записи: " . $irbis_target->error($write_result));
    }
    
    echo "Запись успешно перенесена в базу $target_db!" . PHP_EOL;
    
    // Выводим некоторую информацию о перенесенной записи
    echo "Информация о записи:" . PHP_EOL;
    echo "- Поле 200: " . $record->getField(200, 1) . PHP_EOL;
    echo "- Поле $id_field_num (ID): " . $record->getField($id_field_num, 1) . PHP_EOL;
    echo "- Количество полей: " . count($record->getRecordArray()['fields']) . PHP_EOL;
    
} catch (Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . PHP_EOL;
} finally {
    // Завершаем сессии
    $irbis_source->logout();
    $irbis_target->logout();
    echo "Сессии завершены" . PHP_EOL;
}

    }
}
