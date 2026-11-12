<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $asset->displayIdentifier() }}</h2>
                <p class="text-sm text-gray-500">{{ $asset->displayName() }}</p>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('assets.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Voltar para a lista</a>
                @can('manage-inventory')
                    <a href="{{ route('assets.edit', $asset) }}"
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
                    <h3 class="text-lg font-medium text-gray-900">Dados do equipamento</h3>
                    <span class="rounded-full px-3 py-1 text-sm {{ $asset->status->badgeClasses() }}">{{ $asset->status->label() }}</span>
                </div>

                <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">Nº de patrimônio</dt><dd class="text-gray-900">{{ $asset->asset_tag ?? 'Sem etiqueta' }}</dd></div>
                    <div><dt class="text-gray-500">Código interno</dt><dd class="text-gray-900">{{ $asset->code }}</dd></div>
                    <div><dt class="text-gray-500">Categoria</dt><dd class="text-gray-900">{{ $asset->category->name }}</dd></div>
                    <div><dt class="text-gray-500">Fabricante</dt><dd class="text-gray-900">{{ $asset->manufacturer?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Modelo</dt><dd class="text-gray-900">{{ $asset->model ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Número de série</dt><dd class="text-gray-900">{{ $asset->serial_number ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Local</dt><dd class="text-gray-900">{{ $asset->location?->name ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-gray-500">Garantia</dt>
                        <dd class="text-gray-900">
                            @if ($asset->warranty_until)
                                Até {{ $asset->warranty_until->format('d/m/Y') }}
                                <span class="ms-1 rounded-full px-2 py-0.5 text-xs {{ $asset->isUnderWarranty() ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                                    {{ $asset->isUnderWarranty() ? 'Vigente' : 'Vencida' }}
                                </span>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div><dt class="text-gray-500">Data de compra</dt><dd class="text-gray-900">{{ $asset->purchase_date?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-gray-500">Valor de compra</dt>
                        <dd class="text-gray-900">{{ $asset->purchase_price !== null ? 'R$ ' . number_format((float) $asset->purchase_price, 2, ',', '.') : '—' }}</dd>
                    </div>
                    <div><dt class="text-gray-500">Nota fiscal</dt><dd class="text-gray-900">{{ $asset->invoice_number ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Fornecedor</dt><dd class="text-gray-900">{{ $asset->supplier ?? '—' }}</dd></div>
                </dl>

                @if ($asset->description)
                    <div class="mt-6 text-sm">
                        <p class="text-gray-500">Descrição</p>
                        <p class="mt-1 whitespace-pre-line text-gray-900">{{ $asset->description }}</p>
                    </div>
                @endif

                @if ($asset->notes)
                    <div class="mt-6 text-sm">
                        <p class="text-gray-500">Observações</p>
                        <p class="mt-1 whitespace-pre-line text-gray-900">{{ $asset->notes }}</p>
                    </div>
                @endif
            </section>

            @php
                $currentAssignment = $asset->assignments->first(fn ($assignment) => $assignment->isOpen());
            @endphp

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">Histórico de uso</h3>
                    @can('move-assets')
                        @if ($asset->status === \App\Enums\AssetStatus::Disponivel)
                            <a href="{{ route('assets.checkout', $asset) }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                Entregar a colaborador
                            </a>
                        @elseif ($asset->status === \App\Enums\AssetStatus::EmUso)
                            <a href="{{ route('assets.checkin', $asset) }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                Registrar devolução
                            </a>
                        @endif
                    @endcan
                </div>

                @if ($currentAssignment)
                    <div class="mt-4 rounded-md bg-blue-50 p-4 text-sm text-blue-800">
                        Em uso por <strong>{{ $currentAssignment->employee->name }}</strong>
                        desde {{ $currentAssignment->checked_out_at->format('d/m/Y') }}.
                    </div>
                @endif

                <x-input-error :messages="$errors->get('term_signed_at')" class="mt-4" />

                @if ($asset->assignments->isEmpty())
                    <p class="mt-4 text-sm text-gray-600">Este equipamento ainda não foi entregue a nenhum colaborador.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="text-left text-gray-600">
                                <tr>
                                    <th class="py-2 pe-4 font-medium">Colaborador</th>
                                    <th class="py-2 pe-4 font-medium">Entrega</th>
                                    <th class="py-2 pe-4 font-medium">Devolução</th>
                                    <th class="py-2 font-medium">Termo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($asset->assignments as $assignment)
                                    <tr class="align-top">
                                        <td class="py-3 pe-4 text-gray-900">
                                            <a href="{{ route('employees.show', $assignment->employee) }}" class="text-indigo-600 hover:text-indigo-900">{{ $assignment->employee->name }}</a>
                                            @if ($assignment->notes)
                                                <div class="text-xs text-gray-500">{{ $assignment->notes }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3 pe-4 text-gray-600">
                                            {{ $assignment->checked_out_at->format('d/m/Y') }}
                                            <div class="text-xs text-gray-500">por {{ $assignment->checkedOutBy->name }}</div>
                                            @if ($assignment->checkout_condition)
                                                <div class="text-xs text-gray-500">{{ $assignment->checkout_condition }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3 pe-4 text-gray-600">
                                            @if ($assignment->isOpen())
                                                <span class="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-800">Em uso</span>
                                            @else
                                                {{ $assignment->checked_in_at->format('d/m/Y') }}
                                                <div class="text-xs text-gray-500">por {{ $assignment->checkedInBy?->name }}</div>
                                                @if ($assignment->checkin_condition)
                                                    <div class="text-xs text-gray-500">{{ $assignment->checkin_condition }}</div>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="py-3 text-gray-600">
                                            @if ($assignment->term_signed)
                                                Assinado em {{ $assignment->term_signed_at->format('d/m/Y') }}
                                            @else
                                                <span class="rounded-full bg-yellow-100 px-2 py-1 text-xs text-yellow-800">Pendente</span>
                                                @can('move-assets')
                                                    <form method="POST" action="{{ route('assignments.sign-term', $assignment) }}"
                                                          class="mt-2 flex items-center gap-2">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="date" name="term_signed_at" value="{{ today()->format('Y-m-d') }}"
                                                               class="rounded-md border-gray-300 text-xs shadow-sm">
                                                        <button type="submit" class="text-xs text-indigo-600 underline hover:text-indigo-900">
                                                            Registrar assinatura
                                                        </button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

                        <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">Licenças neste equipamento</h3>

                @if ($asset->licenseAssignments->isEmpty())
                    <p class="mt-2 text-sm text-gray-600">Nenhuma licença atribuída a este equipamento.</p>
                @else
                    <ul class="mt-4 divide-y divide-gray-100 text-sm">
                        @foreach ($asset->licenseAssignments as $licenseAssignment)
                            <li class="flex items-center justify-between py-2">
                                <a href="{{ route('licenses.show', $licenseAssignment->license) }}" class="text-indigo-600 hover:text-indigo-900">
                                    {{ $licenseAssignment->license->name }}
                                </a>
                                <span class="text-gray-500">
                                    @if ($licenseAssignment->employee)
                                        Usada por {{ $licenseAssignment->employee->name }},
                                    @endif
                                    desde {{ $licenseAssignment->assigned_at->format('d/m/Y') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($asset->status === \App\Enums\AssetStatus::Descartado)
                <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900">Descarte</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Descartado em {{ $asset->disposed_at?->format('d/m/Y') }}. Motivo: {{ $asset->disposal_reason }}
                    </p>
                </section>
            @else
                @can('dispose-assets')
                    <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900">Registrar descarte</h3>
                        @if ($asset->status === \App\Enums\AssetStatus::EmUso)
                            <p class="mt-2 text-sm text-gray-600">Este equipamento está em uso. Registre a devolução antes de descartá-lo.</p>
                        @else
                            <p class="mt-2 text-sm text-gray-600">
                                Use quando o equipamento for doado, vendido, reciclado ou não tiver mais conserto.
                                O registro é definitivo, e o histórico do equipamento é mantido.
                            </p>
                            <form method="POST" action="{{ route('assets.dispose', $asset) }}" class="mt-4 space-y-4"
                                  onsubmit="return confirm('O descarte é definitivo. Confirma?')">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <x-input-label for="disposal_reason" value="Motivo do descarte" />
                                    <x-text-input id="disposal_reason" name="disposal_reason" type="text" class="mt-1 block w-full"
                                                  :value="old('disposal_reason')" required />
                                    <x-input-error :messages="$errors->get('disposal_reason')" class="mt-2" />
                                </div>
                                <x-danger-button>Registrar descarte</x-danger-button>
                            </form>
                        @endif
                    </section>
                @endcan
            @endif

<x-attachments :item="$asset" :store-route="route('assets.attachments.store', $asset)" />

 <x-audit-trail :subject="$asset" />

        </div>
    </div>
</x-app-layout>