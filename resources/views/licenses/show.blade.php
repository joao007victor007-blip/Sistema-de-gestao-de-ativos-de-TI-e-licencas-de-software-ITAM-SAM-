<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $license->name }}</h2>
                <p class="text-sm text-gray-500">{{ $license->software->name }}</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('licenses.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Voltar para a lista</a>
                @can('manage-inventory')
                    <a href="{{ route('licenses.edit', $license) }}"
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
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">Dados da licença</h3>
                    <x-license-expiration-badge :license="$license" class="text-sm" />
                </div>

                <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Tipo</dt><dd class="text-gray-900">{{ $license->license_type->label() }}</dd></div>
                    <div><dt class="text-gray-500">Forma de atribuição</dt><dd class="text-gray-900">{{ $license->assignment_type->label() }}</dd></div>
                    <div><dt class="text-gray-500">Assentos contratados</dt><dd class="text-gray-900">{{ $license->seats }}</dd></div>
                    <div><dt class="text-gray-500">Aviso</dt><dd class="text-gray-900">{{ $license->alert_days }} dias antes</dd></div>
                    <div><dt class="text-gray-500">Data de aquisição</dt><dd class="text-gray-900">{{ $license->purchase_date?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ $license->isAutoRenewing() ? 'Próxima renovação' : 'Vencimento' }}</dt><dd class="text-gray-900">{{ $license->dateLabel() ?? 'Sem data' }}</dd></div>
                    <div><dt class="text-gray-500">Periodicidade</dt><dd class="text-gray-900">{{ $license->billing_cycle?->label() ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Renovação automática</dt><dd class="text-gray-900">{{ $license->auto_renew ? 'Sim' : 'Não' }}</dd></div>
                    <div>
                        <dt class="text-gray-500">Valor</dt>
                        <dd class="text-gray-900">{{ $license->cost !== null ? 'R$ ' . number_format((float) $license->cost, 2, ',', '.') : '—' }}</dd>
                    </div>
                    <div><dt class="text-gray-500">Fornecedor</dt><dd class="text-gray-900">{{ $license->supplier ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Nota fiscal ou pedido</dt><dd class="text-gray-900">{{ $license->invoice_number ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Conta administradora</dt><dd class="text-gray-900">{{ $license->admin_account_email ?? '—' }}</dd></div>
                </dl>

                @if ($license->notes)
                    <div class="mt-6 text-sm">
                        <p class="text-gray-500">Observações</p>
                        <p class="mt-1 whitespace-pre-line text-gray-900">{{ $license->notes }}</p>
                    </div>
                @endif
            </section>

            {{-- Chave de ativação --}}
            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">Chave de ativação</h3>

                @if (! $license->hasKey())
                    <p class="mt-2 text-sm text-gray-600">Nenhuma chave cadastrada para esta licença.</p>
                @elseif ($revealedKey !== null)
                    <p class="mt-4 break-all rounded-md bg-gray-100 p-4 font-mono text-sm text-gray-900">{{ $revealedKey }}</p>
                    <p class="mt-2 text-xs text-gray-500">Feche ou atualize a página quando terminar de usar a chave.</p>
                @else
                    <p class="mt-2 font-mono text-sm text-gray-500">••••• ••••• ••••• •••••</p>
                    @can('reveal-license-keys')
                        <form method="POST" action="{{ route('licenses.reveal-key', $license) }}" class="mt-4">
                            @csrf
                            <x-secondary-button type="submit">Mostrar chave</x-secondary-button>
                        </form>
                    @else
                        <p class="mt-2 text-xs text-gray-500">O seu perfil não tem permissão para ver chaves.</p>
                    @endcan
                @endif
            </section>

                                    @php
                $activeAssignments = $license->assignments->filter(fn ($assignment) => $assignment->isActive());
                $pastAssignments = $license->assignments->reject(fn ($assignment) => $assignment->isActive());
            @endphp

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">Uso das licenças</h3>
                    @can('move-assets')
                        @if (! $license->assignmentBlockReason())
                            <a href="{{ route('licenses.assign', $license) }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                Atribuir licença
                            </a>
                        @endif
                    @endcan
                </div>

                @if (! $license->assignment_type->usesSeats())
                    <p class="mt-2 text-sm text-gray-600">Licença corporativa: uso liberado para toda a empresa, sem atribuição individual.</p>
                @else
                    @php
                        $used = $activeAssignments->count();
                        $percent = min(100, (int) round($used / max(1, $license->seats) * 100));
                    @endphp

                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-gray-700">
                            <span>{{ $used }} de {{ $license->seats }} em uso</span>
                            <span>{{ max(0, $license->seats - $used) }} disponíveis</span>
                        </div>
                        <div class="mt-2 h-2 w-full rounded-full bg-gray-200">
                            <div class="h-2 rounded-full {{ $used >= $license->seats ? 'bg-red-500' : 'bg-indigo-500' }}"
                                 style="width: {{ $percent }}%"></div>
                        </div>
                    </div>

                    @if ($reason = $license->assignmentBlockReason())
                        <p class="mt-3 text-xs text-gray-500">{{ $reason }}</p>
                    @endif

                    <x-input-error :messages="$errors->get('revoked_at')" class="mt-4" />

                    @if ($activeAssignments->isEmpty())
                        <p class="mt-4 text-sm text-gray-600">Nenhuma licença atribuída no momento.</p>
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="text-left text-gray-600">
                                    <tr>
                                        <th class="py-2 pe-4 font-medium">Colaborador</th>
                                        <th class="py-2 pe-4 font-medium">Equipamento</th>
                                        <th class="py-2 pe-4 font-medium">Desde</th>
                                        @can('move-assets')
                                            <th class="py-2 font-medium text-right">Liberar</th>
                                        @endcan
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($activeAssignments as $assignment)
                                        <tr class="align-top">
                                            <td class="py-3 pe-4 text-gray-900">
                                                {{ $assignment->employee?->name ?? '—' }}
                                                @if ($assignment->notes)
                                                    <div class="text-xs text-gray-500">{{ $assignment->notes }}</div>
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4 text-gray-600">
                                                @if ($assignment->asset)
                                                    <a href="{{ route('assets.show', $assignment->asset) }}" class="text-indigo-600 hover:text-indigo-900">
                                                        {{ $assignment->asset->displayIdentifier() }}
                                                    </a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4 text-gray-600">
                                                {{ $assignment->assigned_at->format('d/m/Y') }}
                                                <div class="text-xs text-gray-500">por {{ $assignment->assignedBy->name }}</div>
                                            </td>
                                            @can('move-assets')
                                                <td class="py-3 text-right">
                                                    <form method="POST" action="{{ route('license-assignments.revoke', $assignment) }}"
                                                          class="inline-flex items-center gap-2"
                                                          onsubmit="return confirm('Liberar esta licença?')">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="date" name="revoked_at" value="{{ today()->format('Y-m-d') }}"
                                                               class="rounded-md border-gray-300 text-xs shadow-sm">
                                                        <button type="submit" class="text-xs text-indigo-600 underline hover:text-indigo-900">Liberar</button>
                                                    </form>
                                                </td>
                                            @endcan
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($pastAssignments->isNotEmpty())
                        <details class="mt-6">
                            <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-900">
                                Histórico de liberações ({{ $pastAssignments->count() }})
                            </summary>
                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="text-left text-gray-600">
                                        <tr>
                                            <th class="py-2 pe-4 font-medium">Colaborador</th>
                                            <th class="py-2 pe-4 font-medium">Equipamento</th>
                                            <th class="py-2 font-medium">Período</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($pastAssignments as $assignment)
                                            <tr>
                                                <td class="py-2 pe-4 text-gray-900">{{ $assignment->employee?->name ?? '—' }}</td>
                                                <td class="py-2 pe-4 text-gray-600">{{ $assignment->asset?->displayIdentifier() ?? '—' }}</td>
                                                <td class="py-2 text-gray-600">
                                                    {{ $assignment->assigned_at->format('d/m/Y') }} a {{ $assignment->revoked_at->format('d/m/Y') }}
                                                    <div class="text-xs text-gray-500">liberada por {{ $assignment->revokedBy?->name }}</div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    @endif
                @endif
            </section>

            @if ($license->isRetired())
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900">Baixa</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Baixada em {{ $license->retired_at->format('d/m/Y') }}. Motivo: {{ $license->retirement_reason }}
                    </p>
                </section>
            @else
                @can('dispose-assets')
                    <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900">Registrar baixa</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            Use quando o contrato for cancelado ou não for renovado. O registro é definitivo, e o histórico é mantido.
                        </p>
                        <form method="POST" action="{{ route('licenses.retire', $license) }}" class="mt-4 space-y-4"
                              onsubmit="return confirm('A baixa é definitiva. Confirma?')">
                            @csrf
                            @method('PATCH')
                            <div>
                                <x-input-label for="retirement_reason" value="Motivo da baixa" />
                                <x-text-input id="retirement_reason" name="retirement_reason" type="text" class="mt-1 block w-full"
                                              :value="old('retirement_reason')" required />
                                <x-input-error :messages="$errors->get('retirement_reason')" class="mt-2" />
                            </div>
                            <x-danger-button>Registrar baixa</x-danger-button>
                        </form>
                    </section>
                @endcan
            @endif

             <x-attachments :item="$license" :store-route="route('licenses.attachments.store', $license)" />

<x-audit-trail :subject="$license" />

        </div>
    </div>
</x-app-layout>