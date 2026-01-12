{{-- resources/views/command.blade.php --}}

<x-app-layout>
<x-slot name="header">
    <div class="flex flex-col">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Заказы') }}
        </h2>
            <input
                id="search_input"
                type="text"
                placeholder="Данные заказчика, дата, номер заказа..."
                class="mt-2 max-w-md rounded-md border border-gray-300 shadow-sm
                    focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
            >
    </div>
</x-slot>

<div class="py-12">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-7xl">

{{-- Заголовок с кнопками и сортировкой --}}
        <div class="flex items-center mb-6">
            <h1 class="text-2xl font-bold">Все заказы</h1>
            
            {{-- Кнопки управления по центру --}}
            <div class="flex-1 flex justify-center">
                <div class="flex items-center space-x-2">
                    <a href="{{ route('manual-order.index') }}" 
                       class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded shadow transition-colors w-40 text-center text-sm">
                        Загрузить заказ
                    </a>
                    <a href="{{ route('sync.interface') }}" 
                       class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded shadow transition-colors w-40 text-center text-sm">
                        ИРБИС→MySQL
                    </a>
                </div>
            </div>
            
            {{-- Сортировка справа --}}
            <div class="flex items-center space-x-2">
                <label for="sort" class="font-semibold text-gray-700">Сортировка:</label>
                <select id="sort" name="sort" class="rounded border border-gray-300 px-2 py-1 focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" onchange="handleSortChange()">
                    <option value="desc">По номеру заказа (убывание)</option>    
                    <option value="asc">По номеру заказа (возрастание)</option>
                </select>
            </div>
        </div>

        {{-- Основной контейнер с таблицей и фильтрами --}}
        <div class="flex gap-6">
            {{-- Левая часть: таблица заказов --}}
            <div class="flex-1">
                @if($groupedCartItems->isEmpty())
                    <p class="text-center">No orders found.</p>
                @else
                    <div class="flex flex-col items-center">
                        @foreach($groupedCartItems as $orderData)
                            <div class="customCard order-card w-full max-w-7xl m-3 p-4 border rounded shadow" 
                                 data-status="{{ mb_strtolower($orderData['items']->first()->status ?? '') }}"
                                 data-ordernum="{{ $orderData['ordernum'] }}"
                                 data-user="{{ $orderData['user']->name ?? '' }}"
                                 data-userid="{{ $orderData['userid'] }}">
                                @php
                                    $cartItems = $orderData['items'];
                                    $firstItem = $cartItems->first();
                                    $status = $firstItem->status ?? 'нет статуса';
                                    $isCancelled = mb_strtolower($status) === 'отменён';
                                    $createdAt = $firstItem->created_at ? $firstItem->created_at->format('d.m.Y H:i') : 'нет даты';
                                    
                                    // Определяем состояния кнопок на основе статуса
                                    $isInProcessing = mb_strtolower($status) === 'в обработке';
                                    $isInWork = mb_strtolower($status) === 'принят в работу';
                                    $isReady = mb_strtolower($status) === 'готов к выдаче';
                                @endphp
                                
                                    <h3 class="font-semibold">
                                    <div class="flex items-center justify-between" style="white-space: nowrap;">
                                        <span style="display: inline-flex; align-items: center;">
                                        Заказ № {{ $orderData['ordernum'] }} от 
                                        <span style="margin: 0 8px;">{{ $createdAt }}</span>
                                        </span>
                                        <span style="margin-left: auto; white-space: nowrap;">
                                        Статус: {{ $status }}
                                        </span>
                                    </div>
                                    <div class="flex items-center mt-1" style="white-space: nowrap;">
                                        <span style="display: inline-flex; align-items: center;">
                                        Пользователь:&nbsp;
                                        @if($orderData['user']->user_verified_at)
                                        <svg 
                                            style="color: #16a34a; vertical-align: middle; margin: 0 6px;" 
                                            fill="none" 
                                            stroke="#16a34a" 
                                            stroke-width="3" 
                                            stroke-linecap="round" 
                                            stroke-linejoin="round" 
                                            viewBox="0 0 24 24" 
                                            xmlns="http://www.w3.org/2000/svg" 
                                            width="24" 
                                            height="24"
                                        >
                                            <circle cx="12" cy="12" r="10" />
                                            <path d="M9 12l2 2 4-4"/>
                                        </svg>
                                        @endif
                                        {{ $orderData['user']->name }} (ID: {{ $orderData['userid'] }})
                                        </span>
                                    </div>
                                    </h3>                            
                                <table class="w-full mt-4">
                                    <thead>
                                        <tr>
                                            <th class="text-left">Учебник</th>
                                            <th class="text-left">Количество</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($cartItems as $cartItem)
                                            @php
                                                $isApproved = $cartItem->book->approved == 1;
                                            @endphp
                                            <tr class="{{ $isApproved ? 'approved-row' : '' }}">
                                                <td>{{ $cartItem->book->caption }}</td>
                                                <td>{{ $cartItem->quantity }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                
                                <div class="mt-4 mb-4 flex items-center gap-2 justify-between order-block" data-cancelled="{{ $isCancelled ? '1' : '0' }}">
                                    <div class="flex items-center gap-2 order-action-group flex-wrap">
                                        @if ($isCancelled)
                                            <div class="bg-gray-400 text-white font-semibold py-2 px-3 rounded shadow w-44 text-center text-sm whitespace-nowrap opacity-50 cursor-not-allowed">
                                                Скачать
                                            </div>
                                            <div class="bg-gray-400 text-white font-semibold py-2 px-3 rounded shadow w-44 text-center text-sm whitespace-nowrap opacity-50 cursor-not-allowed">
                                                Скачать CSV
                                            </div>
                                        @else
                                            <a href="{{ route('order.download', [$orderData['userid'], $orderData['ordernum']]) }}"
                                            class="bg-blue-600 text-white font-semibold py-2 px-3 rounded shadow hover:bg-blue-700 w-44 text-center text-sm whitespace-nowrap">
                                                Скачать
                                            </a>
                                            <a href="{{ url('/download-csv?ordernum=' . $orderData['ordernum']) }}"
                                            class="bg-blue-600 text-white font-semibold py-2 px-3 rounded shadow hover:bg-blue-700 w-44 text-center text-sm whitespace-nowrap">
                                                Скачать CSV
                                            </a>
                                        @endif

                                        {{-- Кнопка "Принять в работу" / "Убрать из работы" / "Заказ готов" --}}
                                        @if ($isCancelled)
                                            <div class="px-3 py-2 font-semibold rounded shadow bg-gray-400 text-white text-center cursor-not-allowed opacity-50 text-sm whitespace-nowrap inline-block" style="width: 156px;">
                                                {{ $isReady ? 'Заказ готов' : ($isInWork ? 'Убрать из работы' : 'Принять в работу') }}
                                            </div>
                                        @elseif ($isReady)
                                            <div class="px-3 py-2 font-semibold rounded shadow border border-black text-green-600 bg-white text-center cursor-not-allowed opacity-50 text-sm whitespace-nowrap inline-block" style="width: 156px;">
                                                Заказ готов
                                            </div>
                                        @else
                                            <form method="POST" action="{{ route('admin.order.toggleStatus', [$orderData['userid'], $orderData['ordernum']]) }}">
                                                @csrf
                                                <button type="submit"
                                                    class="px-3 py-2 font-semibold rounded shadow text-white hover:opacity-90 transition-opacity text-sm whitespace-nowrap"
                                                    style="background-color: {{ $isInWork ? '#dc2626' : '#2563eb' }}; width: 156px;"
                                                >
                                                    {{ $isInWork ? 'Убрать из работы' : 'Принять в работу' }}
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Кнопка "Верифицировать" / "Снять верификацию" --}}
                                        @if ($isCancelled)
                                            <div class="px-3 py-2 font-semibold rounded shadow bg-gray-400 text-white text-center cursor-not-allowed opacity-50 text-sm whitespace-nowrap inline-block" style="width: 156px;">
                                                {{ $isReady ? 'Снять верификацию' : 'Верифицировать' }}
                                            </div>
                                        @elseif ($isInProcessing)
                                            <div class="px-3 py-2 font-semibold rounded shadow bg-green-500 text-white text-center cursor-not-allowed opacity-70 text-sm whitespace-nowrap inline-block" style="width: 156px;">
                                                Верифицировать
                                            </div>
                                        @else
                                            <form method="POST" action="{{ route('admin.order.toggleVerification', [$orderData['userid'], $orderData['ordernum']]) }}">
                                                @csrf
                                                <button type="submit"
                                                    class="px-3 py-2 font-semibold rounded shadow text-white hover:opacity-90 transition-opacity text-sm whitespace-nowrap"
                                                    style="background-color: {{ $isReady ? '#dc2626' : '#16a34a' }}; width: 156px;"
                                                >
                                                    {{ $isReady ? 'Снять верификацию' : 'Верифицировать' }}
                                                </button>
                                            </form>
                                        @endif
                                        
                                        @if ($isCancelled)
                                            <div class="bg-gray-400 text-white font-semibold py-2 px-3 rounded shadow w-44 text-center text-sm whitespace-nowrap opacity-50 cursor-not-allowed">
                                                Передать в ИРБИС
                                            </div>
                                        @else
                                            <button
                                                type="button"
                                                class="bg-blue-600 text-white font-semibold py-2 px-3 rounded shadow hover:bg-blue-700 w-44 text-center text-sm whitespace-nowrap"
                                            >
                                                Передать в ИРБИС
                                            </button>
                                        @endif
                                    </div>
                                        {{-- Кнопка "Отменить заказ" / "Вернуть в работу" справа --}}
                                        @if ($isCancelled)
                                            <form method="POST" action="{{ route('admin.order.restore', [$orderData['userid'], $orderData['ordernum']]) }}" class="restore-form">
                                                @csrf
                                                <button type="submit"
                                                    style="background-color: #16a34a;" {{-- это соответствует bg-green-600 --}}
                                                    class="hover:bg-green-700 text-white font-bold py-2 px-3 rounded shadow w-44 text-center text-sm whitespace-nowrap transition-colors"
                                                >
                                                    Вернуть в работу
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.order.cancel', [$orderData['userid'], $orderData['ordernum']]) }}" class="cancel-form">
                                                @csrf
                                                <button type="submit"
                                                    style="background-color: #dc2626;" {{-- это соответствует bg-red-600 --}}
                                                    class="hover:bg-red-700 text-white font-bold py-2 px-3 rounded shadow cancel-btn w-44 text-center text-sm whitespace-nowrap transition-colors"
                                                    onclick="return confirm('Вы уверены, что хотите отменить заказ?')"
                                                >
                                                    Отменить заказ
                                                </button>
                                            </form>
                                        @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Правая часть: фильтры --}}
            <div class="w-64 flex-shrink-0 space-y-6">
                {{-- Фильтры заказов --}}
                <div>
                    <div class="font-semibold text-gray-700 mb-3">Заказы</div>
                    <form>
                        <div class="flex flex-col space-y-2">
                            <label class="inline-flex items-center">
                                <input type="radio" name="status_filter" class="form-radio status-filter" value="all" />
                                <span class="ml-2">Все</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="status_filter" class="form-radio status-filter" value="processing" />
                                <span class="ml-2">В обработке</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="status_filter" class="form-radio status-filter" value="accepted" />
                                <span class="ml-2">Принятые в работу</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="status_filter" class="form-radio status-filter" value="ready" />
                                <span class="ml-2">Готовые к выдаче</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="status_filter" class="form-radio status-filter" value="cancelled" />
                                <span class="ml-2">Отменённые</span>
                            </label>
                        </div>
                    </form>
                </div>

                {{-- Фильтры клиентов --}}
                <div>
                    <div class="font-semibold text-gray-700 mb-3">Клиенты</div>
                    <form>
                        <div class="flex flex-col space-y-2 mb-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="filter2" class="form-radio" value="optionA" />
                                <span class="ml-2">Активные клиенты</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="filter2" class="form-radio" value="optionB" />
                                <span class="ml-2">Все</span>
                            </label>
                        </div>
                        
                        {{-- Выпадающее меню с пользователями --}}
                        <div class="mt-3">
                            <label for="user_filter" class="block text-sm font-medium text-gray-700 mb-2">Выберите клиента:</label>
                            <select id="user_filter" name="user_filter" class="w-full rounded border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" onchange="handleUserFilterChange()">
                                <option value="all">Все клиенты</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} (ID: {{ $user->id }})</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Восстанавливаем сохраненный фильтр статуса
    const savedFilter = localStorage.getItem('orderStatusFilter') || 'all';
    const filterRadio = document.querySelector(`.status-filter[value="${savedFilter}"]`);
    if (filterRadio) {
        filterRadio.checked = true;
    }
    
    // Восстанавливаем сохраненный фильтр пользователя
    const savedUserFilter = localStorage.getItem('orderUserFilter') || 'all';
    const userSelect = document.getElementById('user_filter');
    if (userSelect) {
        userSelect.value = savedUserFilter;
    }
    
    // Восстанавливаем сохраненную сортировку
    const savedSort = localStorage.getItem('orderSort') || 'desc';
    const sortSelect = document.getElementById('sort');
    if (sortSelect) {
        sortSelect.value = savedSort;
    }
    
    // Восстанавливаем сохраненный поисковый запрос
    const savedSearch = localStorage.getItem('orderSearchQuery') || '';
    const searchInput = document.querySelector('input[type="text"][placeholder*="Данные заказчика"]');
    if (searchInput) {
        searchInput.value = savedSearch;
    }
    
    // Применяем фильтры и сортировку при загрузке страницы
    applyFilters();
    applySorting(savedSort);

    // Добавляем обработчики событий для фильтров статуса
    document.querySelectorAll('.status-filter').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                handleStatusFilterChange(this.value);
            }
        });
    });

    // Добавляем обработчик для поиска
    if (searchInput) {
        searchInput.addEventListener('input', handleSearchInput);
    }
});

