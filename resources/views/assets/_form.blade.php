@php
    $fieldClasses = 'mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm';
@endphp

{{-- Identificação --}}
<div class="space-y-6">
    <h3 class="text-base font-medium text-gray-900">Identificação</h3>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="category_id" value="Categoria" />
            <select id="category_id" name="category_id" required class="{{ $fieldClasses }}">
                <option value="">Selecione</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $asset->category_id) === (string) $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="asset_tag" value="Nº de patrimônio" />
            <x-text-input id="asset_tag" name="asset_tag" type="text" class="mt-1 block w-full uppercase"
                          :value="old('asset_tag', $asset->asset_tag)" />
            <p class="mt-1 text-xs text-gray-500">Número da etiqueta física. Sem etiqueta, o equipamento é identificado pelo código interno gerado pelo sistema.</p>
            <x-input-error :messages="$errors->get('asset_tag')" class="mt-2" />
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="manufacturer_id" value="Fabricante" />
            <select id="manufacturer_id" name="manufacturer_id" class="{{ $fieldClasses }}">
                <option value="">Não informado</option>
                @foreach ($manufacturers as $manufacturer)
                    <option value="{{ $manufacturer->id }}" @selected((string) old('manufacturer_id', $asset->manufacturer_id) === (string) $manufacturer->id)>
                        {{ $manufacturer->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('manufacturer_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="model" value="Modelo" />
            <x-text-input id="model" name="model" type="text" class="mt-1 block w-full"
                          :value="old('model', $asset->model)" />
            <p class="mt-1 text-xs text-gray-500">Opcional. Exemplos: Latitude 5440, Galaxy A55, P24h G5.</p>
            <x-input-error :messages="$errors->get('model')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="serial_number" value="Número de série" />
        <x-text-input id="serial_number" name="serial_number" type="text" class="mt-1 block w-full uppercase"
                      :value="old('serial_number', $asset->serial_number)" />
        <x-input-error :messages="$errors->get('serial_number')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" value="Descrição" />
        <textarea id="description" name="description" rows="3" class="{{ $fieldClasses }}">{{ old('description', $asset->description) }}</textarea>
        <p class="mt-1 text-xs text-gray-500">
            Opcional. Exemplos: "Core i5, 16 GB de RAM, SSD 512 GB"; "Monitor 24 polegadas Full HD"; "IMEI 35..., linha (81) 9...".
        </p>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>
</div>

{{-- Localização e situação --}}
<div class="space-y-6 border-t border-gray-200 pt-6">
    <h3 class="text-base font-medium text-gray-900">Localização e situação</h3>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="location_id" value="Local" />
            <select id="location_id" name="location_id" class="{{ $fieldClasses }}">
                <option value="">Não informado</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) old('location_id', $asset->location_id) === (string) $location->id)>
                        {{ $location->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="status" value="Situação" />
            @if ($asset->status->isEditable())
                <select id="status" name="status" required class="{{ $fieldClasses }}">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $asset->status->value) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
            @else
                <input type="hidden" name="status" value="{{ $asset->status->value }}">
                <p class="mt-2 text-sm text-gray-700">{{ $asset->status->label() }}</p>
                <p class="mt-1 text-xs text-gray-500">Esta situação só muda pela devolução ou pelo registro de descarte.</p>
            @endif
            <x-input-error :messages="$errors->get('status')" class="mt-2" />
        </div>
    </div>
</div>

{{-- Aquisição e garantia --}}
<div class="space-y-6 border-t border-gray-200 pt-6">
    <h3 class="text-base font-medium text-gray-900">Aquisição e garantia</h3>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <div>
            <x-input-label for="purchase_date" value="Data de compra" />
            <x-text-input id="purchase_date" name="purchase_date" type="date" class="mt-1 block w-full"
                          :value="old('purchase_date', $asset->purchase_date?->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('purchase_date')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="purchase_price" value="Valor de compra (R$)" />
            <x-text-input id="purchase_price" name="purchase_price" type="number" step="0.01" min="0" class="mt-1 block w-full"
                          :value="old('purchase_price', $asset->purchase_price)" />
            <x-input-error :messages="$errors->get('purchase_price')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="warranty_until" value="Fim da garantia" />
            <x-text-input id="warranty_until" name="warranty_until" type="date" class="mt-1 block w-full"
                          :value="old('warranty_until', $asset->warranty_until?->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('warranty_until')" class="mt-2" />
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="invoice_number" value="Nota fiscal" />
            <x-text-input id="invoice_number" name="invoice_number" type="text" class="mt-1 block w-full"
                          :value="old('invoice_number', $asset->invoice_number)" />
            <x-input-error :messages="$errors->get('invoice_number')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="supplier" value="Fornecedor" />
            <x-text-input id="supplier" name="supplier" type="text" class="mt-1 block w-full"
                          :value="old('supplier', $asset->supplier)" />
            <x-input-error :messages="$errors->get('supplier')" class="mt-2" />
        </div>
    </div>
</div>

{{-- Nota fiscal (somente no cadastro; depois, use "Documentos anexados") --}}
@unless ($asset->exists)
    <div class="space-y-6 border-t border-gray-200 pt-6">
        <div>
            <x-input-label for="invoice_file" value="Arquivo da nota fiscal (opcional)" />
            <input id="invoice_file" type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png"
                   class="mt-1 block w-full text-sm text-gray-700 file:me-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:text-gray-700 hover:file:bg-gray-200">
            <p class="mt-1 text-xs text-gray-500">
                PDF, JPG ou PNG, até 2 MB. Outros documentos podem ser anexados depois, na página do ativo.
                Se o formulário voltar com algum erro, selecione o arquivo de novo.
            </p>
            <x-input-error :messages="$errors->get('invoice_file')" class="mt-2" />
        </div>
    </div>
@endunless

{{-- Observações --}}
<div class="space-y-6 border-t border-gray-200 pt-6">
    <div>
        <x-input-label for="notes" value="Observações" />
        <textarea id="notes" name="notes" rows="3" class="{{ $fieldClasses }}">{{ old('notes', $asset->notes) }}</textarea>
        <p class="mt-1 text-xs text-gray-500">Fatos sobre o equipamento: avarias, acessórios faltando, empréstimos temporários.</p>
        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
    </div>
</div>