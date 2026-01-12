<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateSearchIndex extends Command
{
    protected $signature = 'books:generate-search-index';
    protected $description = 'Генерирует поле search_index из caption и author для всех книг';

    public function handle(): int
    {
        $this->info('Начинаем генерацию search_index...');

        $count = DB::table('books')->count();
        $this->info("Обрабатываем $count записей...");

        DB::table('books')->orderBy('id')->chunk(500, function ($books) {
            foreach ($books as $book) {
                $normalized = $this->normalizeText("{$book->caption} {$book->author}");

                DB::table('books')
                    ->where('id', $book->id)
                    ->update(['search_index' => $normalized]);
            }
        });

        $this->info('Готово!');
        return Command::SUCCESS;
    }

    protected function normalizeText($text): string
    {
        // Приводит к нижнему регистру и удаляет все, кроме букв и цифр
        return preg_replace('/[^a-zа-я0-9]/ui', '', mb_strtolower($text));
    }
}
