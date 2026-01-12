<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Загрузка заказов</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Custom scrollbar styles */
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Стиль для выделения совпадающих записей */
        .matching-record {
            background-color: #dcfce7 !important; /* светло-зелёный фон */
        }
        .matching-record:hover {
            background-color: #bbf7d0 !important; /* чуть темнее при наведении */
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8" x-data="manualOrder()">
        <div class="mb-6">
    <label for="user_select" class="block text-sm font-medium text-gray-700 mb-2">
        Выберите пользователя
    </label>
    <select id="user_select" 
            x-model="selectedUserId"
            class="block w-full max-w-md px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            @change="updateSelectedUser($event.target.value)">
        <option value="">-- Выберите пользователя --</option>
        @foreach($users as $user)
            <option value="{{ $user->id }}" {{ $selectedUserId == $user->id ? 'selected' : '' }}>
                {{ $user->name }}
            </option>
        @endforeach
    </select>
    
    <!-- Для отладки - можно удалить позже -->
    <div class="mt-2 text-sm text-gray-500" x-show="selectedUserId">
        Выбранный пользователь ID: <span x-text="selectedUserId"></span>
    </div>
</div>
        <!-- Header -->
        <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-6">Загрузка заказов учебников</h1>
            
            <!-- Action Buttons -->
            <div class="flex flex-wrap gap-4 mb-4">
                <!-- Upload Button -->
                <div class="relative">
                    <input type="file" 
                           id="excel_file" 
                           accept=".xlsx,.xls" 
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                           @change="handleFileSelect($event)">
                    <button class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        Загрузить файл Excel
                    </button>
                </div>
                
                <!-- Clear Button -->
                <button @click="clearData()" 
                        class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 flex items-center gap-2"
                        :disabled="loading">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Очистить данные
                </button>

<!-- Update Database Button -->
                <button @click="updateDatabase()" 
                        class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 flex items-center gap-2"
                        :disabled="loading">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="!loading">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24" x-show="loading">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-show="!loading">Обновить базу</span>
                    <span x-show="loading">Обновляется...</span>
                </button>

                <!-- Download Orders Button -->
                <button @click="downloadOrders()" 
                        class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 flex items-center gap-2"
                        :disabled="loading">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="!loading">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24" x-show="loading">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-show="!loading">Сформировать заказ</span>
                    <span x-show="loading">Скачивается...</span>
                </button>
            
            <!-- File info -->
            <div x-show="selectedFile" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <p class="text-blue-800">
                    Выбран файл: <span x-text="selectedFile?.name" class="font-medium"></span>
                    (<span x-text="formatFileSize(selectedFile?.size)"></span>)
                </p>
                <button @click="uploadFile()" 
                        class="mt-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-medium"
                        :disabled="loading">
                    <span x-show="!loading">Загрузить</span>
                    <span x-show="loading" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Загружается...
                    </span>
                </button>
            </div>
            
            <!-- Messages -->
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ session('error') }}
                    </div>
                </div>
            @endif
        </div>

        <!-- Data Table -->
