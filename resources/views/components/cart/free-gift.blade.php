@props(['gift', 'mode' => 'line'])

{{-- Pâtisserie offerte (20/10/2026) : « line » = ligne à 0 € si le seuil est atteint ;
     « progress » = montant manquant pour l'obtenir. --}}
@if ($gift['active'])
    @if ($mode === 'line' && $gift['earned'])
        <div {{ $attributes->merge(['class' => 'flex items-center justify-between gap-3 text-sm']) }}>
            <span class="font-medium text-ink">🎁 {{ $gift['label'] }} × 1</span>
            <span class="whitespace-nowrap font-semibold text-sage-dark">Offert (0,00 €)</span>
        </div>
    @elseif ($mode === 'progress' && ! $gift['earned'] && $gift['missing_ttc'] > 0)
        <x-ui.alert variant="info">
            🎁 Plus que {{ number_format($gift['missing_ttc'] / 100, 2, ',', ' ') }} € d'achats (hors frais de livraison) pour recevoir : {{ mb_strtolower($gift['label']) }}.
        </x-ui.alert>
    @endif
@endif
