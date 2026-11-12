<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Registrar devolução</h2>
                <p class="text-sm text-gray-500">{{ $asset->displayIdentifier() }}, {{ $asset->displayName() }}</p>
            </div>
            <a href="{{ route('assets.show', $asset) }}" class="text-sm text-gray-600 underline hover:text-gray-900">Cancelar</a>
        </div>
    </x-slot>

    @php
        $fieldClasses = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm';
    @endphp

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            <div class="rounded-md bg-blue-50 p-4 text-sm text-blue-800">
                Em uso por <strong>{{ $assignment->employee->name }}</strong>
                desde {{ $assignment->checked_out_at->format('d/m/Y') }}.
                @if ($assignment->checkout_condition)
                    Condição na entrega: {{ $assignment->checkout_condition }}.
                @endif
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('assets.checkin.store', $asset) }}" class="space-y-6">
                    @csrf

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="checked_in_at" value="Data da devolução" />
                            <x-text-input id="checked_in_at" name="checked_in_at" type="date" class="mt-1 block w-full"
                                          :value="old('checked_in_at', today()->format('Y-m-d'))" required />
                            <x-input-error :messages="$errors->get('checked_in_at')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="status" value="Situação após a devolução" />
                            <select id="status" name="status" required class="{{ $fieldClasses }}">
                                <option value="disponivel" @selected(old('status', 'disponivel') === 'disponivel')>Disponível</option>
                                <option value="manutencao" @selected(old('status') === 'manutencao')>Em manutenção</option>
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="location_id" value="Local de destino" />
                        <select id="location_id" name="location_id" class="{{ $fieldClasses }}">
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) old('location_id', $asset->location_id) === (string) $location->id)>
                                    {{ $location->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Para onde o equipamento vai agora (por exemplo, o almoxarifado ou o escritório da TI).</p>
                        <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="checkin_condition" value="Condição na devolução" />
                        <x-text-input id="checkin_condition" name="checkin_condition" type="text" class="mt-1 block w-full"
                                      :value="old('checkin_condition')" />
                        <p class="mt-1 text-xs text-gray-500">Exemplos: "Perfeito estado"; "Sem carregador"; "Tela trincada".</p>
                        <x-input-error :messages="$errors->get('checkin_condition')" class="mt-2" />
                    </div>

                    <div class="flex justify-end border-t border-gray-200 pt-6">
                        <x-primary-button>Registrar devolução</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>