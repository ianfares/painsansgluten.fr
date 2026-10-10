<x-compte.layout :title="'Commande '.$order->number" active="orders">
    <a href="{{ route('compte.orders') }}" class="mb-4 inline-block text-sm text-ink-muted hover:text-sage">← Mes commandes</a>

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold text-ink">Commande {{ $order->number }}</h1>
        <x-ui.badge>{{ $order->status->label() }}</x-ui.badge>
    </div>
    <p class="mb-6 text-sm text-ink-muted">Passée le {{ $order->created_at->format('d/m/Y') }} — paiement : {{ $order->payment_method->label() }}</p>

    @if ($order->status === \App\Enums\OrderStatus::PendingPayment && $order->payment_method === \App\Enums\PaymentMethod::Stripe)
        <x-ui.alert variant="info" class="mb-6">
            <p class="mb-3">Le paiement de cette commande n'est pas finalisé.</p>
            <x-ui.button href="{{ route('checkout.stripe.start', $order) }}">Payer ma commande</x-ui.button>
        </x-ui.alert>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <section class="rounded-card border border-line bg-white p-4 text-sm">
            <h2 class="mb-2 font-semibold text-ink">Livraison</h2>
            <p>Point relais Chronopost : <strong>{{ $order->relay_name }}</strong></p>
            @if ($order->planned_ship_date)
                <p class="mt-1">Expédition prévue le <strong>{{ $order->planned_ship_date->locale('fr')->isoFormat('dddd D MMMM') }}</strong></p>
            @endif
            @if ($order->tracking_number)
                <p class="mt-1">N° de suivi : <strong>{{ $order->tracking_number }}</strong></p>
                @if ($order->trackingUrl())
                    <a href="{{ $order->trackingUrl() }}" target="_blank" rel="noopener" class="mt-2 inline-block text-sage-dark underline hover:text-sage">Suivre mon colis</a>
                @endif
            @endif
        </section>

        <section class="rounded-card border border-line bg-white p-4 text-sm">
            <h2 class="mb-2 font-semibold text-ink">Factures</h2>
            @forelse ($order->invoices as $invoice)
                <p><a href="{{ route('invoices.download', $invoice) }}" target="_blank" class="text-sage-dark underline hover:text-sage">{{ $invoice->type->label() }} {{ $invoice->number }} (PDF)</a></p>
            @empty
                <p class="text-ink-muted">La facture sera disponible une fois le paiement reçu.</p>
            @endforelse
        </section>
    </div>

    <div class="overflow-x-auto rounded-card border border-line bg-white">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-line text-left text-ink-muted">
                    <th class="p-3">Produit</th>
                    <th class="p-3 text-center">Quantité</th>
                    <th class="p-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr class="border-b border-line">
                        <td class="p-3">{{ $item->product_name }}</td>
                        <td class="p-3 text-center">{{ $item->quantity }}</td>
                        <td class="p-3 text-right">{{ number_format($item->line_total_ttc / 100, 2, ',', ' ') }} €</td>
                    </tr>
                @endforeach
                @if ($order->discount_total_ttc > 0)
                    <tr class="border-b border-line text-sage-dark">
                        <td class="p-3" colspan="2">Votre remise -{{ $order->discount_percent }} % (déjà déduite)</td>
                        <td class="p-3 text-right">-{{ number_format($order->discount_total_ttc / 100, 2, ',', ' ') }} €</td>
                    </tr>
                @endif
                <tr class="border-b border-line">
                    <td class="p-3" colspan="2">Livraison</td>
                    <td class="p-3 text-right">{{ number_format($order->shipping_ttc / 100, 2, ',', ' ') }} €</td>
                </tr>
                <tr class="font-semibold">
                    <td class="p-3" colspan="2">Total TTC</td>
                    <td class="p-3 text-right">{{ number_format($order->total_ttc / 100, 2, ',', ' ') }} €</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-compte.layout>
