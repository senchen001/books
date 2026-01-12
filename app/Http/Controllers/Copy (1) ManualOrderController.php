<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ManualOrderController extends Controller
{
    
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
            $irbis = new \irbis64($server, $port, $login, $password, $database);
            
            // Авторизуемся
            if (!$irbis->login()) {
                throw new \Exception('Ошибка авторизации в ИРБИС: ' . $irbis->error());
            }

            Log::info('Успешная авторизация в ИРБИС');

            // Получаем текущую и предыдущую даты в формате ГГГГММДД
            $currentDate = date('Ymd');
            $previousDate = date('Ymd', strtotime('-1 day'));
            
            Log::info("Ищем термины для дат: {$currentDate} и {$previousDate}");

            // Более эффективный поиск терминов
            $validTerms = $this->findValidTerms($irbis, [$currentDate, $previousDate]);

            Log::info("Всего найдено подходящих терминов: " . count($validTerms));

            if (empty($validTerms)) {
                $irbis->logout();
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
                    $termUpdates = $this->processTermRecords($irbis, $termInfo, $processedRecords);
                    $updatedCount += $termUpdates;
                } catch (\Exception $e) {
                    $errors[] = "Ошибка обработки термина {$termInfo['term']}: " . $e->getMessage();
                    Log::error("Ошибка обработки термина {$termInfo['term']}: " . $e->getMessage());
                }
            }

            // Завершаем сессию ИРБИС
            $irbis->logout();
            
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
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении базы: ' . $e->getMessage(),
                'error_details' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Поиск валидных терминов для указанных дат
     */
    private function findValidTerms($irbis, $dates)
    {
        $validTerms = [];
        
        foreach ($dates as $date) {
            $searchTerm = "TH={$date}";
            Log::info("Поиск терминов: {$searchTerm}");
            
            try {
                // Читаем словарь, начиная с термина
                $terms = $irbis->terms_read($searchTerm, 1000);
                
                if ($terms === false) {
                    Log::warning("Ошибка чтения словаря для {$searchTerm}: " . $irbis->error());
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
    private function processTermRecords($irbis, $termInfo, &$processedRecords)
    {
        Log::info("Обрабатываем термин: {$termInfo['term']}");
        
        try {
            // Сначала получаем количество записей
            $recordCount = $irbis->term_records($termInfo['term'], 0, 0);
            if ($recordCount === false || empty($recordCount) || $recordCount[0] == 0) {
                Log::warning("Нет записей для термина {$termInfo['term']}");
                return 0;
            }
            
            $totalRecords = (int)$recordCount[0];
            Log::info("Всего записей для термина: {$totalRecords}");
            
            // Получаем все MFN записей
            $mfnList = $irbis->term_records($termInfo['term'], $totalRecords, 1);
            
            if ($mfnList === false || empty($mfnList)) {
                Log::warning("Не удалось получить MFN для термина {$termInfo['term']}: " . $irbis->error());
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
                    if ($this->processRecord($irbis, $mfn)) {
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
    private function processRecord($irbis, $mfn)
    {
        Log::info("Читаем запись MFN: {$mfn}");
        
        try {
            // Читаем запись из ИРБИС
            $record = $irbis->record_read($mfn);
            
            if ($record === false) {
                Log::warning("Ошибка чтения записи MFN {$mfn}: " . $irbis->error());
                return false;
            }

            // Получаем поле 803 (внешний ID книги)
            $field803 = $record->field(803);
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

            // Получаем поле 802 (артикул)
            $field802 = $record->field(802);
            if (empty($field802)) {
                Log::info("Поле 802 отсутствует в записи MFN {$mfn}");
                return false;
            }

            $articleNumber = trim($field802);
            Log::info("Найден артикул: {$articleNumber}");

            // Проверяем, нужно ли обновление
            if ($book->ART === $articleNumber) {
                Log::info("Артикул уже актуален для книги ID {$externalId}");
                return false;
            }

            // Обновляем поле ART в таблице books
            $updated = DB::table('books')
                ->where('id', $externalId)
                ->update([
                    'ART' => $articleNumber,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info("Обновлена книга ID {$externalId}: ART = {$articleNumber}");
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

    public function index()
    {
        $orders = DB::table('uploaded_orders')->get();
        return view('manual-order', compact('orders'));
    }

    public function upload(Request $request)
    {
        Log::info('=== НАЧАЛО ЗАГРУЗКИ ФАЙЛА ===');
        
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls|max:10240', // max 10MB
        ]);

        try {
            $file = $request->file('excel_file');
            Log::info('Файл получен: ' . $file->getClientOriginalName());
            Log::info('Размер файла: ' . $file->getSize() . ' байт');
            
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            Log::info('Общее количество строк в файле: ' . count($rows));

            // Показываем первые 15 строк для анализа
            for ($i = 0; $i < min(15, count($rows)); $i++) {
                Log::info("Строка $i: " . json_encode($rows[$i], JSON_UNESCAPED_UNICODE));
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
                    'author' => trim($row[4] ?? ''),
                    'caption' => trim($row[5] ?? ''),
                    'year' => trim($row[6] ?? ''),
                    'quantity' => (int)($row[8] ?? 0),
                    'price' => $price,
                    'created_at' => now(),
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