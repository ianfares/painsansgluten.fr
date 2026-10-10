<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 5px 8px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f3ecdc; }
        .text-right { text-align: right; }
        .filters { color: #444; margin: 0; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
<h1>À produire</h1>
@foreach ($filters as $line)
    <p class="filters">{{ $line }}</p>
@endforeach
<p class="filters">Édité le {{ $generatedAt->format('d/m/Y à H:i') }}</p>

<h2>Total à produire par produit</h2>
<table>
    <thead><tr><th>Produit</th><th>Catégorie</th><th class="text-right">Quantité</th></tr></thead>
    <tbody>
    @forelse ($totals as $row)
        <tr><td>{{ $row['product_name'] }}</td><td>{{ $row['category'] }}</td><td class="text-right">{{ $row['quantity'] }}</td></tr>
    @empty
        <tr><td colspan="3">Rien à produire sur cette période.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="page-break"></div>
<h2>Détail par commande</h2>
<table>
    <thead><tr><th>Expédition prévue</th><th>N° commande</th><th>Client</th><th>Livraison</th><th>Produit</th><th class="text-right">Qté</th></tr></thead>
    <tbody>
    @forelse ($details as $row)
        <tr>
            <td>{{ \Carbon\Carbon::parse($row['planned_ship_date'])->format('d/m/Y') }}</td>
            <td>{{ $row['order_number'] }}</td>
            <td>{{ $row['customer'] }}</td>
            <td>{{ $row['delivery_method'] }}</td>
            <td>{{ $row['product_name'] }}</td>
            <td class="text-right">{{ $row['quantity'] }}</td>
        </tr>
    @empty
        <tr><td colspan="6">Aucune commande sur cette période.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
