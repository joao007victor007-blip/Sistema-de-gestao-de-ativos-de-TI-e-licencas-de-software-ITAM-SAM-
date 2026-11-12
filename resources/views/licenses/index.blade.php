<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Licenças de software</h2>
            @can('manage-inventory')
                <a href="{{ route('licenses.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    Nova licença
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

            <form method="GET" action="{{ route('licenses.index') }}"
                  class="mb-4 grid grid-cols-1 gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-4">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="Nome da licença ou do software" class="sm:col-span-2 {{ $filterClasses }}">

                <select name="software_id" class="{{ $filterClasses }}">
                    <option value="">Todos os softwares</option>
                    @foreach ($softwareList as $software)
                        <option value="{{ $software->id }}" @selected((string) ($filters['software_id'] ?? '') === (string) $software->id)>
                            {{ $software->name }}
                        </option>
                    @endforeach
                </select>

                <select name="situacao" class="{{ $filterClasses }}">
                    <option value="ativas" @selected($filters['situacao'] === 'ativas')>Ativas</option>
                    <option value="vencendo" @selected($filters['situacao'] === 'vencendo')>Vencem em breve</option>
                    <option value="renovando" @selected($filters['situacao'] === 'renovando')>Renovam em breve</option><option value="vencidas" @selected($filters['situacao'] === 'vencidas')>Vencidas</option>
                    <option value="baixadas" @selected($filters['situacao'] === 'baixadas')>Baixadas</option>
                    <option value="todas" @selected($filters['situacao'] === 'todas')>Todas</option>
                </select>

                <div class="flex items-center justify-end gap-4 sm:col-span-4">
                    <a href="{{ route('licenses.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Limpar filtros</a>
                    <x-primary-button>Filtrar</x-primary-button>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-6 py-3 font-medium">Licença</th>
                            <th class="px-6 py-3 font-medium">Tipo</th>
                            <th class="px-6 py-3 font-medium">Atribuição</th>
                            <th class="px-6 py-3 font-medium">Em uso</th>
                            <th class="px-6 py-3 font-medium">Renovação ou vencimento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($licenses as $license)
                            <tr>
                                <td class="px-6 py-4">
                                    <a href="{{ route('licenses.show', $license) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ $license->name }}</a>
                                    <div class="text-xs text-gray-500">{{ $license->software->name }}</div>
                                </td>
                                <td class="px-6 py-4 text-gray-600">{{ $license->license_type->label() }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $license->assignment_type->label() }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $license->assignment_type->usesSeats() ? $license->active_assignments_count . ' de ' . $license->seats : 'Corporativa' }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="text-gray-600">{{ $license->dateLabel() ?? '—' }}</span>
                                        <x-license-expiration-badge :license="$license" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">Nenhuma licença encontrada com esses filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $licenses->links() }}</div>
        </div>
    </div>
</x-app-layout>