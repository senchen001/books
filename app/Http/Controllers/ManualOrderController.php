<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;

class ManualOrderController extends Controller
{
    private $irbis;
    
    
    public function updateDatabase()
    {
        // Добавляем заголовки для JSON-ответа
        header('Content-Type: application/json; charset=utf-8');
        
        Log::info('=== НАЧАЛО ОБНОВЛЕНИЯ БАЗЫ ИЗ ИРБИС ===');
        
        try {
            // Проверяем существование файла класса ИРБИС
            $irbisClassPath = base_path('irbis_class.inc');
            if (!file_exists($irbisClassPath)) {
                throw new \Exception('Файл irbis_class.inc не найден по пути: ' . $irbisClassPath);
            }
            
            // Подключаем класс ИРБИС
            require_once $irbisClassPath;
            
            // Проверяем существование класса
            if (!class_exists('irbis64')) {
                throw new \Exception('Класс irbis64 не найден в файле irbis_class.inc');
            }

            // Настройки подключения к ИРБИС (можно вынести в .env)
            $server = env('IRBIS_SERVER', '127.0.0.1');
            $port = env('IRBIS_PORT', 6666);
            $login = env('IRBIS_LOGIN', '1');
            $password = env('IRBIS_PASSWORD', '1');
            $database = env('IRBIS_DATABASE', 'NEB');

            Log::info("Подключаемся к ИРБИС: {$server}:{$port}");

            // Создаем экземпляр класса ИРБИС
            $this->irbis = new \irbis64($server, $port, $login, $password, $database);
            
            // Авторизуемся
            if (!$this->irbis->login()) {
                throw new \Exception('Ошибка авторизации в ИРБИС: ' . $this->irbis->error());
            }

            Log::info('Успешная авторизация в ИРБИС');

            // Получаем текущую и предыдущую даты в формате ГГГГММДД
            $currentDate = date('Ymd');
            $previousDate = date('Ymd', strtotime('-1 day'));
            
            Log::info("Ищем термины для дат: {$currentDate} и {$previousDate}");

            // Более эффективный поиск терминов
            $validTerms = $this->findValidTerms([$currentDate, $previousDate]);

            Log::info("Всего найдено подходящих терминов: " . count($validTerms));

            if (empty($validTerms)) {
                $this->irbis->logout();
                return response()->json([
                    'success' => true,
                    'message' => 'Обновление завершено. Термины с указанными датами не найдены.',
                    'updated_count' => 0
                ]);
            }

            $updatedCount = 0;
            $processedRecords = [];
            $errors = [];

            // Обрабатываем каждый найденный термин
            foreach ($validTerms as $termInfo) {
                try {
                    $termUpdates = $this->processTermRecords($termInfo, $processedRecords);
                    $updatedCount += $termUpdates;
                } catch (\Exception $e) {
                    $errors[] = "Ошибка обработки термина {$termInfo['term']}: " . $e->getMessage();
                    Log::error("Ошибка обработки термина {$termInfo['term']}: " . $e->getMessage());
                }
            }

            // Завершаем сессию ИРБИС
            $this->irbis->logout();
            
            Log::info("=== ОБНОВЛЕНИЕ ЗАВЕРШЕНО ===");
            Log::info("Обновлено записей: {$updatedCount}");
            if (!empty($errors)) {
                Log::warning('Ошибки при обработке: ' . implode('; ', $errors));
            }

            return response()->json([
                'success' => true,
                'message' => "Обновление базы завершено успешно! Обновлено записей: {$updatedCount}",
                'updated_count' => $updatedCount,
                'processed_terms' => count($validTerms),
                'processed_records' => count($processedRecords),
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Критическая ошибка при обновлении базы: ' . $e->getMessage());
            Log::error('Трассировка: ' . $e->getTraceAsString());
            
            // Завершаем сессию ИРБИС если она была открыта
            if (isset($this->irbis)) {
                $this->irbis->logout();
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении базы: ' . $e->getMessage(),
                'error_details' => $e->getTraceAsString()
            ], 500);
        }
    }
    /**
 * Transfer verified orders from uploaded_orders to orders table
 */
public function downloadOrders()
{
    try {
        DB::beginTransaction();
        
        // Получаем все проверенные записи из uploaded_orders
        $verifiedOrders = DB::table('uploaded_orders')
            ->where('is_verified', 1)
            ->get();
            
        if ($verifiedOrders->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Нет проверенных заказов для переноса',
                'transferred_count' => 0
            ]);
        }
        
        $transferredCount = 0;
        $errors = [];
        
        foreach ($verifiedOrders as $order) {
            // Ищем соответствующую книгу по ART и year
            $book = DB::table('books')
                ->where('ART', $order->ART)
                ->where('year', $order->year)
                ->first();
                
            if ($book) {
                // Проверяем, не существует ли уже такой заказ
                $existingOrder = DB::table('orders')
                    ->where('productid', $book->id)
                    ->where('userid', $order->userid)
                    ->where('ordernum', $order->ordernum)
                    ->first();
                    
                if (!$existingOrder) {
                    // Вставляем новую запись в orders
                    DB::table('orders')->insert([
                        'productid' => $book->id,
                        'userid' => $order->userid,
                        'ordernum' => $order->ordernum,
                        'price' => $order->price,
                        'quantity' => $order->quantity,
                        'created_at' => $order->created_at,
                        'updated_at' => now()
                    ]);
                    
                    $transferredCount++;
                } else {
                    $errors[] = "Заказ для книги {$order->ART} уже существует";
                }
            } else {
                $errors[] = "Книга с артикулом {$order->ART} и годом {$order->year} не найдена";
            }
        }
        
        // Удаляем перенесённые записи из uploaded_orders (опционально)
        if ($transferredCount > 0) {
            DB::table('uploaded_orders')
                ->where('is_verified', 1)
                ->delete();
        }
        
        DB::commit();
        
        $message = "Успешно перенесено заказов: {$transferredCount}";
        if (!empty($errors)) {
            $message .= "\nПредупреждения: " . implode(', ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= " и ещё " . (count($errors) - 3) . " предупреждений";
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => $message,
            'transferred_count' => $transferredCount,
            'errors' => $errors
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        
        return response()->json([
            'success' => false,
            'message' => 'Ошибка при переносе заказов: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Поиск валидных терминов для указанных дат
     */
    private function findValidTerms($dates)
    {
        $validTerms = [];
        
        foreach ($dates as $date) {
            $searchTerm = "TH={$date}";
            Log::info("Поиск терминов: {$searchTerm}");
            
            try {
                // Читаем словарь, начиная с термина
                $terms = $this->irbis->terms_read($searchTerm, 1000);
                
                if ($terms === false) {
                    Log::warning("Ошибка чтения словаря для {$searchTerm}: " . $this->irbis->error());
                    continue;
                }

                Log::info("Найдено терминов для {$searchTerm}: " . count($terms));

                // Фильтруем термины по дате
                foreach ($terms as $termData) {
                    $parsedTerm = $this->parseTermData($termData);
                    
                    if (!$parsedTerm || $parsedTerm['count'] == 0) {
                        continue;
                    }
                    
                    // Проверяем, что термин начинается с TH= и содержит нужную дату
                    if ($this->isValidTermForDate($parsedTerm['term'], $date)) {
                        $validTerms[] = [
                            'term' => $parsedTerm['term'],
                            'count' => $parsedTerm['count'],
                            'date' => $date
                        ];
                        Log::info("Найден подходящий термин: {$parsedTerm['term']} (записей: {$parsedTerm['count']})");
                    }
                }
            } catch (\Exception $e) {
                Log::error("Ошибка при поиске терминов для даты {$date}: " . $e->getMessage());
            }
        }
        
        return $validTerms;
    }

    /**
     * Парсинг данных термина из формата "ЧИСЛО_ССЫЛОК#ТЕРМИН=ЗНАЧЕНИЕ"
     */
    private function parseTermData($termData)
    {
        if (empty($termData)) {
            return null;
        }
        
        $parts = explode('#', $termData, 2);
        if (count($parts) < 2) {
            return null;
        }
        
        return [
            'count' => (int)$parts[0],
            'term' => $parts[1]
        ];
    }

    /**
     * Проверка, подходит ли термин для указанной даты
     */
    private function isValidTermForDate($termValue, $targetDate)
    {
        // Проверяем, что термин начинается с TH=
        if (!str_starts_with($termValue, 'TH=')) {
            return false;
        }
        
        // Извлекаем дату из термина (первые 8 цифр после TH=)
        $termDate = substr($termValue, 3, 8);
        
        // Проверяем, что извлеченная дата соответствует целевой
        return $termDate === $targetDate && preg_match('/^\d{8}$/', $termDate);
    }

    /**
     * Обработка записей для конкретного термина
     */
    private function processTermRecords($termInfo, &$processedRecords)
    {
        Log::info("Обрабатываем термин: {$termInfo['term']}");
        
        try {
            // Сначала получаем количество записей
            $recordCount = $this->irbis->term_records($termInfo['term'], 0, 0);
            if ($recordCount === false || empty($recordCount) || $recordCount[0] == 0) {
                Log::warning("Нет записей для термина {$termInfo['term']}");
                return 0;
            }
            
            $totalRecords = (int)$recordCount[0];
            Log::info("Всего записей для термина: {$totalRecords}");
            
            // Получаем все MFN записей
            $mfnList = $this->irbis->term_records($termInfo['term'], $totalRecords, 1);
            
            if ($mfnList === false || empty($mfnList)) {
                Log::warning("Не удалось получить MFN для термина {$termInfo['term']}: " . $this->irbis->error());
                return 0;
            }

            Log::info("Получено MFN записей: " . count($mfnList));
            
            $updatedCount = 0;

            // Обрабатываем каждую запись
            foreach ($mfnList as $mfn) {
                // Избегаем повторной обработки одной записи
                if (in_array($mfn, $processedRecords)) {
                    continue;
                }
                $processedRecords[] = $mfn;

                try {
                    if ($this->processRecord($mfn)) {
                        $updatedCount++;
                    }
                } catch (\Exception $e) {
                    Log::error("Ошибка обработки записи MFN {$mfn}: " . $e->getMessage());
                }
            }
            
            return $updatedCount;
        } catch (\Exception $e) {
            Log::error("Ошибка при обработке термина {$termInfo['term']}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Обработка отдельной записи
     */
private function processRecord($mfn)
    {
        Log::info("Читаем запись MFN: {$mfn}");
       
        try {
            // Используем безопасное чтение записи
            $record = $this->safeReadRecord($mfn);
           
            if ($record === false) {
                Log::warning("Не удалось прочитать запись MFN {$mfn}");
                return false;
            }
            // Получаем поле 803 (внешний ID книги) с безопасной проверкой
            $field803 = $this->getFieldValue($record, 803);
            if (empty($field803)) {
                Log::info("Поле 803 отсутствует в записи MFN {$mfn}");
                return false;
            }
            $externalId = trim($field803);
            Log::info("Найден внешний ID: {$externalId}");
            // Проверяем корректность ID
            if (!is_numeric($externalId) || $externalId <= 0) {
                Log::info("Некорректный внешний ID: {$externalId}");
                return false;
            }
            // Ищем книгу в MySQL по внешнему ID
            $book = DB::table('books')->where('id', $externalId)->first();
           
            if (!$book) {
                Log::info("Книга с ID {$externalId} не найдена в MySQL");
                return false;
            }
            // Получаем поле 802 (артикул) с безопасной проверкой
            $field802 = $this->getFieldValue($record, 802);
            if (empty($field802)) {
                Log::info("Поле 802 отсутствует в записи MFN {$mfn}");
                return false;
            }
            $articleNumber = trim($field802);
            Log::info("Найден артикул: {$articleNumber}");
            
            // Получаем поле 2210 (год) с безопасной проверкой
            $field2210 = $this->getFieldValue($record, 2210);
            if (empty($field2210)) {
                Log::info("Поле 2210 отсутствует в записи MFN {$mfn}");
                return false;
            }
            $year = trim($field2210);
            Log::info("Найден год: {$year}");
            
            // Проверяем, нужно ли обновление
            if ($book->ART === $articleNumber && $book->year === $year) {
                Log::info("Артикул и год уже актуальны для книги ID {$externalId}");
                return false;
            }
            // Обновляем поля ART и year в таблице books
            $updated = DB::table('books')
                ->where('id', $externalId)
                ->update([
                    'ART' => $articleNumber,
                    'year' => $year,
                ]);
            if ($updated) {
                Log::info("Обновлена книга ID {$externalId}: ART = {$articleNumber}, year = {$year}");
                return true;
            } else {
                Log::warning("Не удалось обновить книгу ID {$externalId}");
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Ошибка при обработке записи MFN {$mfn}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Безопасное чтение записи из ИРБИС с обработкой ошибок протокола
     * (Скопировано из IrbisToMySqlSyncController)
     */
    private function safeReadRecord($mfn)
    {
        try {
            // Пытаемся прочитать запись
            $record = $this->irbis->record_read($mfn);
            
            // Проверяем код ошибки ИРБИС
            if ($this->irbis->error_code != 0) {
                // Пропускаем удаленные или недоступные записи
                if (in_array($this->irbis->error_code, [-603, -601, -140])) {
                    return false;
                }
                
                // Для других ошибок логируем и пропускаем
                Log::warning("Ошибка чтения MFN=$mfn: " . $this->irbis->error());
                return false;
            }
            
            return $record;
            
        } catch (\Error $e) {
            // Ловим фатальные ошибки PHP (например, Undefined array key)
            Log::warning("Ошибка протокола при чтении MFN=$mfn: " . $e->getMessage());
            return false;
            
        } catch (\Exception $e) {
            // Ловим обычные исключения
            Log::warning("Исключение при чтении MFN=$mfn: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Безопасное получение значения поля из записи ИРБИС
     * (Скопировано из IrbisToMySqlSyncController)
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

public function index()
{
    // Получаем список пользователей для выпадающего списка
    $users = DB::table('users')->select('id', 'name')->orderBy('name')->get();
    
    // Получаем выбранного пользователя из сессии
    $selectedUserId = session('selected_user_id');
    
    // Получаем только загруженные данные из Excel-файла
    $orders = DB::table('uploaded_orders')->get();
   
    // Заглушка, чтобы переменная существовала всегда
    $orderInfo = null;
    // Создаем ассоциативный массив для быстрого поиска совпадений в основной БД
    $matchingKeys = [];
   
    if ($orders->isNotEmpty()) {
        // Получаем все записи из таблицы books для сравнения
        $booksData = DB::table('books')
            ->select('ART', 'year')
            ->whereNotNull('ART')
            ->whereNotNull('year')
            ->where('ART', '!=', '')
            ->where('year', '!=', '')
            ->get();
       
        // Создаем массив ключей для быстрого поиска
        $matchingKeys = $booksData
            ->filter(fn($book) => !empty($book->ART) && !empty($book->year))
            ->mapWithKeys(fn($book) => ["{$book->ART}_{$book->year}" => true])
            ->toArray();
       
        // Обновляем is_verified для совпадающих записей
        foreach ($orders as $order) {
            if (!empty($order->ART) && !empty($order->year)) {
                $key = $order->ART . '_' . $order->year;
                $isMatching = isset($matchingKeys[$key]);
               
                // Обновляем поле is_verified
                DB::table('uploaded_orders')
                    ->where('id', $order->id)
                    ->update(['is_verified' => $isMatching ? 1 : 0]);
            }
        }
       
        // Перезагружаем данные после обновления
        $orders = DB::table('uploaded_orders')->get();
        // Получаем информацию о заказе
        $orderInfo = DB::table('uploaded_orders')->first();
        if ($orderInfo && isset($orderInfo->created_at)) {
            $orderInfo->created_at = Carbon::parse($orderInfo->created_at);
        }
    }
    
    return view('manual-order', compact('orders', 'matchingKeys', 'orderInfo', 'users', 'selectedUserId'));
}
public function updateUser(Request $request)
{
    $userId = $request->input('user_id');
    
    // Сохраняем выбранного пользователя в сессии
    session(['selected_user_id' => $userId]);
    
    return response()->json(['success' => true]);
}


public function upload(Request $request)
{
    Log::info('=== НАЧАЛО ЗАГРУЗКИ ФАЙЛА ===');
    
    $request->validate([
        'excel_file' => 'required|file|mimes:xlsx,xls|max:10240', // max 10MB
        'user_id' => 'required|exists:users,id'
    ]);

    try {
        $file = $request->file('excel_file');
        $userId = $request->input('user_id');
        
        // Сохраняем выбранного пользователя в сессии
        session(['selected_user_id' => $userId]);
        
        Log::info('Файл получен: ' . $file->getClientOriginalName());
        Log::info('Размер файла: ' . $file->getSize() . ' байт');
        Log::info('Выбранный пользователь: ' . $userId);
        
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        Log::info('Общее количество строк в файле: ' . count($rows));

        // Показываем первые 15 строк для анализа
        for ($i = 0; $i < min(15, count($rows)); $i++) {
            Log::info("Строка $i: " . json_encode($rows[$i], JSON_UNESCAPED_UNICODE));
        }

        // Извлекаем номер заказа и дату из второй строки (индекс 1)
        $orderNum = null;
        $orderDate = null;
        
        if (isset($rows[1]) && isset($rows[1][0])) {
            $secondRowText = trim($rows[1][0]);
            Log::info("Вторая строка для извлечения данных: " . $secondRowText);
            
            // Паттерн для поиска номера заказа и даты
            if (preg_match('/Заказ\s+([A-Za-z0-9]+)\s+от\s+(\d{2}\.\d{2}\.\d{4})/', $secondRowText, $matches)) {
                $orderNum = $matches[1];
                $dateString = $matches[2];
                
                // Преобразуем дату из формата дд.мм.гггг в гггг-мм-дд 00:00:00
                $dateParts = explode('.', $dateString);
                if (count($dateParts) === 3) {
                    $orderDate = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0] . ' 00:00:00';
                }
                
                Log::info("Извлечен номер заказа: " . $orderNum);
                Log::info("Извлечена дата заказа: " . $orderDate);
            }
        }

        // Очищаем таблицу перед загрузкой новых данных
        DB::table('uploaded_orders')->truncate();
        Log::info('Таблица очищена');

        $insertedCount = 0;
        $skippedCount = 0;

        // Пробуем разные варианты начальной строки
        $possibleStartRows = [6, 7, 8, 9, 10]; // Индексы возможных начальных строк
        
        foreach ($possibleStartRows as $startRow) {
            Log::info("=== Проверяем начальную строку: $startRow ===");
            
            if ($startRow >= count($rows)) {
                Log::info("Строка $startRow не существует");
                continue;
            }
            
            $testRow = $rows[$startRow];
            Log::info("Содержимое тестовой строки $startRow: " . json_encode($testRow, JSON_UNESCAPED_UNICODE));
            
            // Проверяем, похожа ли строка на заголовок или данные
            if (isset($testRow[0]) && is_numeric($testRow[0])) {
                Log::info("Найдена возможная начальная строка данных: $startRow");
                break;
            }
        }

        // Начинаем с найденной строки или с 8 по умолчанию
        $dataStartRow = isset($startRow) ? $startRow : 8;
        Log::info("Используем начальную строку: $dataStartRow");

        for ($i = $dataStartRow; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            Log::info("Обрабатываем строку $i: " . json_encode($row, JSON_UNESCAPED_UNICODE));
            
            // Пропускаем совсем пустые строки
            if (empty(array_filter($row))) {
                Log::info("Строка $i пустая, пропускаем");
                $skippedCount++;
                continue;
            }
            
            // Пропускаем строки с "ИТОГО"
            if (isset($row[0]) && strpos(strtoupper($row[0] ?? ''), 'ИТОГО') !== false) {
                Log::info("Строка $i содержит ИТОГО, пропускаем");
                $skippedCount++;
                continue;
            }

            // Преобразуем цену из российского формата в европейский
            $price = $this->convertPrice($row[9] ?? '0');
            Log::info("Цена после конвертации: $price");

            $orderData = [
                'ART' => trim($row[1] ?? ''),
                'seqNum' => trim($row[2] ?? ''),
                'type' => trim($row[3] ?? ''), // Добавляем колонку "Тип" из 4-й колонки Excel
                'author' => trim($row[4] ?? ''),
                'caption' => trim($row[5] ?? ''),
                'year' => trim($row[6] ?? ''),
                'quantity' => (int)($row[8] ?? 0),
                'price' => $price,
                'ordernum' => $orderNum,
                'userid' => $userId,
                'created_at' => $orderDate ?: now(),
                'updated_at' => now(),
            ];  

            Log::info("Подготовленные данные: " . json_encode($orderData, JSON_UNESCAPED_UNICODE));

            // Проверяем, есть ли хоть какие-то значимые данные
            $hasData = !empty($orderData['ART']) || !empty($orderData['caption']) || !empty($orderData['author']);
            
            if ($hasData) {
                try {
                    DB::table('uploaded_orders')->insert($orderData);
                    $insertedCount++;
                    Log::info("Строка $i успешно вставлена в БД");
                } catch (\Exception $dbError) {
                    Log::error("Ошибка вставки строки $i в БД: " . $dbError->getMessage());
                }
            } else {
                Log::info("Строка $i не содержит значимых данных, пропускаем");
                $skippedCount++;
            }
        }

        Log::info("=== РЕЗУЛЬТАТ ОБРАБОТКИ ===");
        Log::info("Вставлено записей: $insertedCount");
        Log::info("Пропущено строк: $skippedCount");

        if ($insertedCount > 0) {
            return redirect()->route('manual-order.index')
                ->with('success', "Файл успешно загружен! Обработано записей: {$insertedCount}, пропущено: {$skippedCount}");
        } else {
            return redirect()->route('manual-order.index')
                ->with('error', "В файле не найдено данных для загрузки. Проверьте логи для детальной информации. Пропущено строк: {$skippedCount}");
        }

    } catch (\Exception $e) {
        Log::error('Критическая ошибка при загрузке файла: ' . $e->getMessage());
        Log::error('Трассировка: ' . $e->getTraceAsString());
        return redirect()->route('manual-order.index')
            ->with('error', 'Ошибка при загрузке файла: ' . $e->getMessage());
    }
}

   public function clear()
    {
        DB::table('uploaded_orders')->truncate();
        return redirect()->route('manual-order.index')->with('success', 'Данные успешно очищены!');
    }

    private function convertPrice($priceString)
    {
        Log::info("Конвертируем цену: '$priceString'");
        
        // Убираем пробелы и заменяем запятую на точку
        $price = str_replace([' ', ','], ['', '.'], trim($priceString));
        
        // Дополнительная очистка от возможных символов
        $price = preg_replace('/[^\d.,]/', '', $price);
        $price = str_replace(',', '.', $price);
        
        Log::info("Цена после обработки: '$price'");
        
        $result = is_numeric($price) ? (float)$price : 0.00;
        Log::info("Финальная цена: $result");
        
        return $result;
    }

}