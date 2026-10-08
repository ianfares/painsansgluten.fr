@component('mail::message')
# Virement reçu — commande {{ $order->number }}

Bonjour {{ $order->first_name }},

Nous avons bien reçu votre virement pour la commande **{{ $order->number }}**. Elle va maintenant être préparée.

@if ($order->planned_ship_date)
📦 Expédition prévue le **{{ $order->planned_ship_date->locale('fr')->isoFormat('dddd D MMMM') }}**.
@endif

@if ($invoiceUrl)
@component('mail::button', ['url' => $invoiceUrl])
Télécharger ma facture
@endcomponent
@endif

Merci de votre confiance,<br>
{{ config('app.name') }}
@endcomponent
