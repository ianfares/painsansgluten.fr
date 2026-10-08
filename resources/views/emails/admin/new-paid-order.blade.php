@component('mail::message')
# Nouvelle commande payée : {{ $order->number }}

- Client : {{ $order->first_name }} {{ $order->last_name }}
- Paiement : {{ $order->payment_method->label() }}
- Total : **{{ number_format($order->total_ttc / 100, 2, ',', ' ') }} €**
@if ($order->planned_ship_date)
- Expédition prévue : **{{ $order->planned_ship_date->locale('fr')->isoFormat('dddd D MMMM') }}**
@endif

@include('emails.partials.order-summary')

@component('mail::button', ['url' => url(config('admin.path').'/orders/'.$order->id)])
Voir la commande
@endcomponent
@endcomponent
