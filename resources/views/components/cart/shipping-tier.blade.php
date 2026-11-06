@props(['tier' => null, 'showHt' => false])

{{-- Frais de port par palier de montant (06/11/2026) : montant manquant pour
     atteindre le palier de livraison moins cher (texte demandé par Angélique). --}}
@if ($tier)
    <x-ui.alert variant="info">
        🚚 Plus que {{ number_format($tier['missing_ttc'] / 100, 2, ',', ' ') }} €{{ $showHt ? ' TTC' : '' }} pour bénéficier de la livraison à {{ number_format($tier['price_ttc'] / 100, 2, ',', ' ') }} €{{ $showHt ? ' TTC' : '' }}.
    </x-ui.alert>
@endif
