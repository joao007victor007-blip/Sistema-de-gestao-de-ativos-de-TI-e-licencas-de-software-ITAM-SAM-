<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Backups</h2>
            <form method="POST" action="{{ route('backups.store') }}">
                @csrf
                <x-primary-button>Gerar backup agora</x-primary-button>
            </form>
        </div>
    </x-slot>

    @php
        $isLate = ! $latest || $latest['created_at']->lt(now()->subHours(48));
    @endphp

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            @if ($isLate)
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    @if ($latest)
                        O último backup é de {{ $latest['created_at']->format('d/m/Y H:i') }}, há mais de 48 horas.
                    @else
                        Nenhum backup encontrado.
                    @endif
                    Verifique a tarefa agendada no Cron do cPanel, ou gere um backup agora.
                </div>
            @endif

            <section class="bg-white p-6 shadow-sm sm:rounded-lg text-sm text-gray-700 space-y-2">
                <h3 class="text-lg font-medium text-gray-900">Como funcionam os backups</h3>
                <p>Um backup completo do banco é gerado automaticamente todos os dias de madrugada, e os últimos {{ $keepDays }} dias ficam guardados aqui.</p>
                <p><strong>Toda semana, baixe o backup mais recente</strong> e guarde-o numa pasta restrita da empresa. Um backup que fica só no servidor não protege contra a perda do próprio servidor.</p>
                <p>Os arquivos contêm dados de colaboradores: não os envie por e-mail nem os deixe em pastas compartilhadas. Cada download fica registrado na auditoria.</p>
            </section>

                        <section class="bg-white p-6 shadow-sm sm:rounded-lg text-sm text-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Anexos</h3>
                        <p class="mt-1">
                            Notas fiscais e documentos ocupam
                            <strong>{{ number_format($attachmentsSize / 1048576, 1, ',', '.') }} MB</strong>.
                            Eles não fazem parte do backup do banco: baixe o pacote junto com o backup semanal.
                        </p>
                    </div>
                    <a href="{{ route('backups.attachments') }}"
                       class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                        Baixar todos os anexos (.zip)
                    </a>
                </div>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-6 py-3 font-medium">Arquivo</th>
                            <th class="px-6 py-3 font-medium">Gerado em</th>
                            <th class="px-6 py-3 font-medium">Tamanho</th>
                            <th class="px-6 py-3 font-medium text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($files as $file)
                            <tr>
                                <td class="px-6 py-3 font-mono text-gray-900">{{ $file['name'] }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $file['created_at']->format('d/m/Y H:i') }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ number_format($file['size'] / 1024, 1, ',', '.') }} KB</td>
                                <td class="px-6 py-3 text-right">
                                    <a href="{{ route('backups.download', $file['name']) }}" class="text-indigo-600 hover:text-indigo-900">Baixar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500">Nenhum backup gerado ainda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</x-app-layout>