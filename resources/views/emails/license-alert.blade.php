<x-mail::message>
# Avisos de licenças

@if ($expired->isNotEmpty())
## Vencidas (exigem ação)

<x-mail::table>
| Licença | Software | Venceu em |
|:--------|:---------|:----------|
@foreach ($expired as $license)
| [{{ $license->name }}]({{ route('licenses.show', $license) }}) | {{ $license->software->name }} | {{ $license->expires_at->format('d/m/Y') }} |
@endforeach
</x-mail::table>
@endif

@if ($expiring->isNotEmpty())
## Vencem em breve (renovação manual)

<x-mail::table>
| Licença | Software | Vence em | Faltam |
|:--------|:---------|:---------|:-------|
@foreach ($expiring as $license)
| [{{ $license->name }}]({{ route('licenses.show', $license) }}) | {{ $license->software->name }} | {{ $license->expires_at->format('d/m/Y') }} | {{ (int) today()->diffInDays($license->expires_at) }} dia(s) |
@endforeach
</x-mail::table>
@endif

@if ($renewing->isNotEmpty())
## Renovações automáticas próximas

<x-mail::table>
| Licença | Software | Cobrança em | Valor |
|:--------|:---------|:------------|:------|
@foreach ($renewing as $license)
| [{{ $license->name }}]({{ route('licenses.show', $license) }}) | {{ $license->software->name }} | {{ $license->nextRenewalDate()->format('d/m/Y') }} ({{ mb_strtolower($license->billing_cycle->label()) }}) | {{ $license->cost !== null ? 'R$ ' . number_format((float) $license->cost, 2, ',', '.') : '—' }} |
@endforeach
</x-mail::table>

As renovações automáticas não exigem ação. O aviso serve para revisar a quantidade de licenças e o orçamento antes da cobrança.
@endif

<x-mail::button :url="route('licenses.index')">
Abrir as licenças na intranet
</x-mail::button>

Este é um aviso automático da Intranet de TI da Construtora Exemplo.
</x-mail::message>