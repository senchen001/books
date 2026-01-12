<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Клиенты') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if(session('success'))
                        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                            <span class="block sm:inline">{{ session('success') }}</span>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Пользователь
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Дата регистрации
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Верификация
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Статус
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($users as $user)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $user->name }} (ID: {{ $user->id }})
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $user->created_at->format('d.m.Y H:i') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
<button 
    onclick="toggleVerification({{ $user->id }}, this)"
    class="verification-btn inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150"
    style="width: 200px; background-color: {{ $user->user_verified_at ? '#DC2626' : '#2563EB' }};"
    data-user-id="{{ $user->id }}"
>
    {{ $user->user_verified_at ? 'Снять верификацию' : 'Верифицировать' }}
</button>

                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div class="verification-status" data-user-id="{{ $user->id }}">
                                                @if($user->user_verified_at)
                                                    <span class="text-green-600 font-medium">
                                                        Пользователь верифицирован ({{ is_string($user->user_verified_at) ? $user->user_verified_at : $user->user_verified_at->format('d.m.Y H:i') }})
                                                    </span>
                                                @else
                                                    <span class="text-gray-500">
                                                        Пользователь не верифицирован
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                            Пользователи не найдены
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="loading-overlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 flex items-center">
            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Обновление...
        </div>
    </div>

    <script>
function toggleVerification(userId, button) {
    const isRemoving = button.textContent.trim() === 'Снять верификацию';

    if (isRemoving) {
        const confirmed = confirm('Вы уверены, что хотите снять верификацию с этого пользователя?');
        if (!confirmed) {
            return;
        }
    }

    const overlay = document.getElementById('loading-overlay');
    const statusContainer = document.querySelector(`.verification-status[data-user-id="${userId}"]`);
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    button.disabled = true;

    const csrfToken = document.querySelector('meta[name="csrf-token"]');

    fetch(`/clients/${userId}/toggle-verification`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken ? csrfToken.getAttribute('content') : '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            button.textContent = data.button_text;
            button.style.backgroundColor = data.button_text === 'Снять верификацию' ? '#DC2626' : '#2563EB';

            if (statusContainer) {
                statusContainer.innerHTML = `<span class="${data.status_class}">${data.status_text}</span>`;
            }

            const successDiv = document.createElement('div');
            successDiv.className = 'fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50';
            successDiv.innerHTML = `
                <span class="block sm:inline">Статус верификации обновлен</span>
                <button onclick="this.parentElement.remove()" class="float-right ml-4">&times;</button>
            `;
            document.body.appendChild(successDiv);

            setTimeout(() => {
                if (successDiv.parentNode) successDiv.remove();
            }, 3000);
        } else {
            throw new Error(data.message || 'Произошла ошибка');
        }
    })
    .catch(error => {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50';
        alertDiv.innerHTML = `
            <span class="block sm:inline">${error.message || 'Произошла ошибка при обновлении статуса'}</span>
            <button onclick="this.parentElement.remove()" class="float-right ml-4">&times;</button>
        `;
        document.body.appendChild(alertDiv);

        setTimeout(() => {
            if (alertDiv.parentNode) alertDiv.remove();
        }, 5000);
    })
    .finally(() => {
        button.disabled = false;
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
    });
}

    </script>
</x-app-layout>