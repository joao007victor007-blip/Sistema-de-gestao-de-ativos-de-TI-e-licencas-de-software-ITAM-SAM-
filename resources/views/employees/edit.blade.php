<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Editar colaborador: {{ $employee->name }}</h2>
            <a href="{{ route('employees.show', $employee) }}" class="text-sm text-gray-600 underline hover:text-gray-900">Voltar para os detalhes</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('employees.update', $employee) }}" class="space-y-6">
                    @csrf
                    @method('PUT')
                    @include('employees._form')

                    <div class="flex justify-end">
                        <x-primary-button>Salvar alterações</x-primary-button>
                    </div>
                </form>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">Situação</h3>

                @if ($employee->is_active)
                    @php
                        $openAssets = $employee->openAssetAssignments;
                        $activeLicenses = $employee->activeLicenseAssignments;
                    @endphp

                    @if ($openAssets->isEmpty() && $activeLicenses->isEmpty())
                        <p class="mt-2 text-sm text-gray-600">
                            Nenhum equipamento ou licença com este colaborador. O desligamento pode ser registrado sem pendências.
                        </p>
                    @else
                        <div class="mt-4 rounded-md bg-yellow-50 p-4 text-sm text-yellow-800">
                            <p class="font-medium">Itens que estão com este colaborador:</p>
                            <ul class="mt-2 list-disc space-y-1 ps-5">
                                @foreach ($openAssets as $assignment)
                                    <li>
                                        Equipamento
                                        <a href="{{ route('assets.show', $assignment->asset) }}" class="underline">
                                            {{ $assignment->asset->displayIdentifier() }}
                                        </a>,
                                        {{ $assignment->asset->displayName() }}
                                    </li>
                                @endforeach
                                @foreach ($activeLicenses as $assignment)
                                    <li>Licença {{ $assignment->license->name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('employees.toggle-active', $employee) }}" class="mt-4 space-y-3"
                          onsubmit="return confirm('Confirma o registro do desligamento?')">
                        @csrf
                        @method('PATCH')

                        @if ($activeLicenses->isNotEmpty())
                            <p class="text-sm text-gray-700">
                                As licenças deste colaborador ({{ $activeLicenses->count() }}) serão liberadas automaticamente,
                                ficando disponíveis para outras pessoas.
                            </p>
                        @endif

                        @if ($openAssets->isNotEmpty())
                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="acknowledge_pending" value="1"
                                       class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span>
                                    Estou ciente de que há equipamentos com este colaborador ainda não devolvidos.
                                    A devolução de cada um será registrada quando chegar.
                                </span>
                            </label>
                            <x-input-error :messages="$errors->get('acknowledge_pending')" class="mt-1" />
                        @endif

                        <x-danger-button>Registrar desligamento</x-danger-button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-gray-600">
                        Colaborador desligado em {{ $employee->terminated_at?->format('d/m/Y') }}.
                        Reative em caso de recontratação ou de registro feito por engano.
                    </p>
                    <form method="POST" action="{{ route('employees.toggle-active', $employee) }}" class="mt-4">
                        @csrf
                        @method('PATCH')
                        <x-primary-button>Reativar colaborador</x-primary-button>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>