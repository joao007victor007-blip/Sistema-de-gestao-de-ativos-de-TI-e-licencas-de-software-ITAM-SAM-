<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Colaboradores</h2>
            @can('manage-inventory')
                <a href="{{ route('employees.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    Novo colaborador
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

            {{-- Busca e filtros --}}
            <form method="GET" action="{{ route('employees.index') }}"
                  class="mb-4 grid grid-cols-1 gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-5">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="Nome, matrícula ou e-mail" class="sm:col-span-2 {{ $filterClasses }}">

                <select name="department_id" class="{{ $filterClasses }}">
                    <option value="">Todos os setores</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>
                            {{ $department->name }}
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
                    <option value="ativos" @selected($filters['status'] === 'ativos')>Ativos</option>
                    <option value="desligados" @selected($filters['status'] === 'desligados')>Desligados</option>
                    <option value="pendencias" @selected($filters['status'] === 'pendencias')>Desligados com pendências</option>
                    <option value="todos" @selected($filters['status'] === 'todos')>Todos</option>
                </select>

                <div class="flex items-center justify-end gap-4 sm:col-span-5">
                    <a href="{{ route('employees.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Limpar filtros</a>
                    <x-primary-button>Filtrar</x-primary-button>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-6 py-3 font-medium">Nome</th>
                            <th class="px-6 py-3 font-medium">Matrícula</th>
                            <th class="px-6 py-3 font-medium">Setor</th>
                            <th class="px-6 py-3 font-medium">Local</th>
                            <th class="px-6 py-3 font-medium">Em posse</th>
                            <th class="px-6 py-3 font-medium">Situação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($employees as $employee)
                            @php
                                $holdings = collect([
                                    $employee->open_asset_assignments_count ? $employee->open_asset_assignments_count . ' equip.' : null,
                                    $employee->active_license_assignments_count ? $employee->active_license_assignments_count . ' lic.' : null,
                                ])->filter()->implode(', ');
                            @endphp
                            <tr>
                                <td class="px-6 py-4">
                                    <a href="{{ route('employees.show', $employee) }}" class="font-medium text-indigo-600 hover:text-indigo-900">
                                        {{ $employee->name }}
                                    </a>
                                    @if ($employee->job_title)
                                        <div class="text-xs text-gray-500">{{ $employee->job_title }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-600">{{ $employee->registration ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $employee->department?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $employee->location?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $holdings ?: '—' }}</td>
                                <td class="px-6 py-4">
                                    @if ($employee->is_active)
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs text-green-800">Ativo</span>
                                    @elseif ($employee->open_asset_assignments_count > 0)
                                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs text-red-800">Desligado com pendências</span>
                                    @else
                                        <span class="rounded-full bg-gray-200 px-2 py-1 text-xs text-gray-700">
                                            Desligado em {{ $employee->terminated_at?->format('d/m/Y') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">Nenhum colaborador encontrado com esses filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $employees->links() }}</div>
        </div>
    </div>
</x-app-layout>