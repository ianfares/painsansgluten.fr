@component('mail::table')
| Produit | Qté | Total |
|:--------|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | {{ number_format($item->line_total_ttc / 100, 2, ',', ' ') }} € |
@endforeach
@if ($order->discount_total_ttc > 0)
| Remise client -{{ $order->discount_percent }} % (déjà déduite) | | -{{ number_format($order->discount_total_ttc / 100, 2, ',', ' ') }} € |
@endif
| Livraison | | {{ $order->delivery_method->isPickup() ? 'Gratuit (retrait)' : number_format($order->shipping_ttc / 100, 2, ',', ' ').' €' }} |
| **Total TTC** | | **{{ number_format($order->total_ttc / 100, 2, ',', ' ') }} €** |
@endcomponent
