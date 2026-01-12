<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class IrbisErrorHandler
{
    /**
     * Коды ошибок ИРБИС, которые следует игнорировать (удаленные/недоступные записи)
     */
    const IGNORABLE_ERRORS = [-603, -601, -140];
    
    /**
     * Критические ошибки, которые требуют прерывания процесса
     */
    const CRITICAL_ERRORS = [-1, -2, -3];
    
    /**
     * Проверяет, является ли ошибка критической
     * 
     * @param int $errorCode
     * @return bool
     */
    public static function isCriticalError($errorCode)
    {
        return in_array($errorCode, self::CRITICAL_ERRORS);
    }
    
    /**
     * Проверяет, можно ли игнорировать ошибку
     * 
     * @param int $errorCode
     * @return bool
     */
    public static function isIgnorableError($errorCode)
    {
        return in_array($errorCode, self::IGNORABLE_ERRORS);
    }
    
    /**
     * Безопасное чтение записи из ИРБИС с обработкой ошибок
     * 
     * @param object $irbis - экземпляр класса ИРБИС
     * @param int $mfn - номер записи
     * @return mixed|false - запись или false в случае ошибки
     */
    public static function safeReadRecord($irbis, $mfn)
    {
        try {
            // Пытаемся прочитать запись
            $record = $irbis->record_read($mfn);
            
            // Проверяем код ошибки ИРБИС
            if ($irbis->error_code != 0) {
                return self::handleIrbisError($irbis, $mfn, 'record_read');
            }
            
            return $record;
            
        } catch (\Error $e) {
            // Ловим фатальные ошибки PHP
            Log::warning("Фатальная ошибка протокола при чтении MFN=$mfn: " . $e->getMessage());
            return false;
            
        } catch (\Exception $e) {
            // Ловим обычные исключения
            Log::warning("Исключение при чтении MFN=$mfn: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Безопасное получение списка записей по термину
     * 
     * @param object $irbis
     * @param string $term
     * @param int $maxRecords
     * @param int $format
     * @return array|false
     */
    public static function safeGetTermRecords($irbis, $term, $maxRecords = 0, $format = 0)
    {
        try {
            $records = $irbis->term_records($term, $maxRecords, $format);
            
            if ($irbis->error_code != 0) {
                return self::handleIrbisError($irbis, $term, 'term_records');
            }
            
            return $records;
            
        } catch (\Error $e) {
            Log::warning("Фатальная ошибка при получении записей термина '$term': " . $e->getMessage());
            return false;
            
        } catch (\Exception $e) {
            Log::warning("Исключение при получении записей термина '$term': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Безопасное чтение терминов словаря
     * 
     * @param object $irbis
     * @param string $startTerm
     * @param int $count
     * @return array|false
     */
    public static function safeReadTerms($irbis, $startTerm, $count = 100)
    {
        try {
            $terms = $irbis->terms_read($startTerm, $count);
            
            if ($irbis->error_code != 0) {
                return self::handleIrbisError($irbis, $startTerm, 'terms_read');
            }
            
            return $terms;
            
        } catch (\Error $e) {
            Log::warning("Фатальная ошибка при чтении терминов с '$startTerm': " . $e->getMessage());
            return false;
            
        } catch (\Exception $e) {
            Log::warning("Исключение при чтении терминов с '$startTerm': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Обработка ошибок ИРБИС
     * 
     * @param object $irbis
     * @param mixed $context - контекст (MFN, термин и т.д.)
     * @param string $operation - операция, которая вызвала ошибку
     * @return false|null
     * @throws \Exception для критических ошибок
     */
    private static function handleIrbisError($irbis, $context, $operation)
    {
        $errorCode = $irbis->error_code;
        $errorMessage = $irbis->error();
        
        // Критические ошибки - прерываем выполнение
        if (self::isCriticalError($errorCode)) {
            Log::error("Критическая ошибка ИРБИС при $operation ($context): [$errorCode] $errorMessage");
            throw new \Exception("Критическая ошибка ИРБИС: [$errorCode] $errorMessage");
        }
        
        // Игнорируемые ошибки - логируем и продолжаем
        if (self::isIgnorableError($errorCode)) {
            Log::info("Игнорируемая ошибка ИРБИС при $operation ($context): [$errorCode] $errorMessage");
            return false;
        }
        
        // Остальные ошибки - предупреждение и продолжаем
        Log::warning("Ошибка ИРБИС при $operation ($context): [$errorCode] $errorMessage");
        return false;
    }
    
    /**
     * Безопасное получение значения поля из записи ИРБИС
     * 
     * @param mixed $record
     * @param int $fieldTag
     * @param int $occurrence
     * @return string|null
     */
    public static function safeGetFieldValue($record, $fieldTag, $occurrence = 1)
    {
        try {
            // Проверяем, что запись существует и корректна
            if (!$record || !is_object($record)) {
                return null;
            }

            // Разные варианты получения поля в зависимости от версии библиотеки
            $field = null;
            
            if (method_exists($record, 'getField')) {
                $field = $record->getField($fieldTag, $occurrence);
            } elseif (method_exists($record, 'field')) {
                $field = $record->field($fieldTag, $occurrence);
            } elseif (property_exists($record, 'fields') && is_array($record->fields) && isset($record->fields[$fieldTag])) {
                $fields = $record->fields[$fieldTag];
                if (is_array($fields) && isset($fields[$occurrence])) {
                    $field = $fields[$occurrence];
                }
            }

            // Если поле не найдено
            if (!$field) {
                return null;
            }

            return self::extractFieldValue($field);

        } catch (\Error $e) {
            Log::warning("Фатальная ошибка получения поля $fieldTag: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            Log::warning("Ошибка получения поля $fieldTag: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Извлечение строкового значения из поля ИРБИС
     * 
     * @param mixed $field
     * @return string|null
     */
    private static function extractFieldValue($field)
    {
        // Если поле - массив с подполями
        if (is_array($field)) {
            if (isset($field['*'])) {
                return trim((string)$field['*']);
            }
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
    }
    
    /**
     * Логирование статистики ошибок
     * 
     * @param array $errorStats - массив со статистикой ошибок
     */
    public static function logErrorStatistics($errorStats)
    {
        if (!empty($errorStats)) {
            Log::info("=== СТАТИСТИКА ОШИБОК ИРБИС ===");
            foreach ($errorStats as $errorCode => $count) {
                $errorType = self::getErrorType($errorCode);
                Log::info("Ошибка $errorCode ($errorType): $count раз");
            }
        }
    }
    
    /**
     * Получение типа ошибки по коду
     * 
     * @param int $errorCode
     * @return string
     */
    private static function getErrorType($errorCode)
    {
        if (self::isCriticalError($errorCode)) {
            return 'критическая';
        } elseif (self::isIgnorableError($errorCode)) {
            return 'игнорируемая';
        } else {
            return 'предупреждение';
        }
    }
}