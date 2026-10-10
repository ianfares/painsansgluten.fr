@props(['amount', 'suffix' => null])
{{--
    Prix TTC d'un produit pour le visiteur courant (T27-L6) : client connecté
    avec une remise → prix public barré + prix remisé. Le taux vient du compte
    (App\Services\Pricing\CustomerPricing), jamais d'un paramètre de la page.
--}}
@php
    $percent = \App\Services\Pricing\CustomerPricing::percentFor(auth('web')->user());
    $net = \App\Services\Pricing\CustomerPricing::unitNet((int) $amount, $percent);
    $format = fn (int $cents): string => number_format($cents / 100, 2, ',', ' ').' €';
@endphp
<span {{ $attributes }}>
    @if ($percent > 0)
        <s class="text-[0.8em] font-normal text-ink-muted">{{ $format((int) $amount) }}</s>
        {{ $format($net) }}@if ($suffix) <span class="text-sm font-normal text-ink-muted">{{ $suffix }}</span>@endif
        <span class="ml-1 whitespace-nowrap rounded-full bg-sage/10 px-2 py-0.5 text-xs font-semibold text-sage-dark">Votre remise -{{ $percent }} %</span>
    @else
        {{ $format((int) $amount) }}@if ($suffix) <span class="text-sm font-normal text-ink-muted">{{ $suffix }}</span>@endif
    @endif
</span>
