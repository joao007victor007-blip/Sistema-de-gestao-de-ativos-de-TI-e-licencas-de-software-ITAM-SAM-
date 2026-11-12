<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Entregar equipamento</h2>
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

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('assets.checkout.store', $asset) }}" class="space-y-6"
                      x-data="{
                          employeeLocations: @js($employees->pluck('location_id', 'id')),
                          location: @js((string) old('location_id', '')),
                          pickEmployee(id) {
                              const loc = this.employeeLocations[id];
                              if (loc) this.location = String(loc);
                          }
                      }">
                    @csrf

                    <div>
                        <x-input-label for="employee_id" value="Colaborador" />
                        <select id="employee_id" name="employee_id" required class="{{ $fieldClasses }}"
                                @change="pickEmployee($event.target.value)">
                            <option value="">Selecione</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>
                                    {{ $employee->name }}{{ $employee->registration ? ' (mat. ' . $employee->registration . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Só aparecem colaboradores ativos.</p>
                        <x-input-error :messages="$errors->get('employee_id')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="location_id" value="Local de uso" />
                            <select id="location_id" name="location_id" x-model="location" class="{{ $fieldClasses }}">
                                <option value="">Lotação do colaborador</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Preenchido com a lotação do colaborador. Altere se for usar em outra obra.</p>
                            <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="checked_out_at" value="Data da entrega" />
                            <x-text-input id="checked_out_at" name="checked_out_at" type="date" class="mt-1 block w-full"
                                          :value="old('checked_out_at', today()->format('Y-m-d'))" required />
                            <p class="mt-1 text-xs text-gray-500">Para equipamentos que já estão com a pessoa, informe a data real.</p>
                            <x-input-error :messages="$errors->get('checked_out_at')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="checkout_condition" value="Condição na entrega" />
                        <x-text-input id="checkout_condition" name="checkout_condition" type="text" class="mt-1 block w-full"
                                      :value="old('checkout_condition')" />
                        <p class="mt-1 text-xs text-gray-500">Exemplos: "Novo, com carregador e mochila"; "Pequeno risco na tampa".</p>
                        <x-input-error :messages="$errors->get('checkout_condition')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notes" value="Observações" />
                        <textarea id="notes" name="notes" rows="2" class="{{ $fieldClasses }}">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <label for="term_signed" class="inline-flex items-center">
                        <input id="term_signed" type="checkbox" name="term_signed" value="1"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                               @checked(old('term_signed'))>
                        <span class="ms-2 text-sm text-gray-700">O colaborador assinou o termo de responsabilidade</span>
                    </label>

                    <div class="flex justify-end border-t border-gray-200 pt-6">
                        <x-primary-button>Registrar entrega</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>