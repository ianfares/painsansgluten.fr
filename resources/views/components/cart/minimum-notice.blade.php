@props(['subtotal', 'chronopost', 'pickup'])

@php
    // Minimums de commande par mode de livraison (19/10/2026), en centimes, hors frais de port.
    $euros = fn (int $cents): string => number_format($cents / 100, 2, ',', ' ').' €';
    $lowest = min($chronopost, $pickup);
@endphp

@if ($subtotal < max($chronopost, $pickup))
    <x-ui.alert :variant="$subtotal < $lowest ? 'warning' : 'info'">
        @if ($chronopost === $pickup)
            Minimum de commande : {{ $euros($chronopost) }} (hors frais de livraison).
        @else
            Minimum de commande (hors frais de livraison) : {{ $euros($chronopost) }} en livraison Chronopost Relais, {{ $euros($pickup) }} en retrait.
        @endif
        @if ($subtotal < $lowest)
            Il vous manque {{ $euros($lowest - $subtotal) }}.
        @else
            {{ $chronopost > $pickup ? 'Livraison Chronopost' : 'Retrait' }} possible à partir de {{ $euros(max($chronopost, $pickup)) }}.
        @endif
    </x-ui.alert>
@endif
