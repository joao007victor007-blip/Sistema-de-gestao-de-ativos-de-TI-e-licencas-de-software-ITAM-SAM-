<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Importar planilha de inventário</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    <p class="font-medium">{{ session('status') }}</p>
                    @if (session('summary'))
                        <ul class="mt-2 list-disc ps-5">
                            @foreach (session('summary') as $item)
                                <li>
                                    {{ $item['label'] }}: {{ $item['created'] }} novo(s)
                                    @if ($item['reused'] > 0), {{ $item['reused'] }} já existente(s) reaproveitado(s)@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">1. Baixe o modelo</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Use sempre o modelo baixado daqui. A aba "Instruções" explica como preencher cada coluna.
                </p>
                <a href="{{ route('import.template') }}"
                   class="mt-4 inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                    Baixar modelo (.xlsx)
                </a>
            </section>

            <section class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h3 class="text-lg font-medium text-gray-900">2. Envie a planilha preenchida</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Primeiro o sistema mostra uma prévia com os problemas encontrados. Nada é gravado até você confirmar.
                </p>

                <form method="POST" action="{{ route('import.preview') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <input type="file" name="planilha" accept=".xlsx" required
                           class="block w-full text-sm text-gray-700 file:me-4 file:rounded-md file:border-0 file:bg-gray-800 file:px-4 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-widest file:text-white hover:file:bg-gray-700">
                    <x-input-error :messages="$errors->get('planilha')" />
                    <x-primary-button>Analisar planilha</x-primary-button>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>