function handleSearchInput(event) {
    const searchQuery = event.target.value;
    
    // Сохраняем поисковый запрос в localStorage
    localStorage.setItem('orderSearchQuery', searchQuery);
    
    // Применяем фильтры с учетом поиска
    applyFilters();
}

function handleStatusFilterChange(filterValue) {
    // Сохраняем фильтр в localStorage
    localStorage.setItem('orderStatusFilter', filterValue);
    
    // Применяем фильтры без перезагрузки страницы
    applyFilters();
}

function handleUserFilterChange() {
    const userSelect = document.getElementById('user_filter');
    const selectedUserId = userSelect.value;
    
    // Сохраняем фильтр пользователя в localStorage
    localStorage.setItem('orderUserFilter', selectedUserId);
    
    // Применяем фильтры без перезагрузки страницы
    applyFilters();
}

function applyFilters() {
    const statusFilter = localStorage.getItem('orderStatusFilter') || 'all';
    const userFilter = localStorage.getItem('orderUserFilter') || 'all';
    const searchQuery = localStorage.getItem('orderSearchQuery') || '';
    const orderCards = document.querySelectorAll('.order-card');

    orderCards.forEach(card => {
        const status = card.dataset.status;
        const userId = card.dataset.userid;
        const orderNum = card.dataset.ordernum;
        const userName = card.dataset.user;
        
        // Получаем текст заголовка заказа для поиска
        const headerElement = card.querySelector('h3');
        const headerText = headerElement ? headerElement.textContent.trim().replace(/\s+/g, ' ') : '';
        
        let shouldShow = true;

        // Применяем фильтр по статусу
        switch (statusFilter) {
            case 'processing':
                shouldShow = shouldShow && status === 'в обработке';
                break;
            case 'accepted':
                shouldShow = shouldShow && status === 'принят в работу';
                break;
            case 'ready':
                shouldShow = shouldShow && status === 'готов к выдаче';
                break;
            case 'cancelled':
                shouldShow = shouldShow && status === 'отменён';
                break;
            case 'all':
            default:
                shouldShow = shouldShow && status !== 'отменён'; // скрываем отменённые
                break;
        }

        // Применяем фильтр по пользователю
        if (userFilter !== 'all') {
            shouldShow = shouldShow && userId === userFilter;
        }

        // Применяем поисковый фильтр
        if (searchQuery.trim() !== '') {
            const searchLower = searchQuery.toLowerCase();
            
            // Исключаем общие слова из текста для поиска
            const cleanedHeaderText = headerText
                .replace(/заказ\s*/gi, '')
                .replace(/пользователь:\s*/gi, '')
                .toLowerCase();
            
            // Проверяем, содержит ли очищенный заголовок поисковый запрос
            shouldShow = shouldShow && cleanedHeaderText.includes(searchLower);
        }

        card.style.display = shouldShow ? 'block' : 'none';
    });
}

