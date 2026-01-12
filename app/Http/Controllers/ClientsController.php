<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ClientsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'checkAdmin']);
    }

    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();
        
        return view('clients.index', compact('users'));
    }

    public function toggleVerification(Request $request, User $user)
    {
        try {
            if ($user->user_verified_at) {
                // Снимаем верификацию
                $user->user_verified_at = null;
                $status = 'unverified';
                $buttonText = 'Верифицировать';
                $statusText = 'Пользователь не верифицирован';
                $statusClass = 'text-gray-500';
            } else {
                // Верифицируем
                $user->user_verified_at = now();
                $status = 'verified';
                $buttonText = 'Снять верификацию';
                $statusText = 'Пользователь верифицирован (' . $user->user_verified_at->format('d.m.Y H:i') . ')';
                $statusClass = 'text-green-600 font-medium';
            }
            
            $user->save();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'status' => $status,
                    'user_verified_at' => $user->user_verified_at ? $user->user_verified_at->format('d.m.Y H:i') : null,
                    'button_text' => $buttonText,
                    'status_text' => $statusText,
                    'status_class' => $statusClass
                ]);
            }

            return back()->with('success', 'Статус верификации пользователя изменен');
            
        } catch (\Exception $e) {
            Log::error('Ошибка при изменении верификации пользователя: ' . $e->getMessage());
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Произошла ошибка при обновлении статуса'
                ], 500);
            }
            
            return back()->with('error', 'Произошла ошибка при обновлении статуса');
        }
    }
}