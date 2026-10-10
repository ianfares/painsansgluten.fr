@php
    $plan = $this->plan();
    $totals = $plan->totalsByProduct();
    $details = $plan->details($this->sortBy, $this->sortDirection);
    $columns = [
        'planned_ship_date' => 'Expédition prévue',
        'order_number' => 'N° commande',
        'customer' => 'Client',
        'delivery_method' => 'Livraison',
        'product_name' => 'Produit',
        'quantity' => 'Qté',
    ];
@endphp
<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    <div>
        <x-filament::button wire:click="exportPdf" icon="heroicon-o-arrow-down-tray">Exporter en PDF</x-filament::button>
    </div>

    <x-filament::section heading="Total à produire par produit">
        @if ($totals->isEmpty())
            <p class="text-sm text-gray-500">Rien à produire sur cette période.</p>
        @else
            <table class="w-full text-sm text-left">
                <thead><tr><th class="py-1">Produit</th><th>Catégorie</th><th class="text-right">Quantité</th></tr></thead>
                <tbody>
                @foreach ($totals as $row)
                    <tr class="border-t"><td class="py-1">{{ $row['product_name'] }}</td><td>{{ $row['category'] }}</td><td class="text-right font-semibold">{{ $row['quantity'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section heading="Détail par commande">
        @if ($details->isEmpty())
            <p class="text-sm text-gray-500">Aucune commande sur cette période.</p>
        @else
            <table class="w-full text-sm text-left">
                <thead>
                <tr>
                    @foreach ($columns as $key => $label)
                        <th class="py-1"><button type="button" wire:click="sort('{{ $key }}')" class="font-semibold">{{ $label }}@if ($this->sortBy === $key) {{ $this->sortDirection === 'asc' ? '▲' : '▼' }}@endif</button></th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach ($details as $row)
                    <tr class="border-t">
                        <td class="py-1">{{ \Carbon\Carbon::parse($row['planned_ship_date'])->format('d/m/Y') }}</td>
                        <td>{{ $row['order_number'] }}</td>
                        <td>{{ $row['customer'] }}</td>
                        <td>{{ $row['delivery_method'] }}</td>
                        <td>{{ $row['product_name'] }}</td>
                        <td>{{ $row['quantity'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
