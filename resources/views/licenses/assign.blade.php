@php
    $perUser = $license->assignment_type === \App\Enums\LicenseAssignmentType::PerUser;
    $fieldClasses = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Atribuir licença</h2>
                <p class="text-sm text-gray-500">{{ $license->name }}, {{ $license->software->name }}</p>
            </div>
            <a href="{{ route('licenses.show', $license) }}" class="text-sm text-gray-600 underline hover:text-gray-900">Cancelar</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            <div class="rounded-md bg-blue-50 p-4 text-sm text-blue-800">
                {{ $license->active_assignments_count }} de {{ $license->seats }} licenças em uso.
                {{ $license->assignment_type->label() }}:
                {{ $perUser ? 'cada colaborador ocupa uma licença.' : 'cada equipamento ocupa uma licença.' }}
            </div>

            <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('licenses.assign.store', $license) }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="employee_id" :value="$perUser ? 'Colaborador' : 'Colaborador (opcional)'" />
                        <select id="employee_id" name="employee_id" @required($perUser) class="{{ $fieldClasses }}">
                            <option value="">{{ $perUser ? 'Selecione' : 'Não informado' }}</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>
                                    {{ $employee->name }}{{ $employee->registration ? ' (mat. ' . $employee->registration . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('employee_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="asset_id" :value="$perUser ? 'Equipamento (opcional)' : 'Equipamento'" />
                        <select id="asset_id" name="asset_id" @required(! $perUser) class="{{ $fieldClasses }}">
                            <option value="">{{ $perUser ? 'Não informado' : 'Selecione' }}</option>
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->id }}" @selected((string) old('asset_id') === (string) $asset->id)>
                                    {{ $asset->displayIdentifier() }}, {{ $asset->displayName() }}
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $perUser
                                ? 'Opcional: em qual equipamento o colaborador usa o programa.'
                                : 'O equipamento onde a licença está instalada.' }}
                        </p>
                        <x-input-error :messages="$errors->get('asset_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="assigned_at" value="Data da atribuição" />
                        <x-text-input id="assigned_at" name="assigned_at" type="date" class="mt-1 block w-full"
                                      :value="old('assigned_at', today()->format('Y-m-d'))" required />
                        <p class="mt-1 text-xs text-gray-500">Para licenças que já estão em uso, informe a data real.</p>
                        <x-input-error :messages="$errors->get('assigned_at')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="notes" value="Observações" />
                        <x-text-input id="notes" name="notes" type="text" class="mt-1 block w-full" :value="old('notes')" />
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>

                    <div class="flex justify-end border-t border-gray-200 pt-6">
                        <x-primary-button>Atribuir licença</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>