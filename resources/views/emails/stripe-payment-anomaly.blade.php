@component('mail::message')
# Anomalie de paiement — commande {{ $order->number }}

Stripe a confirmé un paiement dont le montant ne correspond pas au total de la commande **{{ $order->number }}** ({{ number_format($order->total_ttc / 100, 2, ',', ' ') }} €).

La commande n'a **pas** été passée en « payée ». Vérifiez le paiement dans le tableau de bord Stripe avant toute action.

{{ config('app.name') }}
@endcomponent
