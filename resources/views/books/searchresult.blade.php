<x-app-layout>
    
<x-slot name="header">
    <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Учебники') }}
        </h2>
        <div class="flex items-center space-x-2">
            <form action="{{ route('books.search') }}" method="GET">
                <input type="text" name="query" placeholder="Искать..." class="border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                <button class="bg-blue-500 text-white rounded-lg p-2 hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50" style="margin-left:10px; width:45px;">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>
</x-slot>
    <div class="py-12">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Панель с классами сверху -->
            <div class="mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    
                </div>
            </div>

            <div class="flex">
                <!-- Боковое меню -->
                <div class="w-64 mr-6">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900">
                            <a href="{{ route('home') }}" class="inline-block bg-blue-500 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 transition duration-300">
                                Вернуться на главную
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Таблица с книгами -->
                <div class="flex-1 overflow-x-auto">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900">
                            @if($results->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th style="min-width:110px;"></th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Год</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Кол-во</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Цена</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Название</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Автор</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Класс</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">№</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                                                                        @foreach($results as $book)
                                                <tr>
                                                    <td class="px-6 py-4 text-sm text-gray-500" style="min-width: 70px; width: 70px; max-width: 70px;">
                                                        @if(!is_null($book->url_id))
                                                            <div class="relative">
                                                                <a href="https://books.rusneb.ru/book/ru/nbr?book={{ $book->url_id }}" target="_blank">
                                                                <!-- Картинка обложки -->
                                                                <img src="{{ asset('images/thumbs/thumbs_' . $book->url_id . '.jpg') }}" 
                                                                    style="width:70px;" alt="обложка" 
                                                                    data-book-id="{{ $book->url_id }}" 
                                                                    onmouseover="showPreview({{ $book->url_id }})" onmouseleave="hidePreview({{ $book->url_id }})">

                                                                <!-- Контейнер для превью -->
                                                                <div id="preview-{{ $book->url_id }}" 
                                                                    class="absolute hidden z-10 bg-white shadow-lg p-2 rounded-lg">
                                                                    <img src="{{ asset('images/preview/preview_' . $book->url_id . '.jpg') }}" 
                                                                        style="max-width: 500px; max-height: 500px; object-fit: contain;" 
                                                                        alt="Превью">
                                                                </div>
                                                            </div>
                                                        @else
                                                            <img src="{{ asset('images/bookcover.png') }}" class="w-16 h-16 object-cover" alt="обложка">
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm font-medium text-gray-900">{{ $book->year }}</div>
                                                    </td>
                                                    
                                                    <form action="{{ route('cart.add') }}" method="POST" class="contents">
                                                        @csrf
                                                        <input type="hidden" name="book_id" value="{{ $book->id }}">
                                                        
                                                        <!-- Ячейка с количеством -->
                                                        <td class="px-6 py-4 text-sm font-medium">
                                                            <input 
                                                                style="width: 7ch;"
                                                                type="number" 
                                                                name="quantity" 
                                                                value="{{ isset($cartItems[$book->id]) && $cartItems[$book->id]['quantity'] > 0 ? $cartItems[$book->id]['quantity'] : 1 }}" 
                                                                min="0" 
                                                                class="quantity-input w-16 border rounded px-2 py-1"
                                                                data-product-id="{{ $book->id }}"
                                                            >
                                                        </td>
                                                        
                                                        <!-- Ячейка с ценой и кнопкой -->
                                                        <td class="px-2 py-4 text-sm font-medium">
                                                            <div class="flex items-center">
                                                                <input 
                                                                    style="width: 10ch;"
                                                                    type="number" 
                                                                    name="price" 
                                                                    value="{{ isset($cartItems[$book->id]) && $cartItems[$book->id]['price'] ? $cartItems[$book->id]['price'] : '' }}" 
                                                                    step="0.01" 
                                                                    placeholder="Цена"
                                                                    class="price-input w-20 mr-2 border rounded px-2 py-1"
                                                                    data-product-id="{{ $book->id }}"
                                                                >
                                                                
                                                                <div id="cart-button-{{ $book->id }}">
                                                                    @if(isset($cartItems[$book->id]))
                                                                        <!-- Зеленая кнопка -->
                                                                        <button 
                                                                            type="button" 
                                                                            onclick="window.location.href='{{ route('cart.index') }}'" 
                                                                            class="text-white py-2 px-4 rounded flex flex-col items-center justify-center"
                                                                            style="min-width: 80px; min-height: 60px; background-color:#22c55e;"
                                                                        >
                                                                            <span class="font-bold select-none">В корзине</span>
                                                                            <span class="text-sm select-none">Перейти</span>
                                                                        </button>
                                                                    @else
                                                                        <!-- Синяя кнопка -->
                                                                        <button 
                                                                            type="submit" 
                                                                            class="text-white py-2 px-4 rounded flex flex-col items-center justify-center"
                                                                            style="min-width: 80px; min-height: 60px; background-color:#3b82f6;"
                                                                        >
                                                                            <span class="font-bold select-none">В корзину</span>
                                                                        </button>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </form>

                                                    <td class="px-6 py-4 text-sm text-gray-900 break-words">
                                                        {{ $book->caption }}
                                                    </td>
                                                    <td class="px-6 py-4 text-sm text-gray-900 break-words">
                                                        {{ $book->author }}
                                                    </td>
                                                    <td class="px-6 py-4 text-sm text-gray-500 break-words">
                                                        {{ $book->class }}
                                                    </td>
                                                    <td class="px-6 py-4 text-sm text-gray-500">
                                                        {{ $book->seqNum }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p>Учебники не найдены</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@section('cart-scripts')
    <script src="{{ asset('js/updatequantity.js') }}"></script>
    <script src="{{ asset('js/cart.js') }}"></script>
    <script src="{{ asset('js/preview.js') }}"></script>
@endsection    

    
</x-app-layout>