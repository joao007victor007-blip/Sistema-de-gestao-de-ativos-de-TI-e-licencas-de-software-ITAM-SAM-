<section class="bg-white p-6 shadow-sm sm:rounded-lg">
    <details>
        <summary class="cursor-pointer text-lg font-medium text-gray-900">
            Histórico de alterações
            <span class="text-sm font-normal text-gray-500">(auditoria, visível só para administradores)</span>
        </summary>

        @if ($logs->isEmpty())
            <p class="mt-4 text-sm text-gray-600">Nenhum registro de auditoria para este item.</p>
        @else
            <ul class="mt-4 divide-y divide-gray-100 text-sm">
                @foreach ($logs as $log)
                    <li class="py-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-gray-900">{{ $log->description }}</span>
                            <span class="text-xs text-gray-500">{{ $log->created_at->format('d/m/Y H:i') }}, {{ $log->actorName() }}</span>
                        </div>
                        @if ($log->changes)
                            @include('audit._changes', ['changes' => $log->changes])
                        @endif
                    </li>
                @endforeach
            </ul>

            <a href="{{ $fullUrl }}" class="mt-4 inline-block text-sm text-indigo-600 underline hover:text-indigo-900">
                Ver todos os registros deste item na auditoria
            </a>
        @endif
    </details>
</section>