@if($orders->count() > 0)
<div class="bg-white shadow-sm rounded-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">
                    Загруженные данные ({{ $orders->count() }} записей)
                </h2>
                <!-- Добавляем легенду для пользователя -->
                <p class="text-sm text-gray-600 mt-1">
                    <span class="inline-block w-3 h-3 bg-green-200 rounded mr-2"></span>
                    Строки с зелёным фоном соответствуют записям в основной базе данных
                </p>
            </div>
            @if($orderInfo)
            <div class="text-right">
                <p class="text-lg font-medium text-gray-900">
                    Заказ {{ $orderInfo->ordernum }} от {{ $orderInfo->created_at ? $orderInfo->created_at->format('d.m.Y') : 'N/A' }}
                </p>
            </div>
            @endif
        </div>
    </div>
    
    <div class="overflow-auto custom-scrollbar" style="max-height: 70vh;">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 sticky top-0">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="min-width: 100px;">
                        Артикул
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="min-width: 90px;">
                        Копировать
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="min-width: 120px;">
                        Код ФП
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="min-width: 200px;">
                        Автор
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="min-width: 300px;">
                        Наименование
                    </th>
                    <!-- Добавляем новую колонку "Тип" -->
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="min-width: 120px;">
                        Тип
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="min-width: 80px;">
                        Год
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="min-width: 80px;">
                        Кол-во
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="min-width: 100px;">
                        Цена, руб.
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" style="min-width: 120px;">
                        Действия
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($orders as $order)
                    @php
                        $isMatching = false;
                        if (!empty($order->ART) && !empty($order->year)) {
                            $key = $order->ART . '_' . $order->year;
                            $isMatching = isset($matchingKeys[$key]);
                        }
                    @endphp
                    <tr class="hover:bg-gray-50 {{ $isMatching ? 'matching-record' : '' }}">
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                            <div class="max-w-[100px] truncate" title="{{ $order->ART }}">
                                {{ $order->ART }}
                            </div>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                            <button 
                                @click="copyArticleToClipboard('{{ $order->ART }}')"
                                class="bg-gray-500 hover:bg-gray-600 text-white px-2 py-1 rounded text-xs font-medium transition-colors duration-200 flex items-center gap-1"
                                title="Копировать артикул">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                Арт.
                            </button>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                            <div class="max-w-[120px] truncate" title="{{ $order->seqNum }}">
                                {{ $order->seqNum }}
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <div class="max-w-[200px] line-clamp-2" title="{{ $order->author }}">
                                {{ $order->author }}
                            </div>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <div class="max-w-[300px] line-clamp-3" title="{{ $order->caption }}">
                                {{ $order->caption }}
                            </div>
                        </td>
                        <!-- Добавляем ячейку для колонки "Тип" -->
                        <td class="px-3 py-4 text-sm text-gray-900">
                            <div class="max-w-[120px] line-clamp-2" title="{{ $order->type }}">
                                {{ $order->type ?? '-' }}
                            </div>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $order->year }}
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                            {{ $order->quantity ?? '-' }}
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                            {{ isset($order->price) ? number_format($order->price, 2, ',', ' ') : '-' }}
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                            @if(is_null($order->is_verified) || $order->is_verified == 0)
                                <button 
                                    @click="copyToClipboard('{{ addslashes($order->author) }}', '{{ addslashes($order->caption) }}', '{{ $order->year }}')"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs font-medium transition-colors duration-200 flex items-center gap-1"
                                    title="Копировать в буфер обмена">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    Копировать
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="bg-white shadow-sm rounded-lg p-8 text-center">
    <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
    <h3 class="text-lg font-medium text-gray-900 mb-2">Нет загруженных данных</h3>
    <p class="text-gray-500">Загрузите Excel файл для отображения данных заказа</p>
</div>
@endif
    </div>

    <!-- Hidden forms for AJAX requests -->
    <form id="upload-form" method="POST" action="{{ route('manual-order.upload') }}" enctype="multipart/form-data" style="display: none;">
        @csrf
        <input type="file" name="excel_file" id="hidden-file-input">
    </form>

    <form id="clear-form" method="POST" action="{{ route('manual-order.clear') }}" style="display: none;">
        @csrf
    </form>

    <script>
function manualOrder() {
    return {
        selectedFile: null,
        loading: false,
        selectedUserId: {{ $selectedUserId ?? 'null' }},

        handleFileSelect(event) {
            this.selectedFile = event.target.files[0];
        },

        formatFileSize(bytes) {
            if (!bytes) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        async updateSelectedUser(userId) {
    // Сначала обновляем локальную переменную
    this.selectedUserId = userId || null;
    
    try {
        const response = await fetch('{{ route("manual-order.update-user") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: this.selectedUserId
            })
        });

        if (!response.ok) {
            throw new Error('Failed to update user');
        }
        
        console.log('Selected user updated:', this.selectedUserId); // Для отладки
        
    } catch (error) {
        console.error('Error updating user:', error);
        alert('Ошибка при сохранении выбранного пользователя');
        
        // В случае ошибки возвращаем предыдущее значение
        this.selectedUserId = {{ $selectedUserId ?? 'null' }};
    }
},

