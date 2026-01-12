<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IrbisToMySqlSyncController extends Controller
{
    private $irbis;
    private $syncStats = [
        'total_records' => 0,
        'processed_records' => 0,
        'updated_records' => 0,
        'skipped_records' => 0,
        'errors' => 0
    ];
    private $logMessages = [];

    public function syncNebToBooks(Request $request)
    {
        // Подключаем библиотеку ИРБИС
        $this->initializeEnvironment();
        require_once base_path('irbis_class.inc');

        try {
            // Подключение к базе ИРБИС
            $this->irbis = new \irbis64(
                config('irbis.host'),
                config('irbis.port'),
                config('irbis.login'),
                config('irbis.password'),
                config('irbis.database')
            );

            // Авторизация в ИРБИС
            if (!$this->irbis->login()) {
                throw new \Exception("Ошибка подключения к базе ИРБИС: " . $this->irbis->error());
            }

            $this->addLog("Подключение к базе ИРБИС установлено");

            // Получаем максимальный MFN
            $max_mfn = $this->irbis->mfn_max();
            if ($max_mfn === false) {
                throw new \Exception("Ошибка получения максимального MFN: " . $this->irbis->error());
            }

            $this->syncStats['total_records'] = $max_mfn;
            $this->addLog("Найдено записей в базе NEB: $max_mfn");

            // Обрабатываем записи
            $this->processIrbisRecords($max_mfn);

            // Логируем статистику
            $this->logSyncStats();

            return response()->json([
                'success' => true,
                'message' => 'Синхронизация завершена успешно',
                'stats' => $this->syncStats,
                'log' => $this->logMessages
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка синхронизации ИРБИС -> MySQL: ' . $e->getMessage());
            $this->addLog("ОШИБКА: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'stats' => $this->syncStats,
                'log' => $this->logMessages
            ], 500);
            
        } finally {
            // Завершаем сессию ИРБИС
            if (isset($this->irbis)) {
                $this->irbis->logout();
                $this->addLog("Сессия ИРБИС завершена");
            }
        }
    }

    /**
     * Обработка записей ИРБИС
     */
    private function processIrbisRecords($max_mfn)
    {
        $batch_size = config('irbis.batch_size', 100);
        
        for ($mfn = 1; $mfn <= $max_mfn; $mfn++) {
            try {
                $this->syncStats['processed_records']++;
                
                // Читаем запись из ИРБИС с дополнительной обработкой ошибок
                $record = $this->safeReadRecord($mfn);
                
                if ($record === false) {
                    $this->syncStats['skipped_records']++;
                    continue;
                }

                // Извлекаем нужные поля с безопасной проверкой
                $field_803 = $this->getFieldValue($record, 803);
                $field_802 = $this->getFieldValue($record, 802);

                // Проверяем, что поле 803 не пустое
                if (empty($field_803)) {
                    $this->syncStats['skipped_records']++;
                    continue;
                }

                // Синхронизируем с MySQL
                $this->syncWithMysql($field_803, $field_802, $mfn);

                // Логируем прогресс каждые 100 записей
                if ($mfn % $batch_size == 0) {
                    $progress = round(($mfn / $max_mfn) * 100, 1);
                    $this->addLog("Обработано: $mfn/$max_mfn ($progress%)");
                }

            } catch (\Exception $e) {
                $this->syncStats['errors']++;
                Log::error("Ошибка обработки записи MFN=$mfn: " . $e->getMessage());
                $this->addLog("Ошибка MFN=$mfn: " . $e->getMessage());
                continue;
            }
        }
    }

    /**
     * Безопасное чтение записи из ИРБИС с обработкой ошибок протокола
     */
    private function safeReadRecord($mfn)
    {
        try {
            // Пытаемся прочитать запись
            $record = $this->irbis->record_read($mfn);
            
            // Проверяем код ошибки ИРБИС
            if ($this->irbis->error_code != 0) {
                // Пропускаем удаленные или недоступные записи
                $ignoredCodes = config('irbis.ignored_error_codes', [-603, -601, -140]);
                if (in_array($this->irbis->error_code, $ignoredCodes)) {
                    return false;
                }
                
                // Для других ошибок логируем и пропускаем
                $this->addLog("Ошибка чтения MFN=$mfn: " . $this->irbis->error());
                return false;
            }
            
            return $record;
            
        } catch (\Error $e) {
            // Ловим фатальные ошибки PHP (например, Undefined array key)
            $this->addLog("Ошибка протокола при чтении MFN=$mfn: " . $e->getMessage());
            return false;
            
        } catch (\Exception $e) {
            // Ловим обычные исключения
            $this->addLog("Исключение при чтении MFN=$mfn: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Безопасное получение значения поля из записи ИРБИС
     */
    private function getFieldValue($record, $fieldTag, $occurrence = 1)
    {
        try {
            // Проверяем, что запись существует и корректна
            if (!$record || !is_object($record)) {
                return null;
            }

            // Разные варианты получения поля в зависимости от версии библиотеки
            if (method_exists($record, 'getField')) {
                $field = $record->getField($fieldTag, $occurrence);
            } elseif (method_exists($record, 'field')) {
                $field = $record->field($fieldTag, $occurrence);
            } elseif (property_exists($record, 'fields') && is_array($record->fields) && isset($record->fields[$fieldTag])) {
                $fields = $record->fields[$fieldTag];
                if (is_array($fields) && isset($fields[$occurrence])) {
                    $field = $fields[$occurrence];
                } else {
                    return null;
                }
            } else {
                return null;
            }

            // Если поле не найдено
            if (!$field) {
                return null;
            }

            // Если поле - массив с подполями
            if (is_array($field)) {
                // Проверяем наличие ключа '*' (полное значение поля)
                if (isset($field['*'])) {
                    return trim((string)$field['*']);
                }
                // Если нет ключа '*', берем первое значение
                if (!empty($field)) {
                    $firstValue = reset($field);
                    return trim((string)$firstValue);
                }
                return null;
            }

            // Если поле - объект
            if (is_object($field)) {
                if (method_exists($field, 'toString')) {
                    return trim($field->toString());
                } elseif (method_exists($field, '__toString')) {
                    return trim((string)$field);
                } elseif (property_exists($field, 'value')) {
                    return trim((string)$field->value);
                } elseif (property_exists($field, '*')) {
                    return trim((string)$field->{'*'});
                }
            }

            // Если поле - простая строка
            return $field ? trim((string)$field) : null;

        } catch (\Error $e) {
            Log::warning("Фатальная ошибка получения поля $fieldTag: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            Log::warning("Ошибка получения поля $fieldTag: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Синхронизация с MySQL
     */
    private function syncWithMysql($field_803, $field_802, $mfn)
    {
        try {
            // Проверяем существование записи в таблице books
            $book = DB::table('books')->where('id', $field_803)->first();
            
            if ($book) {
                // ИСПРАВЛЕНО: Обрабатываем случай когда field_802 может быть null
                $art_value = $field_802;
                
                // Если поле ART не может быть null в БД, устанавливаем пустую строку
                if ($art_value === null || $art_value === '') {
                    $art_value = ''; // или любое другое значение по умолчанию
                }
                
                // Обновляем поле ART
                $updated = DB::table('books')
                    ->where('id', $field_803)
                    ->update(['ART' => $art_value]);
                
                if ($updated) {
                    $this->syncStats['updated_records']++;
                    $art_display = $art_value ?: '(пустое)';
                    $this->addLog("Обновлена запись ID=$field_803, MFN=$mfn, ART='$art_display'");
                }
            } else {
                // Запись не найдена в MySQL
                $this->syncStats['skipped_records']++;
                // Можно добавить логирование для отладки
                // $this->addLog("Запись ID=$field_803 не найдена в таблице books (MFN=$mfn)");
            }

        } catch (\Exception $e) {
            $this->syncStats['errors']++;
            throw new \Exception("Ошибка работы с MySQL для ID=$field_803: " . $e->getMessage());
        }
    }

    /**
     * Добавить сообщение в лог
     */
    private function addLog($message)
    {
        $this->logMessages[] = $message;
        Log::info($message);
    }

    /**
     * Логирование статистики синхронизации
     */
    private function logSyncStats()
    {
        $this->addLog("=== СТАТИСТИКА СИНХРОНИЗАЦИИ ===");
        $this->addLog("Всего записей в ИРБИС: " . $this->syncStats['total_records']);
        $this->addLog("Обработано записей: " . $this->syncStats['processed_records']);
        $this->addLog("Обновлено в MySQL: " . $this->syncStats['updated_records']);
        $this->addLog("Пропущено записей: " . $this->syncStats['skipped_records']);
        $this->addLog("Ошибок: " . $this->syncStats['errors']);
        $this->addLog("===============================");
    }

    /**
     * Инициализация окружения
     */
    private function initializeEnvironment()
    {
        set_time_limit(config('irbis.time_limit', 0));
        ini_set('memory_limit', config('irbis.memory_limit', '512M'));
    }

    /**
     * Тестовый метод для проверки подключения
     */
    public function testConnection()
    {
        require_once base_path('irbis_class.inc');
        
        try {
            $irbis = new \irbis64(
                config('irbis.host'),
                config('irbis.port'),
                config('irbis.login'),
                config('irbis.password'),
                config('irbis.database')
            );
            
            if (!$irbis->login()) {
                throw new \Exception("Ошибка подключения к ИРБИС: " . $irbis->error());
            }
            
            $max_mfn = $irbis->mfn_max();
            $irbis->logout();
            
            // Тестируем MySQL
            $books_count = DB::table('books')->count();
            
            return response()->json([
                'success' => true,
                'irbis_connected' => true,
                'irbis_max_mfn' => $max_mfn,
                'mysql_connected' => true,
                'books_count' => $books_count
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Синхронизация только указанного диапазона MFN
     */
    public function syncRange(Request $request)
    {
        $this->initializeEnvironment();
        
        $request->validate([
            'start_mfn' => 'required|integer|min:1',
            'end_mfn' => 'required|integer|min:1',
        ]);

        $start_mfn = $request->input('start_mfn');
        $end_mfn = $request->input('end_mfn');

        if ($start_mfn > $end_mfn) {
            return response()->json([
                'success' => false,
                'message' => 'start_mfn не может быть больше end_mfn'
            ], 400);
        }

        require_once base_path('irbis_class.inc');

        try {
            $this->irbis = new \irbis64(
                config('irbis.host'),
                config('irbis.port'),
                config('irbis.login'),
                config('irbis.password'),
                config('irbis.database')
            );

            if (!$this->irbis->login()) {
                throw new \Exception("Ошибка подключения к ИРБИС: " . $this->irbis->error());
            }

            $this->addLog("Синхронизация диапазона MFN: $start_mfn - $end_mfn");

            for ($mfn = $start_mfn; $mfn <= $end_mfn; $mfn++) {
                try {
                    $this->syncStats['processed_records']++;
                    
                    // Используем безопасное чтение записи
                    $record = $this->safeReadRecord($mfn);
                    
                    if ($record === false) {
                        $this->syncStats['skipped_records']++;
                        continue;
                    }

                    // Используем безопасное получение полей
                    $field_803 = $this->getFieldValue($record, 803);
                    $field_802 = $this->getFieldValue($record, 802);

                    if (empty($field_803)) {
                        $this->syncStats['skipped_records']++;
                        continue;
                    }

                    $this->syncWithMysql($field_803, $field_802, $mfn);

                } catch (\Exception $e) {
                    $this->syncStats['errors']++;
                    Log::error("Ошибка обработки записи MFN=$mfn: " . $e->getMessage());
                    $this->addLog("Ошибка MFN=$mfn: " . $e->getMessage());
                    continue;
                }
            }

            $this->logSyncStats();

            return response()->json([
                'success' => true,
                'message' => "Синхронизация диапазона $start_mfn-$end_mfn завершена",
                'stats' => $this->syncStats,
                'log' => $this->logMessages
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка синхронизации диапазона: ' . $e->getMessage());
            $this->addLog("ОШИБКА: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'stats' => $this->syncStats,
                'log' => $this->logMessages
            ], 500);
            
        } finally {
            if (isset($this->irbis)) {
                $this->irbis->logout();
                $this->addLog("Сессия ИРБИС завершена");
            }
        }
    }

    /**
     * Отладочный метод для исследования структуры записи
     */
    public function debugRecord(Request $request)
    {
        $mfn = $request->input('mfn', 1);
        
        require_once base_path('irbis_class.inc');
        
        try {
            $irbis = new \irbis64(
                config('irbis.host'),
                config('irbis.port'),
                config('irbis.login'),
                config('irbis.password'),
                config('irbis.database')
            );
            
            if (!$irbis->login()) {
                throw new \Exception("Ошибка подключения к ИРБИС: " . $irbis->error());
            }
            
            // Используем try-catch для безопасного чтения записи
            try {
                $record = $irbis->record_read($mfn);
                
                if ($irbis->error_code != 0) {
                    throw new \Exception("Ошибка чтения записи MFN=$mfn: " . $irbis->error());
                }
                
                // Исследуем структуру записи
                $debug_info = [
                    'mfn' => $mfn,
                    'record_type' => get_class($record),
                    'record_methods' => get_class_methods($record),
                    'record_properties' => get_object_vars($record),
                    'field_803' => $this->getFieldValue($record, 803),
                    'field_802' => $this->getFieldValue($record, 802),
                ];
                
            } catch (\Error $e) {
                // Ловим фатальные ошибки при чтении записи
                $debug_info = [
                    'mfn' => $mfn,
                    'error' => 'Ошибка протокола: ' . $e->getMessage(),
                    'error_type' => 'Fatal Error'
                ];
            }
            
            $irbis->logout();
            
            return response()->json([
                'success' => true,
                'debug_info' => $debug_info
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}