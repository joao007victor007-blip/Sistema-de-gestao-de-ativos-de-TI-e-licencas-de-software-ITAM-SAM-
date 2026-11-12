<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $labels['plural'] }}</h2>
            @can('manage-inventory')
                <a href="{{ route($labels['route'] . '.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ $labels['new'] }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-6 py-3 font-medium">Nome</th>
                            <th class="px-6 py-3 font-medium">Observações</th>
                            <th class="px-6 py-3 font-medium">Situação</th>
                            @can('manage-inventory')
                                <th class="px-6 py-3 font-medium text-right">Ações</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($items as $item)
                            <tr>
                                <td class="px-6 py-4 text-gray-900">{{ $item->name }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ \Illuminate\Support\Str::limit($item->notes, 80) }}</td>
                                <td class="px-6 py-4">
                                    @if ($item->is_active)
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs text-green-800">Ativo</span>
                                    @else
                                        <span class="rounded-full bg-gray-200 px-2 py-1 text-xs text-gray-700">Desativado</span>
                                    @endif
                                </td>
                                @can('manage-inventory')
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route($labels['route'] . '.edit', $item) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500">{{ $labels['empty'] }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $items->links() }}</div>
        </div>
    </div>
</x-app-layout>