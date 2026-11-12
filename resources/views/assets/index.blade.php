<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ativos de hardware</h2>
            @can('manage-inventory')
                <a href="{{ route('assets.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    Novo ativo
                </a>
            @endcan
        </div>
    </x-slot>

    @php
        $filterClasses = 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm';
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            
            @if (($filters['termo'] ?? null) === 'pendente')
                <div class="mb-4 rounded-md bg-yellow-50 p-4 text-sm text-yellow-800">
                    Mostrando apenas equipamentos em uso com o termo de responsabilidade pendente.
                    <a href="{{ route('assets.index') }}" class="underline">Ver todos os ativos</a>
                </div>
            @endif

            <form method="GET" action="{{ route('assets.index') }}"
                  class="mb-4 grid grid-cols-1 gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-5">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="Patrimônio, código, série, modelo ou descrição" class="sm:col-span-2 {{ $filterClasses }}">

                <select name="category_id" class="{{ $filterClasses }}">
                    <option value="">Todas as categorias</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>

                <select name="location_id" class="{{ $filterClasses }}">
                    <option value="">Todos os locais</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) ($filters['location_id'] ?? '') === (string) $location->id)>
                            {{ $location->name }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="{{ $filterClasses }}">
                    <option value="ativos" @selected($filters['status'] === 'ativos')>Todos, exceto descartados</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                    <option value="todos" @selected($filters['status'] === 'todos')>Todos</option>
                </select>

                <div class="flex items-center justify-end gap-4 sm:col-span-5">
                    <a href="{{ route('assets.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Limpar filtros</a>
                    <x-primary-button>Filtrar</x-primary-button>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-6 py-3 font-medium">Identificação</th>
                            <th class="px-6 py-3 font-medium">Categoria</th>
                            <th class="px-6 py-3 font-medium">Equipamento</th>
                            <th class="px-6 py-3 font-medium">Nº de série</th>
                            <th class="px-6 py-3 font-medium">Local</th>
                            <th class="px-6 py-3 font-medium">Responsável</th>
                            <th class="px-6 py-3 font-medium">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($assets as $asset)
                            <tr>
                                <td class="px-6 py-4">
                                    <a href="{{ route('assets.show', $asset) }}" class="font-medium text-indigo-600 hover:text-indigo-900">
                                        {{ $asset->displayIdentifier() }}
                                    </a>
                                    @if ($asset->asset_tag)
                                        <div class="text-xs text-gray-500">{{ $asset->code }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-600">{{ $asset->category->name }}</td>
                                <td class="px-6 py-4 text-gray-900">
                                    {{ $asset->displayName() }}
                                    @if ($asset->description)
                                        <div class="text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($asset->description, 60) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-600">{{ $asset->serial_number ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $asset->location?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $asset->currentAssignment?->employee->name ?? '—' }}</td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full px-2 py-1 text-xs {{ $asset->status->badgeClasses() }}">{{ $asset->status->label() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">Nenhum ativo encontrado com esses filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $assets->links() }}</div>
        </div>
    </div>
</x-app-layout>