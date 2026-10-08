@component('mail::message')
# Votre commande est en route 🚚

Bonjour {{ $order->first_name }},

Votre commande **{{ $order->number }}** vient d'être expédiée vers votre point relais Chronopost : **{{ $order->relay_name }}**.

@if ($order->tracking_number)
Numéro de suivi : **{{ $order->tracking_number }}**
@endif

@if ($trackingUrl)
@component('mail::button', ['url' => $trackingUrl])
Suivre mon colis
@endcomponent
@endif

@if ($pickupMessage)
@component('mail::panel')
⚠️ {{ $pickupMessage }}
@endcomponent
@endif

Bonne dégustation,<br>
{{ config('app.name') }}
@endcomponent
