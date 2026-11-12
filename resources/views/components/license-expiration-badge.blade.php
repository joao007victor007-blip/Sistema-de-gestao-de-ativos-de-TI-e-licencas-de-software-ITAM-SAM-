@props(['license'])

@php
    $classes = match ($license->expirationState()) {
        'vigente', 'renovacao_automatica' => 'bg-green-100 text-green-800',
        'renova_em_breve' => 'bg-blue-100 text-blue-800',
        'vencendo' => 'bg-yellow-100 text-yellow-800',
        'vencida' => 'bg-red-100 text-red-800',
        default => 'bg-gray-200 text-gray-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "whitespace-nowrap rounded-full px-2 py-1 text-xs {$classes}"]) }}>
    {{ $license->expirationLabel() }}
</span>