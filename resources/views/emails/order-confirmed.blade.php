@component('mail::message')
# Merci pour votre commande !

Bonjour {{ $order->first_name }},

Votre paiement a bien été reçu : votre commande **{{ $order->number }}** est confirmée. Nous allons la fabriquer pour vous.

@include('emails.partials.order-summary')

@if ($order->planned_ship_date)
📦 Expédition prévue le **{{ $order->planned_ship_date->locale('fr')->isoFormat('dddd D MMMM') }}**, en point relais Chronopost : **{{ $order->relay_name }}**.
@endif

@if ($invoiceUrl)
@component('mail::button', ['url' => $invoiceUrl])
Télécharger ma facture
@endcomponent
@endif

Merci de votre confiance,<br>
{{ config('app.name') }}
@endcomponent
