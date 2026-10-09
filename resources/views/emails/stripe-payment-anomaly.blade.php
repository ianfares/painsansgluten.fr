@component('mail::message')
# Anomalie de paiement — commande {{ $order->number }}

{{ $reason ?: 'Stripe a confirmé un paiement qui ne correspond pas à la commande.' }}

Total de la commande : **{{ number_format($order->total_ttc / 100, 2, ',', ' ') }} €** — statut actuel : **{{ $order->status->label() }}**.

Vérifiez le paiement dans le tableau de bord Stripe avant toute action.

@component('mail::button', ['url' => url(config('admin.path').'/orders/'.$order->id)])
Voir la commande
@endcomponent
@endcomponent
