<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use Illuminate\Http\Request;

class CartController extends Controller
{   
    public function updateQuantity(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:books,id',
            'quantity' => 'required|integer|min:1',
        ]);

        // Найти товар в корзине и обновить его количество
        $cartItem = CartItem::where('user_id', auth()->id())
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($cartItem) {
            $cartItem->quantity = $validated['quantity'];
            $cartItem->save();
        }

        // Возвращаем JSON-ответ для AJAX запроса
        return response()->json(['success' => true, 'message' => 'Количество товара обновлено']);
    }
    
    public function updatePrice(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:books,id',
            'price' => 'nullable|numeric|min:0',
        ]);

        // Найти товар в корзине и обновить его цену
        $cartItem = CartItem::where('user_id', auth()->id())
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($cartItem) {
            $cartItem->price = $validated['price'];
            $cartItem->save();
        }

        // Возвращаем JSON-ответ для AJAX запроса
        return response()->json(['success' => true, 'message' => 'Цена товара обновлена']);
    }
    
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $cartItems = CartItem::with('book')
            ->where('user_id', auth()->id())
            ->get();

        return view('cart.index', compact('cartItems'));
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'book_id' => 'required|exists:books,id',
            'quantity' => 'required|integer|min:1',
            'price' => 'nullable|numeric|min:0',
        ]);

        // Проверяем, есть ли уже этот товар в корзине
        $cartItem = CartItem::where('user_id', auth()->id())
            ->where('product_id', $validated['book_id'])
            ->first();

        if ($cartItem) {
            // Если товар уже есть в корзине, увеличиваем количество
            $cartItem->quantity += $validated['quantity'];
            // Обновляем цену только если она указана
            if (isset($validated['price'])) {
                $cartItem->price = $validated['price'];
            }
            $cartItem->save();
        } else {
            // Если товара нет в корзине, создаем новую запись
            CartItem::create([
                'user_id' => auth()->id(),
                'product_id' => $validated['book_id'],
                'quantity' => $validated['quantity'],
                'price' => $validated['price'] ?? null,
            ]);
        }

        return redirect()->back()->with('success', 'Книга добавлена в корзину');
    }

    public function remove(Request $request)
    {
        CartItem::where('user_id', auth()->id())
            ->where('product_id', $request->book_id)
            ->delete();

        // Возвращаем JSON-ответ для AJAX запроса
        return response()->json(['success' => true, 'message' => 'Книга удалена из корзины']);
    }
	
    public function order(Request $request){
        //поместим заказ из корзины в заказы
        // Получите элементы корзины для текущего пользователя
		$cartItems = CartItem::with('book')
        ->where('user_id', auth()->id())
        ->get();

        $orderNum = $cartItems[0]->id;
        foreach ($cartItems as $cartItem) {
            // Create a new order
            Order::create([
                'userid' => auth()->id(), // Assuming you want to associate the order with the current user
                'productid' => $cartItem->book->id, // Assuming 'book' is the relationship and you want to use the book's ID
                'ordernum' => $orderNum,
                'quantity' => $cartItem->quantity, // Assuming you have a quantity field in the cart item
                'price' => $cartItem->price, // Добавляем сохранение цены в заказ
                'status' => 'в обработке', // Set the initial status of the order
            ]);
        }

        CartItem::where('user_id', auth()->id())->delete();//или можно менять их статус в корзине
        return redirect()->route('order.index')->with('success', 'Orders created successfully.');
    }

 public function download(Request $request)
    {
        // Проверяем, был ли указан параметр orderNum
        $orderNum = $request->input('orderNum');
        
        if ($orderNum) {
            // Если указан номер заказа, получаем элементы этого заказа
            $orderItems = Order::with('book')
                ->where('userid', auth()->id())
                ->where('ordernum', $orderNum)
                ->get();
                
            if ($orderItems->isEmpty()) {
                return redirect()->back()->with('error', 'Заказ не найден или не принадлежит вам.');
            }
            
            // Создаем текстовый файл со списком заказанных товаров
            $content = "Заказ №{$orderNum} от {$orderItems->first()->created_at}:\n\n";
            
            foreach ($orderItems as $item) {
                $content .= "Название: " . $item->book->caption . "\n";
                $content .= "Автор: " . $item->book->author . "\n";
                $content .= "Количество: " . $item->quantity . "\n";
                if ($item->price) {
                    $content .= "Цена: " . $item->price . "\n";
                }
                $content .= "Статус: " . $item->status . "\n\n";
            }
            
            // Установите заголовки для скачивания файла
            $headers = [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="order_' . $orderNum . '.txt"',
            ];
            
            // Возвращаем файл как ответ
            return response()->make($content, 200, $headers);
        } else {
            // Если номер заказа не указан, получаем элементы корзины
            $cartItems = CartItem::with('book')
                ->where('user_id', auth()->id())
                ->get();
                
            // Проверяем, есть ли элементы в корзине
            if ($cartItems->isEmpty()) {
                return redirect()->back()->with('error', 'Корзина пуста, нечего скачивать.');
            }
            
            // Создаем текстовый файл со списком покупок
            $content = "Список покупок:\n\n";
            foreach ($cartItems as $item) {
                $content .= "Название: " . $item->book->caption . "\n";
                $content .= "Автор: " . $item->book->author . "\n";
                $content .= "Количество: " . $item->quantity . "\n";
                if ($item->price) {
                    $content .= "Цена: " . $item->price . "\n";
                }
                $content .= "\n";
            }
            
            // Установите заголовки для скачивания файла
            $headers = [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="shopping_list.txt"',
            ];
            
            // Возвращаем файл как ответ
            return response()->make($content, 200, $headers);
        }
    }
    public function deleteByOrderNum(Request $request)
{
    $orderNum = $request->input('orderNum');

    // Удаляем все элементы с этим номером заказа
    Order::where('ordernum', $orderNum)->delete();

    return redirect()->back()->with('success', 'Заказ №'.$orderNum.' удалён.');
}
}