async uploadFile() {
    console.log('Current selectedUserId:', this.selectedUserId); // Для отладки
    
    if (!this.selectedFile) {
        alert('Пожалуйста, выберите файл для загрузки');
        return;
    }
    
    if (!this.selectedUserId) {
        alert('Пожалуйста, выберите пользователя перед загрузкой файла');
        return;
    }

    this.loading = true;
    
    const formData = new FormData();
    formData.append('excel_file', this.selectedFile);
    formData.append('user_id', this.selectedUserId);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

    try {
        const response = await fetch('{{ route("manual-order.upload") }}', {
            method: 'POST',
            body: formData,
        });

        if (response.ok) {
            window.location.reload();
        } else {
            throw new Error('Upload failed');
        }
    } catch (error) {
        alert('Ошибка при загрузке файла');
        console.error('Upload error:', error);
    } finally {
        this.loading = false;
    }
},

        async clearData() {
            if (!confirm('Вы уверены, что хотите очистить все данные?')) {
                return;
            }

            this.loading = true;

            try {
                const response = await fetch('{{ route("manual-order.clear") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                    },
                });

                if (response.ok) {
                    window.location.reload();
                } else {
                    throw new Error('Clear failed');
                }
            } catch (error) {
                alert('Ошибка при очистке данных');
                console.error('Clear error:', error);
            } finally {
                this.loading = false;
            }
        },

        async updateDatabase() {
            if (!confirm('Вы уверены, что хотите обновить базу данных из ИРБИС?')) {
                return;
            }

            this.loading = true;

            try {
                const response = await fetch('{{ route("manual-order.update-database") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                    },
                });

                const result = await response.json();

                if (response.ok) {
                    alert(result.message || 'База данных успешно обновлена!');
                    if (result.updated_count > 0) {
                        window.location.reload();
                    }
                } else {
                    throw new Error(result.message || 'Database update failed');
                }
            } catch (error) {
                alert('Ошибка при обновлении базы данных: ' + error.message);
                console.error('Database update error:', error);
            } finally {
                this.loading = false;
            }
        },

        async downloadOrders() {
            if (!confirm('Вы уверены, что хотите перенести проверенные заказы в основную базу?')) {
                return;
            }

            this.loading = true;

            try {
                const response = await fetch('{{ route("manual-order.download-orders") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                    },
                });

                const result = await response.json();

                if (response.ok) {
                    alert(result.message || 'Заказы успешно перенесены!');
                    if (result.transferred_count > 0) {
                        window.location.reload();
                    }
                } else {
                    throw new Error(result.message || 'Transfer failed');
                }
            } catch (error) {
                alert('Ошибка при переносе заказов: ' + error.message);
                console.error('Download orders error:', error);
            } finally {
                this.loading = false;
            }
        },

        copyArticleToClipboard(article) {
            try {
                // Копируем артикул в буфер обмена
                navigator.clipboard.writeText(article).then(() => {
                    // Показываем уведомление об успешном копировании
                    this.showCopyNotification(article);
                }).catch(err => {
                    console.error('Ошибка копирования:', err);
                    // Fallback для старых браузеров
                    this.fallbackCopyToClipboard(article);
                });
                
            } catch (error) {
                console.error('Ошибка при копировании артикула:', error);
                alert('Ошибка при копировании в буфер обмена');
            }
        },

        copyToClipboard(author, caption, year) {
            try {
                // Извлекаем первое слово из автора (до пробела)
                const authorFirstName = author.split(' ')[0];
                
                // Очищаем caption от знаков препинания и разбиваем на слова
                const cleanCaption = caption.replace(/[^\wа-яёА-ЯЁ\s\d]/g, ' ');
                const allWords = cleanCaption.split(/\s+/).filter(word => word.length > 0);
                
                // Фильтруем слова: только буквы, длиной более 3 символов, исключаем "язык" и "учебник"
                const excludeWords = ['язык', 'учебник', 'класс', 'часть', 'Часть'];
                const validWords = allWords.filter(word => {
                    const lowerWord = word.toLowerCase();
                    return /^[а-яёa-z]+$/i.test(word) && 
                           word.length > 3 && 
                           !excludeWords.includes(lowerWord);
                });
                
                // Находим первую цифру в caption
                const digitMatch = caption.match(/\d/);
                const firstDigit = digitMatch ? digitMatch[0] : null;
                
                // Выбираем до 2 случайных слов
                const shuffledWords = [...validWords].sort(() => 0.5 - Math.random());
                const selectedWords = shuffledWords.slice(0, 2);
                
                console.log('Caption:', caption);
                console.log('Valid words found:', validWords);
                console.log('Selected words:', selectedWords);
                console.log('First digit:', firstDigit);
                
                // Формируем строку
                let result = `("A=${authorFirstName}$")*("G=${year}$")`;
                
                // Добавляем K части, если есть данные
                const kParts = [];
                selectedWords.forEach(word => {
                    kParts.push(`"K=${word}$"`);
                });
                
                if (firstDigit) {
                    kParts.push(`"K=${firstDigit}$"`);
                }
                
                if (kParts.length > 0) {
                    result += `*(${kParts.join('/()*')}/()*)`
                }
                
                // Копируем в буфер обмена
                navigator.clipboard.writeText(result).then(() => {
                    // Показываем уведомление об успешном копировании
                    this.showCopyNotification(result);
                }).catch(err => {
                    console.error('Ошибка копирования:', err);
                    // Fallback для старых браузеров
                    this.fallbackCopyToClipboard(result);
                });
                
            } catch (error) {
                console.error('Ошибка при формировании строки:', error);
                alert('Ошибка при копировании в буфер обмена');
            }
        },

        fallbackCopyToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            document.body.appendChild(textArea);
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.showCopyNotification(text);
            } catch (err) {
                console.error('Fallback copy failed:', err);
                alert('Не удалось скопировать в буфер обмена');
            } finally {
                document.body.removeChild(textArea);
            }
        },

        showCopyNotification(copiedText) {
            // Создаем временное уведомление
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 transition-opacity duration-300 max-w-md';
            notification.innerHTML = `<div class="break-words">Строка скопирована в буфер обмена: ${copiedText}</div>`;
            document.body.appendChild(notification);
            
            // Удаляем уведомление через 3 секунды
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
    }
}
    </script>
    
    
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</body>
</html>