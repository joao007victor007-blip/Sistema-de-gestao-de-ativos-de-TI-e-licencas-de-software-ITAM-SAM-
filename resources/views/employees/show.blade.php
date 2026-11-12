@php
    $openAssets = $employee->assetAssignments->filter(fn ($assignment) => $assignment->isOpen());
    $pastAssets = $employee->assetAssignments->reject(fn ($assignment) => $assignment->isOpen());
    $activeLicenses = $employee->licenseAssignments->filter(fn ($assignment) => $assignment->isActive());
    $pastLicenses = $employee->licenseAssignments->reject(fn ($assignment) => $assignment->isActive());
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $employee->name }}</h2>
                <p class="text-sm text-gray-500">
                    {{ collect([$employee->job_title, $employee->department?->name])->filter()->implode(', ') ?: 'Colaborador' }}
                </p>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('employees.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Voltar para a lista</a>
                @can('manage-inventory')
                    <a href="{{ route('employees.edit', $employee) }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        Editar
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @if (! $employee->is_active)
                <div class="rounded-md p-4 text-sm {{ $openAssets->isNotEmpty() ? 'bg-red-50 text-red-800' : 'bg-gray-100 text-gray-700' }}">
                    Colaborador desligado em {{ $employee->terminated_at?->format('d/m/Y') }}.
                    @if ($openAssets->isNotEmpty())
                        Há equipamentos ainda não devolvidos: registre a devolução de cada um quando chegarem.
                    @endif
                </div>
            @endif

            {{-- Dados --}}
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">Dados do colaborador</h3>
                <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Matrícula</dt><dd class="text-gray-900">{{ $employee->registration ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">E-mail</dt><dd class="text-gray-900">{{ $employee->email ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Cargo</dt><dd class="text-gray-900">{{ $employee->job_title ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Setor</dt><dd class="text-gray-900">{{ $employee->department?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Local de lotação</dt><dd class="text-gray-900">{{ $employee->location?->name ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-gray-500">Situação</dt>
                        <dd class="text-gray-900">{{ $employee->is_active ? 'Ativo' : 'Desligado' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Equipamentos em posse --}}
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">Equipamentos em posse</h3>

                @if ($openAssets->isEmpty())
                    <p class="mt-2 text-sm text-gray-600">Nenhum equipamento com este colaborador.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="text-left text-gray-600">
                                <tr>
                                    <th class="py-2 pe-4 font-medium">Identificação</th>
                                    <th class="py-2 pe-4 font-medium">Equipamento</th>
                                    <th class="py-2 pe-4 font-medium">Desde</th>
                                    <th class="py-2 font-medium">Termo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($openAssets as $assignment)
                                    <tr>
                                        <td class="py-3 pe-4">
                                            <a href="{{ route('assets.show', $assignment->asset) }}" class="text-indigo-600 hover:text-indigo-900">
                                                {{ $assignment->asset->displayIdentifier() }}
                                            </a>
                                        </td>
                                        <td class="py-3 pe-4 text-gray-900">{{ $assignment->asset->displayName() }}</td>
                                        <td class="py-3 pe-4 text-gray-600">{{ $assignment->checked_out_at->format('d/m/Y') }}</td>
                                        <td class="py-3 text-gray-600">
                                            @if ($assignment->term_signed)
                                                Assinado
                                            @else
                                                <span class="rounded-full bg-yellow-100 px-2 py-1 text-xs text-yellow-800">Pendente</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Licenças em uso --}}
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">Licenças em uso</h3>

                @if ($activeLicenses->isEmpty())
                    <p class="mt-2 text-sm text-gray-600">Nenhuma licença atribuída a este colaborador.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="text-left text-gray-600">
                                <tr>
                                    <th class="py-2 pe-4 font-medium">Licença</th>
                                    <th class="py-2 pe-4 font-medium">Software</th>
                                    <th class="py-2 pe-4 font-medium">Equipamento</th>
                                    <th class="py-2 font-medium">Desde</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($activeLicenses as $assignment)
                                    <tr>
                                        <td class="py-3 pe-4">
                                            <a href="{{ route('licenses.show', $assignment->license) }}" class="text-indigo-600 hover:text-indigo-900">
                                                {{ $assignment->license->name }}
                                            </a>
                                        </td>
                                        <td class="py-3 pe-4 text-gray-600">{{ $assignment->license->software->name }}</td>
                                        <td class="py-3 pe-4 text-gray-600">{{ $assignment->asset?->displayIdentifier() ?? '—' }}</td>
                                        <td class="py-3 text-gray-600">{{ $assignment->assigned_at->format('d/m/Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Histórico --}}
            @if ($pastAssets->isNotEmpty() || $pastLicenses->isNotEmpty())
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <details>
                        <summary class="cursor-pointer text-lg font-medium text-gray-900">
                            Histórico ({{ $pastAssets->count() + $pastLicenses->count() }} registros)
                        </summary>

                        @if ($pastAssets->isNotEmpty())
                            <h4 class="mt-4 text-sm font-medium text-gray-700">Equipamentos devolvidos</h4>
                            <ul class="mt-2 divide-y divide-gray-100 text-sm">
                                @foreach ($pastAssets as $assignment)
                                    <li class="flex justify-between py-2">
                                        <a href="{{ route('assets.show', $assignment->asset) }}" class="text-indigo-600 hover:text-indigo-900">
                                            {{ $assignment->asset->displayIdentifier() }}, {{ $assignment->asset->displayName() }}
                                        </a>
                                        <span class="text-gray-500">
                                            {{ $assignment->checked_out_at->format('d/m/Y') }} a {{ $assignment->checked_in_at->format('d/m/Y') }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($pastLicenses->isNotEmpty())
                            <h4 class="mt-4 text-sm font-medium text-gray-700">Licenças liberadas</h4>
                            <ul class="mt-2 divide-y divide-gray-100 text-sm">
                                @foreach ($pastLicenses as $assignment)
                                    <li class="flex justify-between py-2">
                                        <span class="text-gray-900">{{ $assignment->license->name }}</span>
                                        <span class="text-gray-500">
                                            {{ $assignment->assigned_at->format('d/m/Y') }} a {{ $assignment->revoked_at->format('d/m/Y') }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </details>
                </section>
            @endif

             <x-audit-trail :subject="$employee" />
             
        </div>
    </div>
</x-app-layout>