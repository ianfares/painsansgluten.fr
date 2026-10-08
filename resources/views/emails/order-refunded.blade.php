@component('mail::message')
# Remboursement de votre commande {{ $order->number }}

Bonjour {{ $order->first_name }},

Nous vous confirmons le remboursement de votre commande **{{ $order->number }}**, d'un montant de **{{ number_format($order->total_ttc / 100, 2, ',', ' ') }} €**.

@if ($order->payment_method === \App\Enums\PaymentMethod::Stripe)
Le montant sera recrédité sur la carte utilisée pour le paiement, généralement sous 5 à 10 jours selon votre banque.
@else
Le montant vous est remboursé par virement bancaire.
@endif

@if ($creditNoteUrl)
@component('mail::button', ['url' => $creditNoteUrl])
Télécharger mon avoir
@endcomponent
@endif

{{ config('app.name') }}
@endcomponent
