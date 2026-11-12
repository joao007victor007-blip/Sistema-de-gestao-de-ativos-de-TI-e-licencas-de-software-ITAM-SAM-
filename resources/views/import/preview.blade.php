<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Prévia da importação</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if ($result->hasErrors())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    Encontramos {{ count($result->errors()) }} problema(s). <strong>Nada foi gravado.</strong>
                    Corrija a planilha e envie novamente.
                </div>

                <section class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-6 py-3 font-medium">Aba</th>
                                <th class="px-6 py-3 font-medium">Linha</th>
                                <th class="px-6 py-3 font-medium">Problema</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($result->errors() as $error)
                                <tr>
                                    <td class="px-6 py-3 whitespace-nowrap text-gray-900">{{ $error['sheet'] }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $error['line'] ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-900">{{ $error['message'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>

                <a href="{{ route('import.index') }}" class="inline-block text-sm text-indigo-600 underline hover:text-indigo-900">
                    Enviar a planilha corrigida
                </a>
            @else
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    Nenhum problema encontrado. Confira o resumo abaixo e confirme a importação.
                </div>

                <section class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                            <tr>
                                <th class="px-6 py-3 font-medium">Item</th>
                                <th class="px-6 py-3 font-medium">Novos</th>
                                <th class="px-6 py-3 font-medium">Já existentes (reaproveitados)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($result->summary() as $item)
                                <tr>
                                    <td class="px-6 py-3 text-gray-900">{{ $item['label'] }}</td>
                                    <td class="px-6 py-3 text-gray-900">{{ $item['created'] }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $item['reused'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>

                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('import.index') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Cancelar</a>
                    <form method="POST" action="{{ route('import.store') }}"
                          onsubmit="return confirm('Confirma a gravação desses dados no sistema?')">
                        @csrf
                        <x-primary-button>Confirmar importação</x-primary-button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>