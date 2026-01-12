<?php
namespace App\Http\Controllers;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommandController extends Controller
{      
    public function toggleStatus($userId, $orderNum)
    {
        // Получаем текущий статус из базы
        $firstOrder = DB::table('orders')
            ->where('userid', $userId)
            ->where('ordernum', $orderNum)
            ->first();
            
        if (!$firstOrder) {
            return redirect()->back()->with('error', 'Заказ не найден');
        }
        
        $currentStatus = $firstOrder->status;
        $newStatus = '';
        $newIsVerified = 0;
        
        // Определяем новый статус в зависимости от текущего
        if ($currentStatus === 'в обработке') {
            $newStatus = 'Принят в работу';
            $newIsVerified = 0;
        } elseif ($currentStatus === 'Принят в работу') {
            $newStatus = 'в обработке';
            $newIsVerified = 0;
        } else {
            return redirect()->back()->with('error', 'Невозможно изменить статус для заказа со статусом: ' . $currentStatus);
        }
        
        // Обновляем статус, is_verified и очищаем verified_at для всех позиций данного заказа
        DB::table('orders')
            ->where('userid', $userId)
            ->where('ordernum', $orderNum)
            ->update([
                'status' => $newStatus, 
                'is_verified' => $newIsVerified,
                'verified_at' => null,
                'updated_at' => now()
            ]);
            
        return redirect()->back()->with('success', "Статус заказа #{$orderNum} изменён на '{$newStatus}'");
    }

    public function toggleVerification($userId, $orderNum)
    {
        // Получаем текущий статус из базы
        $firstOrder = DB::table('orders')
            ->where('userid', $userId)
            ->where('ordernum', $orderNum)
            ->first();
            
        if (!$firstOrder) {
            return redirect()->back()->with('error', 'Заказ не найден');
        }
        
        $currentStatus = $firstOrder->status;
        $newStatus = '';
        $newIsVerified = 0;
        $verifiedAt = null;
        
        // Определяем новый статус в зависимости от текущего
        if ($currentStatus === 'Принят в работу') {
            $newStatus = 'Готов к выдаче';
            $newIsVerified = 1;
            $verifiedAt = now();
        } elseif ($currentStatus === 'Готов к выдаче') {
            $newStatus = 'Принят в работу';
            $newIsVerified = 0;
            $verifiedAt = null;
        } else {
            return redirect()->back()->with('error', 'Невозможно изменить верификацию для заказа со статусом: ' . $currentStatus);
        }
        
        // Обновляем статус, is_verified и verified_at для всех позиций данного заказа
        DB::table('orders')
            ->where('userid', $userId)
            ->where('ordernum', $orderNum)
            ->update([
                'status' => $newStatus, 
                'is_verified' => $newIsVerified,
                'verified_at' => $verifiedAt,
                'updated_at' => now()
            ]);
            
        $actionText = $newStatus === 'Готов к выдаче' ? 'верифицирован' : 'снята верификация';
        return redirect()->back()->with('success', "Заказ #{$orderNum} {$actionText}");
    }

    public function cancelOrder($userId, $orderNum)
    {
        // Получаем текущий статус из базы
        $firstOrder = DB::table('orders')
            ->where('userid', $userId)
            ->where('ordernum', $orderNum)
            ->first();
            
        if (!$firstOrder) {
            return redirect()->back()->with('error', 'Заказ не найден');
        }
        
        $currentStatus = $firstOrder->status;
        
        // Проверяем, можно ли отменить заказ
        if ($currentStatus === 'Отменён') {
            return redirect()->back()->with('error', 'Заказ уже отменён');
        }
        
        // Отменяем заказ
        DB::table('orders')
            ->where('userid', $userId)
            ->where('ordernum', $orderNum)
            ->update([
                'status' => 'Отменён',
                'is_verified' => 0,
                'verified_at' => null,
                'updated_at' => now()
            ]);
            
        return redirect()->back()->with('success', "Заказ #{$orderNum} отменён");
    }

    public function restoreOrder($userId, $orderNum)
    {
        // Получаем текущий статус из базы
        $firstOrder = DB::table('orders')
            ->where('userid', $userId)
            ->where('ordernum', $orderNum)
            ->first();
            
        if (!$firstOrder) {
            return redirect()->back()->with('error', 'Заказ не найден');
        }
        
        $currentStatus = $firstOrder->status;
        
        // Проверяем, можно ли восстановить заказ
        if ($currentStatus !== 'Отменён') {
            return redirect()->back()->with('error', 'Можно восстановить только отменённые заказы');
        }
        
        // Восстанавливаем заказ в статус "в обработке"
        DB::table('orders')
            ->where('userid', $userId)
            ->where('ordernum', $orderNum)
            ->update([
                'status' => 'в обработке',
                'is_verified' => 0,
                'verified_at' => null,
                'updated_at' => now()
            ]);
            
        return redirect()->back()->with('success', "Заказ #{$orderNum} возвращён в работу");
    }

    public function index(Request $request)
    {
        // Get all orders with their associated book and user details
        $cartItems = Order::with(['book', 'user'])->get();
       
        // Group by ordernum
        $groupedCartItems = $cartItems->groupBy('ordernum')->map(function ($orders, $orderNum) {
            $firstItem = $orders->first();
            return [
                'ordernum' => $orderNum,
                'user' => $firstItem->user, // Пользователь для этого заказа
                'userid' => $firstItem->userid, // ID пользователя
                'items' => $orders // Все элементы заказа
            ];
        });

        // Получаем всех пользователей для выпадающего меню
        $users = User::orderBy('name')->get();
       
        return view('command/command', compact('groupedCartItems', 'users'));
    }
}