<div>
    <x-input-label for="name" value="Nome" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                  :value="old('name', $location->name)" required autofocus />
    <p class="mt-1 text-xs text-gray-500">Exemplos: Escritório Central, Obra Residencial Jardins, Almoxarifado Centro.</p>
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div>
    <x-input-label for="type" value="Tipo" />
    <select id="type" name="type" required
            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
        @foreach ($types as $type)
            <option value="{{ $type->value }}" @selected(old('type', $location->type?->value) === $type->value)>
                {{ $type->label() }}
            </option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('type')" class="mt-2" />
</div>

<div class="grid grid-cols-1 gap-6 sm:grid-cols-4">
    <div class="sm:col-span-3">
        <x-input-label for="city" value="Cidade" />
        <x-text-input id="city" name="city" type="text" class="mt-1 block w-full"
                      :value="old('city', $location->city)" />
        <x-input-error :messages="$errors->get('city')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="state" value="UF" />
        <x-text-input id="state" name="state" type="text" maxlength="2" class="mt-1 block w-full uppercase"
                      :value="old('state', $location->state)" />
        <x-input-error :messages="$errors->get('state')" class="mt-2" />
    </div>
</div>

<div>
    <x-input-label for="notes" value="Observações" />
    <textarea id="notes" name="notes" rows="3"
              class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $location->notes) }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
</div>