function handleSortChange() {
    const sortSelect = document.getElementById('sort');
    const sortValue = sortSelect.value;
    
    // Сохраняем сортировку в localStorage
    localStorage.setItem('orderSort', sortValue);
    
    // Применяем сортировку без перезагрузки страницы
    applySorting(sortValue);
}

function applySorting(sortValue) {
    const orderContainer = document.querySelector('.flex.flex-col.items-center');
    const orderCards = Array.from(orderContainer.querySelectorAll('.order-card'));
    
    // Сортируем карточки по номеру заказа
    orderCards.sort((a, b) => {
        const orderNumA = parseInt(a.dataset.ordernum);
        const orderNumB = parseInt(b.dataset.ordernum);
        
        if (sortValue === 'asc') {
            return orderNumA - orderNumB;
        } else {
            return orderNumB - orderNumA;
        }
    });
    
    // Перемещаем отсортированные элементы в DOM
    orderCards.forEach(card => {
        orderContainer.appendChild(card);
    });
}

// Восстанавливаем позицию при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    const savedPosition = sessionStorage.getItem('scrollPosition');
    if (savedPosition) {
        window.scrollTo(0, parseInt(savedPosition));
        sessionStorage.removeItem('scrollPosition');
    }
});

// Сохраняем позицию перед отправкой формы
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        sessionStorage.setItem('scrollPosition', window.scrollY);
    });
});
</script>
</x-app-layout>