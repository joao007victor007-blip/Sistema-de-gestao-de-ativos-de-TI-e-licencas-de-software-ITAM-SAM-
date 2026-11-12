@props(['item', 'storeRoute'])

@php
    $fieldClasses = 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm';
@endphp

<section class="bg-white p-6 shadow-sm sm:rounded-lg">
    <h3 class="text-lg font-medium text-gray-900">Documentos anexados</h3>
    <p class="mt-1 text-xs text-gray-500">
        Notas fiscais, termos assinados e contratos. PDF, JPG ou PNG, até 2 MB cada,
        no máximo {{ \App\Models\Attachment::MAX_PER_ITEM }} por item.
    </p>

    <x-input-error :messages="$errors->get('arquivo')" class="mt-3" />

    @if ($item->attachments->isEmpty())
        <p class="mt-4 text-sm text-gray-600">Nenhum documento anexado.</p>
    @else
        <ul class="mt-4 divide-y divide-gray-100 text-sm">
            @foreach ($item->attachments as $attachment)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div>
                        <a href="{{ route('attachments.show', $attachment) }}" target="_blank" rel="noopener"
                           class="font-medium text-indigo-600 hover:text-indigo-900">
                            {{ $attachment->original_name }}
                        </a>
                        <div class="text-xs text-gray-500">
                            {{ $attachment->categoryLabel() }}, {{ $attachment->sizeLabel() }},
                            enviado em {{ $attachment->created_at->format('d/m/Y') }} por {{ $attachment->uploadedBy->name }}
                        </div>
                    </div>
                    @can('manage-inventory')
                        <form method="POST" action="{{ route('attachments.destroy', $attachment) }}"
                              onsubmit="return confirm('Remover este anexo?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-600 underline hover:text-red-800">Remover</button>
                        </form>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif

    @can('manage-inventory')
        @if ($item->attachments->count() < \App\Models\Attachment::MAX_PER_ITEM)
            <form method="POST" action="{{ $storeRoute }}" enctype="multipart/form-data"
                  class="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
                @csrf
                <div>
                    <label for="categoria" class="block text-xs font-medium text-gray-700">Tipo de documento</label>
                    <select id="categoria" name="categoria" class="mt-1 {{ $fieldClasses }}">
                        @foreach (\App\Models\Attachment::CATEGORIES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1">
                    <label for="arquivo" class="block text-xs font-medium text-gray-700">Arquivo</label>
                    <input id="arquivo" type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png" required
                           class="mt-1 block w-full text-sm text-gray-700 file:me-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:text-gray-700 hover:file:bg-gray-200">
                </div>
                <x-primary-button>Anexar</x-primary-button>
            </form>
        @else
            <p class="mt-4 text-xs text-gray-500">Limite de anexos atingido. Remova um documento para incluir outro.</p>
        @endcan
    @endcan
</section>