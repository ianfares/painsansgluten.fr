<x-compte.layout title="Mes commandes" active="orders">
    <h1 class="mb-6 text-2xl font-semibold text-ink">Mes commandes</h1>

    @if ($orders->isEmpty())
        <x-ui.alert variant="info">Vous n'avez pas encore passé de commande.</x-ui.alert>
    @else
        <div class="overflow-x-auto rounded-card border border-line bg-white">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line text-left text-ink-muted">
                        <th class="p-3">N°</th>
                        <th class="p-3">Date</th>
                        <th class="p-3">Total</th>
                        <th class="p-3">Statut</th>
                        <th class="p-3">Expédition</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr class="border-b border-line last:border-0">
                            <td class="p-3 font-medium">{{ $order->number }}</td>
                            <td class="p-3">{{ $order->created_at->format('d/m/Y') }}</td>
                            <td class="p-3">{{ number_format($order->total_ttc / 100, 2, ',', ' ') }} €</td>
                            <td class="p-3"><x-ui.badge>{{ $order->status->label() }}</x-ui.badge></td>
                            <td class="p-3">{{ $order->planned_ship_date?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</x-compte.layout>
