@component('mail::message')
# Commande {{ $order->number }} annulée

Bonjour {{ $order->first_name }},

Nous n'avons pas reçu votre virement dans le délai imparti : votre commande **{{ $order->number }}** a été automatiquement annulée.

Si vous souhaitez tout de même passer cette commande, vous pouvez repasser commande sur notre boutique. N'hésitez pas à nous contacter si vous pensez qu'il s'agit d'une erreur.

{{ config('app.name') }}
@endcomponent
