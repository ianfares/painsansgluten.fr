<x-compte.layout title="Mes factures" active="invoices">
    <h1 class="mb-6 text-2xl font-semibold text-ink">Mes factures</h1>

    @if ($invoices->isEmpty())
        <x-ui.alert variant="info">Aucune facture pour le moment.</x-ui.alert>
    @else
        <div class="overflow-x-auto rounded-card border border-line bg-white">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-ink-muted">
                        <th class="p-3">N°</th>
                        <th class="p-3">Date</th>
                        <th class="p-3">Montant TTC</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr class="border-b border-line last:border-0">
                            <td class="p-3 font-medium">{{ $invoice->number }}</td>
                            <td class="p-3">{{ $invoice->issued_at->format('d/m/Y') }}</td>
                            <td class="p-3">{{ number_format($invoice->total_ttc / 100, 2, ',', ' ') }} €</td>
                            <td class="p-3 text-right">
                                <a href="{{ route('invoices.download', $invoice) }}" class="text-sage hover:underline" target="_blank">⬇ PDF</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $invoices->links() }}</div>
    @endif
</x-compte.layout>
