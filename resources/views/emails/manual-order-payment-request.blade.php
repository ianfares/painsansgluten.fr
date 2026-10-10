@component('mail::message')
# Votre commande {{ $order->number }}

Bonjour {{ $order->first_name }},

Nous avons enregistré votre commande. Il ne reste plus qu'à la régler par carte bancaire, sur la page de paiement sécurisée :

@component('mail::button', ['url' => $paymentUrl])
Payer ma commande
@endcomponent

@include('emails.partials.order-summary')

La fabrication démarre dès réception du paiement.

À bientôt,<br>
{{ config('app.name') }}
@endcomponent
