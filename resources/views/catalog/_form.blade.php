<div>
    <x-input-label for="name" value="Nome" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                  :value="old('name', $item->name)" required autofocus />
    <p class="mt-1 text-xs text-gray-500">{{ $labels['hint'] }}</p>
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div>
    <x-input-label for="notes" value="Observações" />
    <textarea id="notes" name="notes" rows="3"
              class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $item->notes) }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
</div>