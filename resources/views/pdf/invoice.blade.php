<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #f3ecdc; }
        .text-right { text-align: right; }
        .totals { margin-top: 16px; width: 300px; margin-left: auto; }
        .footer { margin-top: 40px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
@php($s = $invoice->snapshot)

<h1>{{ $invoice->type->label() }} {{ $s['number'] ?? $invoice->number }}</h1>
@if (($s['related_invoice_number'] ?? null))
    <p>Avoir relatif à la facture {{ $s['related_invoice_number'] }}</p>
@endif
<p>Date d'émission : {{ $invoice->issued_at->format('d/m/Y') }}</p>

<table>
    <tr>
        <td style="border: none; width: 50%; vertical-align: top;">
            <strong>{{ $s['seller']['name'] ?? '[RAISON SOCIALE À RENSEIGNER]' }}</strong><br>
            {{ $s['seller']['legal_form'] ?? '' }} {{ $s['seller']['share_capital'] ?? '' }}<br>
            {{ $s['seller']['address'] ?? '[ADRESSE À RENSEIGNER]' }}<br>
            SIRET : {{ $s['seller']['siret'] ?? '[À RENSEIGNER]' }}<br>
            RCS : {{ $s['seller']['rcs'] ?? '[À RENSEIGNER]' }}<br>
            TVA intracommunautaire : {{ $s['seller']['vat_number'] ?? '[À RENSEIGNER]' }}
        </td>
        <td style="border: none; width: 50%; vertical-align: top;">
            <strong>Client</strong><br>
            {{ $s['customer']['name'] ?? '' }}<br>
            {{ $s['customer']['company'] ?? '' }}<br>
            {{ $s['customer']['address'] ?? '' }}<br>
            {{ $s['customer']['postal_code'] ?? '' }} {{ $s['customer']['city'] ?? '' }}
        </td>
    </tr>
</table>

@php($hasDiscount = ($s['discount']['total_ttc'] ?? 0) > 0)
<table>
    <thead>
        <tr>
            <th>Désignation</th>
            <th>Référence</th>
            <th class="text-right">Qté</th>
            <th class="text-right">PU HT</th>
            @if ($hasDiscount)<th class="text-right">Remise</th>@endif
            <th class="text-right">Taux TVA</th>
            <th class="text-right">Total HT</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($s['lines'] ?? [] as $line)
            <tr>
                <td>{{ $line['designation'] }}</td>
                <td>{{ $line['reference'] }}</td>
                <td class="text-right">{{ $line['quantity'] }}</td>
                <td class="text-right">{{ number_format($line['unit_price_ht'] / 100, 2, ',', ' ') }} €</td>
                @if ($hasDiscount)<td class="text-right">{{ ($line['discount_percent'] ?? 0) > 0 ? '-'.$line['discount_percent'].' %' : '' }}</td>@endif
                <td class="text-right">{{ $line['vat_rate'] }} %</td>
                <td class="text-right">{{ number_format($line['total_ht'] / 100, 2, ',', ' ') }} €</td>
            </tr>
        @endforeach
        @if (($s['shipping']['total_ttc'] ?? 0) !== 0)
            <tr>
                <td colspan="{{ $hasDiscount ? 5 : 4 }}">Frais de port</td>
                <td class="text-right">{{ $s['shipping']['vat_rate'] ?? 0 }} %</td>
                <td class="text-right">{{ number_format(($s['shipping']['total_ttc'] ?? 0) / 100, 2, ',', ' ') }} €</td>
            </tr>
        @endif
    </tbody>
</table>

@if ($hasDiscount)
    <p>Remise client de {{ $s['discount']['percent'] }} % appliquée sur les produits (hors frais de port) : -{{ number_format($s['discount']['total_ttc'] / 100, 2, ',', ' ') }} € TTC. Les totaux s'entendent remise déduite.</p>
@endif

<p><strong>Récapitulatif par taux de TVA</strong></p>
<table>
    <thead>
        <tr><th>Taux</th><th class="text-right">Base HT</th><th class="text-right">TVA</th></tr>
    </thead>
    <tbody>
        @foreach ($s['vat_summary'] ?? [] as $row)
            <tr>
                <td>{{ $row['rate'] }} %</td>
                <td class="text-right">{{ number_format($row['base_ht'] / 100, 2, ',', ' ') }} €</td>
                <td class="text-right">{{ number_format($row['vat'] / 100, 2, ',', ' ') }} €</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="totals">
    <table>
        <tr><td>Total HT</td><td class="text-right">{{ number_format($invoice->total_ht / 100, 2, ',', ' ') }} €</td></tr>
        <tr><td>Total TVA</td><td class="text-right">{{ number_format($invoice->total_vat / 100, 2, ',', ' ') }} €</td></tr>
        <tr><td><strong>Total TTC</strong></td><td class="text-right"><strong>{{ number_format($invoice->total_ttc / 100, 2, ',', ' ') }} €</strong></td></tr>
    </table>
</div>

<p>Moyen de paiement : {{ $s['payment_method'] ?? '' }}
    @if ($s['paid_at'] ?? null)
        — payé le {{ \Illuminate\Support\Carbon::parse($s['paid_at'])->format('d/m/Y') }}
    @endif
</p>

<div class="footer">
    {{ $s['seller']['footer_mentions'] ?? '[Mentions légales de pied de facture à renseigner]' }}
</div>
</body>
</html>
