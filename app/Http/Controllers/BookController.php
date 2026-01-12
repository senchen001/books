<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CartItem;

class BookController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Проверяем нужно ли скрывать записи с пустым seqNum
            $hideEmptySeqNum = $request->has('hide_empty_seqnum') && $request->hide_empty_seqnum == '1';
            
            // Получаем список уникальных предметов с их ID
            // Фильтруем предметы, учитывая скрытие пустых seqNum
            $subjectsQuery = DB::table('books')
                ->select('subj', 'subj_hex')
                ->distinct();
                
            if ($hideEmptySeqNum) {
                $subjectsQuery->whereNotNull('seqNum')
                             ->where('seqNum', '!=', '')
                             ->where('seqNum', '!=', '0');
            }
            
            $subjects = $subjectsQuery->orderBy('subj')->get();

            // Базовый запрос для фильтрации
            $query = DB::table('books');
            
            // Применяем фильтр по скрытию пустых seqNum
            if ($hideEmptySeqNum) {
                $query->whereNotNull('seqNum')
                      ->where('seqNum', '!=', '')
                      ->where('seqNum', '!=', '0');
            }
            
            // Фильтр по предмету
            if ($request->has('subject_id')) {
                $query->where('subj_hex', $request->subject_id);
            }

            // Получаем список классов с количеством книг для каждого
            $classesQuery = DB::table('books')
                ->select('class')
                ->selectRaw('COUNT(*) as book_count');
                
            // Применяем фильтр по скрытию пустых seqNum для классов
            if ($hideEmptySeqNum) {
                $classesQuery->whereNotNull('seqNum')
                            ->where('seqNum', '!=', '')
                            ->where('seqNum', '!=', '0');
            }
            
            // Фильтр по предмету для классов
            if ($request->has('subject_id')) {
                $classesQuery->where('subj_hex', $request->subject_id);
            }
            
            $classesWithCount = $classesQuery->groupBy('class')
                                           ->orderBy('class')
                                           ->get();

            // Получаем книги с пагинацией
            if ($request->has('class')) {
                $query->where('class', $request->class);
            }
            
            $books = $query->paginate(20)->withQueryString();
            
            // Получаем информацию о товарах, которые уже в корзине пользователя
            $cartItems = [];
            if (auth()->check()) {
                $items = CartItem::where('user_id', auth()->id())
                    ->get(['product_id', 'quantity', 'price']);
                
                foreach ($items as $item) {
                    $cartItems[$item->product_id] = [
                        'quantity' => $item->quantity,
                        'price' => $item->price
                    ];
                }
            }

            return view('books.index', compact('books', 'subjects', 'classesWithCount', 'cartItems'));

        } catch (\Exception $e) {
            \Log::error('Error in BookController: ' . $e->getMessage());
            throw $e;
        }
    }

    public function show(Book $book)
    {
        return view('books.show', compact('book'));
    }

    public function search(Request $request)
    {
        $searchTerm = $request->input('query');
        $searchTerm = str_replace('-', '', $searchTerm);
        $normalizedSearchTerm = str_replace(['-', ' '], '', $searchTerm);

        // В поиске НЕ применяем фильтр по seqNum - показываем все результаты
$booleanSearch = collect(explode(' ', $searchTerm))
    ->filter() // на случай двойных пробелов
    ->map(fn($word) => '+' . $word)
    ->implode(' ');

$normalized = preg_replace('/[^a-zа-я0-9]/ui', '', mb_strtolower($searchTerm));

$results = DB::table('books')
    ->where('search_index', 'LIKE', '%' . $normalized . '%')
    // другие поля:
    ->orWhereRaw("REPLACE(isbn, '-', '') LIKE ?", ['%' . $normalized . '%'])
    ->orWhereRaw("REPLACE(ART, '-', '') LIKE ?", ['%' . $normalized . '%'])
    ->orWhereRaw("REPLACE(url_id, '-', '') LIKE ?", ['%' . $normalized . '%'])
    ->orWhere('seqNum', 'LIKE', '%' . $normalized . '%')
    ->get();


        // Получаем информацию о товарах, которые уже в корзине пользователя
        $cartItems = [];
        if (auth()->check()) {
            $items = CartItem::where('user_id', auth()->id())
                ->get(['product_id', 'quantity', 'price']);
            
            foreach ($items as $item) {
                $cartItems[$item->product_id] = [
                    'quantity' => $item->quantity,
                    'price' => $item->price
                ];
            }
        }

        return view('books.searchresult', compact('results', 'cartItems'));
    }
}