<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Auditoria</h2>
    </x-slot>

    @php
        $filterClasses = 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm';
        $actionClasses = fn ($action) => match ($action) {
            \App\Enums\AuditAction::Disposed, \App\Enums\AuditAction::Retired,
            \App\Enums\AuditAction::Terminated, \App\Enums\AuditAction::LoginFailed => 'bg-red-100 text-red-800',
            \App\Enums\AuditAction::KeyRevealed, \App\Enums\AuditAction::PasswordReset => 'bg-yellow-100 text-yellow-800',
            \App\Enums\AuditAction::Login, \App\Enums\AuditAction::Logout => 'bg-gray-100 text-gray-700',
            default => 'bg-blue-100 text-blue-800',
        };
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (($filters['tipo'] ?? null) && ($filters['item'] ?? null))
                <div class="mb-4 rounded-md bg-blue-50 p-4 text-sm text-blue-800">
                    Mostrando apenas os registros de um item específico.
                    <a href="{{ route('audit.index') }}" class="underline">Ver todos os registros</a>
                </div>
            @endif

            <form method="GET" action="{{ route('audit.index') }}"
                  class="mb-4 grid grid-cols-1 gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-5">
                @if ($filters['tipo'] ?? null)
                    <input type="hidden" name="tipo" value="{{ $filters['tipo'] }}">
                    <input type="hidden" name="item" value="{{ $filters['item'] ?? '' }}">
                @endif

                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="Texto da descrição" class="{{ $filterClasses }}">

                <select name="user_id" class="{{ $filterClasses }}">
                    <option value="">Todos os usuários</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>

                <select name="action" class="{{ $filterClasses }}">
                    <option value="">Todas as ações</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action->value }}" @selected(($filters['action'] ?? '') === $action->value)>{{ $action->label() }}</option>
                    @endforeach
                </select>

                <input type="date" name="de" value="{{ $filters['de'] ?? '' }}" class="{{ $filterClasses }}" title="De">
                <input type="date" name="ate" value="{{ $filters['ate'] ?? '' }}" class="{{ $filterClasses }}" title="Até">

                <div class="flex items-center justify-between gap-4 sm:col-span-5">
                    <x-input-error :messages="$errors->get('ate')" />
                    <div class="ms-auto flex items-center gap-4">
                        <a href="{{ route('audit.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Limpar filtros</a>
                        <x-primary-button>Filtrar</x-primary-button>
                    </div>
                </div>
            </form>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-6 py-3 font-medium">Data e hora</th>
                            <th class="px-6 py-3 font-medium">Usuário</th>
                            <th class="px-6 py-3 font-medium">Ação</th>
                            <th class="px-6 py-3 font-medium">Descrição</th>
                            <th class="px-6 py-3 font-medium">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($logs as $log)
                            <tr class="align-top">
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                                <td class="px-6 py-4 text-gray-900">{{ $log->actorName() }}</td>
                                <td class="px-6 py-4">
                                    <span class="whitespace-nowrap rounded-full px-2 py-1 text-xs {{ $actionClasses($log->action) }}">
                                        {{ $log->action->label() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-900">
                                    @if ($url = $log->subjectUrl())
                                        <a href="{{ $url }}" class="text-indigo-600 hover:text-indigo-900">{{ $log->description }}</a>
                                    @else
                                        {{ $log->description }}
                                    @endif

                                    @if ($log->changes)
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-xs text-gray-500 hover:text-gray-700">Ver detalhes</summary>
                                            @include('audit._changes', ['changes' => $log->changes])
                                        </details>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">Nenhum registro encontrado com esses filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $logs->links() }}</div>
        </div>
    </div>
</x-app-layout>