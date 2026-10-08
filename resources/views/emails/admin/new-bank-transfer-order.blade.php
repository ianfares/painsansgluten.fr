@component('mail::message')
# Nouveau virement en attente : {{ $order->number }}

{{ $order->first_name }} {{ $order->last_name }} a passé une commande de **{{ number_format($order->total_ttc / 100, 2, ',', ' ') }} €** à régler par virement.

À la réception du virement, vérifiez sur le relevé que **la référence ({{ $order->number }}) et le montant** correspondent, puis cliquez sur « Valider le virement reçu » dans la commande.

@component('mail::button', ['url' => url(config('admin.path').'/orders/'.$order->id)])
Voir la commande
@endcomponent
@endcomponent
