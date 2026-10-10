<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 20px; margin: 16px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f3ecdc; }
        .text-right { text-align: right; }
        .note { page-break-after: always; }
        .note-last { page-break-after: auto; }
        .qr { margin-top: 30px; text-align: center; }
        .qr img { width: 3.6cm; height: 3.6cm; }
    </style>
</head>
<body>
@foreach ($notes as $note)
    @php($order = $note['order'])
    <div class="{{ $loop->last ? 'note-last' : 'note' }}">
        <strong>{{ $shopName ?: '[NOM DE LA BOUTIQUE À RENSEIGNER]' }}</strong><br>
        {{ $shopAddress }}<br>
        {{ $shopPhone }}

        <h1>Bon de livraison</h1>
        <p>Commande n° <strong>{{ $order->number }}</strong> — passée le {{ $order->created_at->timezone('Europe/Paris')->format('d/m/Y') }}</p>

        <table>
            <tr>
                <td style="border: none; width: 50%; vertical-align: top;">
                    <strong>Client</strong><br>
                    {{ $order->first_name }} {{ $order->last_name }}<br>
                    Tél. : {{ $order->phone }}<br>
                    {{ $order->email }}
                </td>
                <td style="border: none; width: 50%; vertical-align: top;">
                    <strong>Livraison</strong><br>
                    {{ $order->delivery_method->label() }}<br>
                    {{ $note['address'] }}
                </td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th>Produit</th>
                    <th class="text-right">Poids net</th>
                    <th class="text-right">Quantité</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($note['items'] as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td class="text-right">{{ $item->product?->formattedNetWeight() }}</td>
                        <td class="text-right">{{ $item->quantity }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2"><strong>Total articles</strong></td>
                    <td class="text-right"><strong>{{ $note['totalQuantity'] }}</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="qr">
            <img src="{{ $note['qr'] }}" alt="QR code du bon de livraison">
        </div>
    </div>
@endforeach
</body>
</html>
