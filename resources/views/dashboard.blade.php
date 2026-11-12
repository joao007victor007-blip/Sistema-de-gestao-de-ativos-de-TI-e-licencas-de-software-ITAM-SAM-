<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard') }}</h2>
    </x-slot>

    @php
        $pendingCount = $pendingTerms->count() + $terminatedWithAssets->count();
        $attentionCount = $assets['manutencao'] + $assets['extraviados'];
        $licenseAlertCount = $expiringLicenses->count() + $expiredLicensesCount;
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">


        
            @if ($backupIsLate)
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    O último backup do banco tem mais de 48 horas, ou não existe.
                    <a href="{{ route('backups.index') }}" class="underline">Verificar os backups</a>
                </div>
            @endif

            {{-- Indicadores --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('assets.index') }}" class="block rounded-lg bg-white p-5 shadow-sm hover:ring-2 hover:ring-indigo-200">
                    <p class="text-sm text-gray-500">Ativos</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $assets['total'] }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $assets['emUso'] }} em uso, {{ $assets['disponiveis'] }} disponíveis</p>
                </a>

                <a href="{{ route('assets.index', ['status' => 'manutencao']) }}"
                   class="block rounded-lg bg-white p-5 shadow-sm hover:ring-2 hover:ring-indigo-200 {{ $attentionCount > 0 ? 'border-l-4 border-yellow-400' : '' }}">
                    <p class="text-sm text-gray-500">Exigem atenção</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $attentionCount }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $assets['manutencao'] }} em manutenção, {{ $assets['extraviados'] }} extraviados</p>
                </a>

                <a href="{{ route('licenses.index', ['situacao' => $expiredLicensesCount > 0 ? 'vencidas' : 'vencendo']) }}"
                   class="block rounded-lg bg-white p-5 shadow-sm hover:ring-2 hover:ring-indigo-200 {{ $expiredLicensesCount > 0 ? 'border-l-4 border-red-500' : ($licenseAlertCount > 0 ? 'border-l-4 border-yellow-400' : '') }}">
                    <p class="text-sm text-gray-500">Licenças com alerta</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $licenseAlertCount }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $expiringLicenses->count() }} vencem em breve, {{ $expiredLicensesCount }} vencidas</p>
                </a>

                <a href="{{ route('assets.index', ['termo' => 'pendente']) }}"
                   class="block rounded-lg bg-white p-5 shadow-sm hover:ring-2 hover:ring-indigo-200 {{ $pendingCount > 0 ? 'border-l-4 border-yellow-400' : '' }}">
                    <p class="text-sm text-gray-500">Pendências</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-900">{{ $pendingCount }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $pendingTerms->count() }} termos, {{ $terminatedWithAssets->count() }} desligados com equipamento</p>
                </a>
            </div>

            {{-- Listas de ação --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-medium text-gray-900">Licenças que vencem em breve</h3>
                        <a href="{{ route('licenses.index', ['situacao' => 'vencendo']) }}" class="text-sm text-indigo-600 hover:text-indigo-900">Ver todas</a>
                    </div>
                    @forelse ($expiringLicenses->take(5) as $license)
                        <div class="mt-3 flex items-center justify-between gap-4 text-sm">
                            <a href="{{ route('licenses.show', $license) }}" class="text-gray-900 hover:text-indigo-700">
                                {{ $license->name }}
                                <span class="block text-xs text-gray-500">{{ $license->software->name }}</span>
                            </a>
                            <span class="whitespace-nowrap text-gray-600">{{ $license->expires_at->format('d/m/Y') }}</span>
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-gray-600">Nenhuma licença vencendo nos próximos dias.</p>
                    @endforelse
                    @if ($expiredLicensesCount > 0)
                        <p class="mt-4 rounded-md bg-red-50 p-3 text-xs text-red-800">
                            Há {{ $expiredLicensesCount }} licença(s) vencida(s).
                            <a href="{{ route('licenses.index', ['situacao' => 'vencidas']) }}" class="underline">Ver vencidas</a>
                        </p>
                    @endif
                </section>

                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-medium text-gray-900">Termos de responsabilidade pendentes</h3>
                        <a href="{{ route('assets.index', ['termo' => 'pendente']) }}" class="text-sm text-indigo-600 hover:text-indigo-900">Ver todos</a>
                    </div>
                    @forelse ($pendingTerms->take(5) as $assignment)
                        <div class="mt-3 flex items-center justify-between gap-4 text-sm">
                            <a href="{{ route('assets.show', $assignment->asset) }}" class="text-gray-900 hover:text-indigo-700">
                                {{ $assignment->employee->name }}
                                <span class="block text-xs text-gray-500">{{ $assignment->asset->displayIdentifier() }}, {{ $assignment->asset->displayName() }}</span>
                            </a>
                            <span class="whitespace-nowrap text-gray-600">desde {{ $assignment->checked_out_at->format('d/m/Y') }}</span>
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-gray-600">Todos os termos de equipamentos em uso estão assinados.</p>
                    @endforelse
                </section>

                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-medium text-gray-900">Desligados com equipamentos pendentes</h3>
                        <a href="{{ route('employees.index', ['status' => 'pendencias']) }}" class="text-sm text-indigo-600 hover:text-indigo-900">Ver todos</a>
                    </div>
                    @forelse ($terminatedWithAssets->take(5) as $employee)
                        <div class="mt-3 flex items-center justify-between gap-4 text-sm">
                            <a href="{{ route('employees.show', $employee) }}" class="text-gray-900 hover:text-indigo-700">
                                {{ $employee->name }}
                                <span class="block text-xs text-gray-500">desligado em {{ $employee->terminated_at?->format('d/m/Y') }}</span>
                            </a>
                            <span class="whitespace-nowrap rounded-full bg-red-100 px-2 py-1 text-xs text-red-800">
                                {{ $employee->open_asset_assignments_count }} equip.
                            </span>
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-gray-600">Nenhum desligado com equipamento a devolver.</p>
                    @endforelse
                </section>

                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-medium text-gray-900">Licenças quase esgotadas</h3>
                        <a href="{{ route('licenses.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">Ver todas</a>
                    </div>
                    @forelse ($licensesNearLimit->take(5) as $license)
                        <div class="mt-3 flex items-center justify-between gap-4 text-sm">
                            <a href="{{ route('licenses.show', $license) }}" class="text-gray-900 hover:text-indigo-700">
                                {{ $license->name }}
                                <span class="block text-xs text-gray-500">{{ $license->software->name }}</span>
                            </a>
                            <span class="whitespace-nowrap text-gray-600">{{ $license->active_assignments_count }} de {{ $license->seats }} em uso</span>
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-gray-600">Todas as licenças têm vagas sobrando.</p>
                    @endforelse
                </section>
            </div>


                        {{-- Renovações automáticas --}}
            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-medium text-gray-900">Renovações automáticas nos próximos 30 dias</h3>
                    <a href="{{ route('licenses.index', ['situacao' => 'renovando']) }}" class="text-sm text-indigo-600 hover:text-indigo-900">Ver todas</a>
                </div>
                @forelse ($upcomingRenewals->take(8) as $license)
                    <div class="mt-3 flex items-center justify-between gap-4 text-sm">
                        <a href="{{ route('licenses.show', $license) }}" class="text-gray-900 hover:text-indigo-700">
                            {{ $license->name }}
                            <span class="block text-xs text-gray-500">{{ $license->software->name }}</span>
                        </a>
                        <span class="whitespace-nowrap text-right text-gray-600">
                            {{ $license->nextRenewalDate()->format('d/m/Y') }}
                            @if ($license->cost !== null)
                                <span class="block text-xs text-gray-500">R$ {{ number_format((float) $license->cost, 2, ',', '.') }}</span>
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-gray-600">Nenhuma renovação automática nos próximos 30 dias.</p>
                @endforelse
            </section>

            {{-- Visão geral --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <section class="rounded-lg bg-white p-6 shadow-sm lg:col-span-2">
                    <h3 class="text-base font-medium text-gray-900">Últimas movimentações</h3>
                    @forelse ($recentMovements as $movement)
                        <div class="mt-3 flex items-center justify-between gap-4 text-sm">
                            <a href="{{ route('assets.show', $movement->asset) }}" class="text-gray-900 hover:text-indigo-700">
                                {{ $movement->isOpen() ? 'Entrega' : 'Devolução' }} de {{ $movement->asset->displayIdentifier() }}
                                <span class="block text-xs text-gray-500">{{ $movement->employee->name }}, {{ $movement->asset->displayName() }}</span>
                            </a>
                            <span class="whitespace-nowrap text-gray-600">
                                {{ ($movement->isOpen() ? $movement->checked_out_at : $movement->checked_in_at)->format('d/m/Y') }}
                            </span>
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-gray-600">Nenhuma movimentação registrada ainda.</p>
                    @endforelse
                </section>

                <section class="rounded-lg bg-white p-6 shadow-sm">
                    <h3 class="text-base font-medium text-gray-900">Ativos por categoria</h3>
                    @forelse ($assetsByCategory as $category)
                        <a href="{{ route('assets.index', ['category_id' => $category->id]) }}"
                           class="mt-3 flex items-center justify-between text-sm text-gray-900 hover:text-indigo-700">
                            <span>{{ $category->name }}</span>
                            <span class="text-gray-600">{{ $category->assets_count }}</span>
                        </a>
                    @empty
                        <p class="mt-3 text-sm text-gray-600">Nenhum ativo cadastrado ainda.</p>
                    @endforelse
                </section>
            </div>
        </div>
    </div>
</x-app-layout>