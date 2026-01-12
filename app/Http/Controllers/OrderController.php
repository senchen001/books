<?php

namespace App\Http\Controllers;
use App\Models\Order;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
public function cancel($userid, $ordernum)
{
    // Получаем все позиции заказа для пользователя и ordernum
    $orders = Order::where('userid', $userid)
                   ->where('ordernum', $ordernum)
                   ->get();

    if ($orders->isEmpty()) {
        return redirect()->back()->with('error', 'Заказ не найден.');
    }

    // Проверяем, отменены ли все позиции
    $allCancelled = $orders->every(function ($order) {
        return $order->status === 'Отменён';
    });

    if ($allCancelled) {
        return redirect()->back()->with('info', 'Заказ уже отменён.');
    }

    // Обновляем статус всех позиций заказа
    Order::where('userid', $userid)
         ->where('ordernum', $ordernum)
         ->update(['status' => 'Отменён']);

    return redirect()->back()->with('success', 'Заказ успешно отменён.');
}

    public function downloadCsv(Request $request)
    {
        $orderNumber = $request->query('ordernum');

        $filename = "order_{$orderNumber}.csv";

        $orders = DB::table('orders')
            ->join('books', 'orders.productid', '=', 'books.id')
            ->join('users', 'orders.userid', '=', 'users.id')
            ->select(
                'books.ART',
                'books.seqNum',
                DB::raw('"" as empty1'),
                'books.author',
                'books.caption',
                'books.year',
                'books.subj',
                'orders.quantity',
                'orders.price',
                DB::raw('"" as empty2'),
                'orders.created_at',
                'books.url_id',
                'orders.ordernum',
                'orders.userid',
                'users.inn',
                'books.pubCompany',
                'books.isbn'
            )
            ->where('orders.ordernum', $orderNumber)
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
        ];

        $columns = [
            'ART', 'seqNum', '', 'author', 'caption', 'year', 'subj',
            'quantity', 'price', '', 'created_at', 'url_id', 'ordernum',
            'userid', 'inn', 'pubCompany', 'isbn'
        ];

        $callback = function () use ($orders, $columns) {
            $file = fopen('php://output', 'w');
            #fputcsv($file, $columns);
            

foreach ($orders as $row) {
    fputcsv($file, [
        mb_convert_encoding($row->ART, 'Windows-1251', 'UTF-8'),
        mb_convert_encoding($row->seqNum, 'Windows-1251', 'UTF-8'),
        '',
        mb_convert_encoding($row->author, 'Windows-1251', 'UTF-8'),
        mb_convert_encoding($row->caption, 'Windows-1251', 'UTF-8'),
        mb_convert_encoding($row->year, 'Windows-1251', 'UTF-8'),
        mb_convert_encoding($row->subj, 'Windows-1251', 'UTF-8'),
        $row->quantity,
        $row->price,
        '',
        $row->created_at,
        $row->url_id,
        $row->ordernum,
        $row->userid,
        mb_convert_encoding($row->inn, 'Windows-1251', 'UTF-8'),
        mb_convert_encoding($row->pubCompany, 'Windows-1251', 'UTF-8'),
        mb_convert_encoding($row->isbn, 'Windows-1251', 'UTF-8'),
    ]);
}

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }  
    public function toggleVerification($userId, $orderNum)
{
    $orders = Order::where('userid', $userId)
                    ->where('ordernum', $orderNum)
                    ->get();

    if ($orders->isEmpty()) {
        return redirect()->back()->with('error', 'Заказ не найден');
    }

    // Проверим текущий статус по первому элементу (предполагаем, что у всех одинаковый статус)
    $currentStatus = $orders->first()->is_verified;

    // Переключаем статус
    $newStatus = $currentStatus ? 0 : 1;

    foreach ($orders as $order) {
        $order->is_verified = $newStatus;
        $order->verified_at = $newStatus ? now() : null;
        $order->save();
    }

    return redirect()->back()->with('success', 'Статус верификации заказа обновлен');
}
    public function index(){

        $orderItems = Order::with('book')
            ->where('userid', auth()->id())
            ->get();

        return view('orders.index', compact('orderItems'));
        
    }

    public function add(Request $request){
        // Validate the request data
        $validatedData = $request->validate([
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        // Logic to add the order
        // For example, you might save the order to the database
        // Order::create($validatedData);
       // return redirect()->route('order.index')->with('success', 'Order added successfully.');
       return redirect()->route('order.ordered');
    }

    public function download(Request $request)
    {
        // Logic to handle downloading an order or related file
        // For example, you might return a file response
        // return response()->download($filePath);

        return response()->json(['message' => 'Download initiated.']);
    }

public function downloadOrder($userId, $orderNum)
    {
        // Fetch the grouped cart items
        $groupedCartItems = Order::with(['book', 'user'])->get()->groupBy('userid')->map(function ($orders) {
            $user = $orders->first()->user;
            return [
                'user' => $user,
                'orders' => $orders->groupBy('ordernum')
            ];
        });
   
        // Get the specific order
        $order = $groupedCartItems[$userId]['orders'][$orderNum];
   
        // Prepare the content for the text file with book IDs
        $url_ids = Array();
        $finalContent = "";
            foreach ($order as $cartItem) {
                $url_id = $cartItem->book->url_id . ".txt";
                $booksNum = $cartItem->quantity;
                $orderDate = $cartItem->created_at->format('Ymd'); // дата в формате YYYYMMDD
                $orderNum = $cartItem->ordernum;
                $price = $cartItem->price;
                $to_add = "#910: ^AU^1" . $booksNum
                        . "^DХР^D" . $orderDate
                        . "^E" . $price
                        . "^Y" . $orderNum . "\r\n";
                $fileContent = Storage::get(trim($url_id)) . "\r\n";
                $finalContent .= $to_add . $fileContent;
            }
       
        // Remove empty lines from final content
        $finalContent = preg_replace('/^\s*\r?\n/m', '', $finalContent);
       
        // Define the file name
        $fileName = 'order_' . $orderNum . '.txt';
   
        // Stream the content as a downloadable file
        return response()->stream(function () use ($finalContent) {
            echo $finalContent; // Output the content
        }, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

     // Remove an order
     public function remove(Request $request)
     {
         // Validate the request data
         $validatedData = $request->validate([
             'order_id' => 'required|integer',
         ]);
 
         // Logic to remove the order
         // For example, you might delete the order from the database
         // Order::destroy($validatedData['order_id']);
 
         return response()->json(['message' => 'Order removed successfully.']);
     }

     public function ordered()
     {
        return redirect()->route('order.ordered');
     }
}
