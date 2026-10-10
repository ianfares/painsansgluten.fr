@component('mail::message')
# Votre commande est prête 🧺

Bonjour {{ $order->first_name }},

Votre commande **{{ $order->number }}** vous attend : {{ $order->delivery_method->label() }}.

@component('mail::panel')
**{{ $point['name'] ?? $order->relay_name }}**<br>
{{ $point['address_line1'] ?? '' }}<br>
{{ $point['postal_code'] ?? '' }} {{ $point['city'] ?? '' }}
@if (! empty($point['opening_hours']))

**Horaires :** {{ $point['opening_hours'] }}
@endif
@if (! empty($point['instructions']))

{{ $point['instructions'] }}
@endif
@endcomponent

Pensez à indiquer votre numéro de commande lors du retrait. Nos produits sont frais : merci de venir les chercher rapidement.

Bonne dégustation,<br>
{{ config('app.name') }}
@endcomponent
