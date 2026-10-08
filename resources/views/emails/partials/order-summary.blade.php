@component('mail::table')
| Produit | Qté | Total |
|:--------|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | {{ number_format($item->line_total_ttc / 100, 2, ',', ' ') }} € |
@endforeach
| Livraison | | {{ number_format($order->shipping_ttc / 100, 2, ',', ' ') }} € |
| **Total TTC** | | **{{ number_format($order->total_ttc / 100, 2, ',', ' ') }} €** |
@endcomponent
