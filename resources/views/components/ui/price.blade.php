@props(['amount', 'suffix' => null, 'vatRate' => null])
{{--
    Prix TTC d'un produit pour le visiteur courant (T27-L6) : client connecté
    avec une remise → prix public barré + prix remisé. Le taux vient du compte
    (App\Services\Pricing\CustomerPricing), jamais d'un paramètre de la page.
    Avec `vatRate` (fiche produit) et un compte professionnel : prix HT en
    premier, TTC en dessous (demande de Ian, 15/10/2026).
--}}
@php
    $percent = \App\Services\Pricing\CustomerPricing::percentFor(auth('web')->user());
    $net = \App\Services\Pricing\CustomerPricing::unitNet((int) $amount, $percent);
    $format = fn (int $cents): string => number_format($cents / 100, 2, ',', ' ').' €';
    $showHt = $vatRate !== null && \App\Services\Pricing\CustomerPricing::showsHt(auth('web')->user());
    $ht = fn (int $cents): int => \App\Services\Pricing\CustomerPricing::htFromTtc($cents, (float) $vatRate);
@endphp
@if ($showHt)
<span {{ $attributes }}>
    @if ($percent > 0)
        <s class="text-[0.8em] font-normal text-ink-muted">{{ $format($ht((int) $amount)) }}</s>
    @endif
    {{ $format($ht($net)) }} <span class="text-sm font-normal text-ink-muted">HT</span>
    @if ($percent > 0)
        <span class="ml-1 whitespace-nowrap rounded-full bg-sage/10 px-2 py-0.5 text-xs font-semibold text-sage-dark">Votre remise -{{ $percent }} %</span>
    @endif
    <span class="block text-sm font-normal text-ink-muted">soit {{ $format($net) }} TTC (TVA {{ rtrim(rtrim(number_format((float) $vatRate, 2, ',', ''), '0'), ',') }} %)</span>
</span>
@else
<span {{ $attributes }}>
    @if ($percent > 0)
        <s class="text-[0.8em] font-normal text-ink-muted">{{ $format((int) $amount) }}</s>
        {{ $format($net) }}@if ($suffix) <span class="text-sm font-normal text-ink-muted">{{ $suffix }}</span>@endif
        <span class="ml-1 whitespace-nowrap rounded-full bg-sage/10 px-2 py-0.5 text-xs font-semibold text-sage-dark">Votre remise -{{ $percent }} %</span>
    @else
        {{ $format((int) $amount) }}@if ($suffix) <span class="text-sm font-normal text-ink-muted">{{ $suffix }}</span>@endif
    @endif
</span>
@endif
