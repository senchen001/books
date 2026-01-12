<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Заказы') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            
                
                    @if($orderItems->count() > 0)
                        @php
                            // Group order items by order number
                            $groupedOrders = $orderItems->groupBy('ordernum');
                        @endphp

                        @foreach($groupedOrders as $orderNum => $items)
                        <div class="bg-gray overflow-hidden shadow-sm sm:rounded-lg mb-5 mt-5">
                        <div class="p-6 text-gray-900">
                            <h2 class="font-bold">Заказ № {{ $orderNum }} от {{ $items->first()->created_at }}</h2>
                            <table class="w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Название</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Год</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Автор</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Количество</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Цена</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($items as $item)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $item->book->caption }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $item->book->year }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ $item->book->author }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">                                        
                                                {{ $item->quantity }}                                                                                    
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">                                        
                                                {{ $item->price ? number_format($item->price, 2) : '-' }}                                                                                    
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                @if($item->is_verified)
                                                    Готов к скачиванию
                                                @else
                                                    {{ $item->status }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                             <!-- Кнопка для загрузки текстового файла -->
<div class="mt-4" style="display: flex; justify-content: space-between; align-items: center;">
    <!-- Левая часть: кнопка скачивания и текст -->
    <form action="{{ route('cart.download') }}" method="POST" style="margin: 0; display: flex; align-items: center; gap: 10px;">
        @csrf
        <input type="hidden" name="orderNum" value="{{ $orderNum }}">

@if(auth()->user()->user_verified_at !== null || $items->first()->is_verified)
    <a href="{{ route('order.download', [auth()->id(), $orderNum]) }}"
       style="padding: 8px 16px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none;">
        Скачать marc-записи
    </a>
@else
    <button type="button" disabled style="padding: 8px 16px; background-color: #f9f9f9; color: #888; border: 1px solid #ccc; border-radius: 4px; cursor: not-allowed;">
        Скачать marc-записи
    </button>
    @if($items->first()->status === 'Отменён')
        <span style="font-size: 14px; color: #cc0000;">Скачивание записей недоступно. Заказ отменён</span>
    @else
        <span style="font-size: 14px; color: #cc0000;">Скачивание записей недоступно. Ожидайте верификации</span>
    @endif
@endif
    </form>

    <!-- Правая часть: кнопка удаления -->
    
<form action="{{ route('cart.delete') }}" method="POST" style="margin: 0; display: flex; align-items: center; gap: 10px;">
    @csrf
    @method('DELETE')
    <input type="hidden" name="orderNum" value="{{ $orderNum }}">

    @if($items->first()->status === 'в обработке')
        <button type="submit" onclick="return confirm('Удалить весь заказ №{{ $orderNum }}?')"
            style="padding: 8px 16px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Удалить заказ
        </button>
@else
    @if($items->first()->status === 'Отменён')
        <span style="color: #cc0000; font-size: 14px; white-space: nowrap;">
            Заказ отменён. Если вы считаете, что произошла ошибка, то можете связаться с нами по телефону 8 812 678-97-27
        </span>
    @elseif(!$items->first()->is_verified)
        <span style="color: #cc0000; font-size: 14px; white-space: nowrap;">
            Заказ принят в работу, удаление невозможно. Вы можете связаться с нами по телефону 8 812 678-97-27
        </span>
    @endif
    <button type="button" disabled
        style="padding: 8px 16px; background-color: #f9f9f9; color: #888; border: 1px solid #ccc; border-radius: 4px; cursor: not-allowed;">
        Удалить заказ
    </button>
@endif
</form>
</div>

                        </div>
                            </div>
                        @endforeach

                       
                    @else
                        <p>У вас пока нет заказов</p>
                    @endif
                
            
        </div>
    </div>
</x-app-layout>