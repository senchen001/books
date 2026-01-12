<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SyncViewController extends Controller
{
    /**
     * Отобразить интерфейс синхронизации
     */
    public function index()
    {
        return view('sync');
    }
}

// Добавьте этот маршрут в routes/web.php:
// Route::get('/sync', [SyncViewController::class, 'index'])->name('sync.interface');