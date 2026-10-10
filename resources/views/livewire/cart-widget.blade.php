<div>
    @if ($removedNotice)
        <x-ui.alert variant="warning" class="mb-4">
            Retiré du panier (produit devenu indisponible) : {{ implode(', ', $removedNames) }}
        </x-ui.alert>
    @endif

    @if ($items->isEmpty())
        <p class="text-sm text-ink-muted">Votre panier est vide.</p>
    @else
        <div class="flex flex-col divide-y divide-line">
            @foreach ($items as $item)
                <div class="flex items-center gap-3 py-4" wire:key="cart-item-{{ $item->id }}">
                    <div class="h-14 w-14 flex-shrink-0 overflow-hidden rounded-card bg-cream-alt">
                        @if ($url = $item->product->getFirstMediaUrl('main', 'thumbnail'))
                            <img src="{{ $url }}" alt="" width="150" height="150" loading="lazy" class="h-full w-full object-cover">
                        @endif
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-ink">{{ $item->product->name }}</p>
                        <p class="text-xs text-ink-muted">@if ($totals['discount_percent'] > 0)<s>{{ number_format($item->product->price_ttc / 100, 2, ',', ' ') }} €</s> @endif{{ number_format(\App\Services\Pricing\CustomerPricing::unitNet($item->product->price_ttc, $totals['discount_percent']) / 100, 2, ',', ' ') }} € / unité</p>
                        <div class="mt-1 flex items-center gap-2">
                            <button type="button" wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity - 1 }})" class="h-6 w-6 rounded-full border border-line text-xs" aria-label="Diminuer la quantité">−</button>
                            <span class="text-sm">{{ $item->quantity }}</span>
                            <button type="button" wire:click="updateQuantity({{ $item->id }}, {{ $item->quantity + 1 }})" class="h-6 w-6 rounded-full border border-line text-xs" aria-label="Augmenter la quantité">+</button>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-ink">{{ number_format(\App\Services\Pricing\CustomerPricing::unitNet($item->product->price_ttc, $totals['discount_percent']) * $item->quantity / 100, 2, ',', ' ') }} €</p>
                        <button type="button" wire:click="removeItem({{ $item->id }})" class="mt-1 text-xs text-ink-muted hover:text-red-600" aria-label="Retirer {{ $item->product->name }}">Retirer</button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 flex flex-col gap-2 border-t border-line pt-4 text-sm">
            <div class="flex justify-between"><span>Sous-total TTC</span><span>{{ number_format(($totals['subtotal_ttc'] + $totals['discount_ttc']) / 100, 2, ',', ' ') }} €</span></div>
            @if ($totals['discount_ttc'] > 0)
                <div class="flex justify-between text-sage-dark"><span>Votre remise -{{ $totals['discount_percent'] }} %</span><span>-{{ number_format($totals['discount_ttc'] / 100, 2, ',', ' ') }} €</span></div>
            @endif

            @if ($totals['shipping_error'])
                {{-- Message client générique : le détail technique est signalé à l'admin (tableau de bord). --}}
                <x-ui.alert variant="warning">Livraison momentanément indisponible pour votre panier. Contactez-nous pour finaliser votre commande.</x-ui.alert>
            @else
                <div class="flex justify-between"><span>Frais de port</span><span>{{ $totals['shipping_ttc'] === 0 ? 'Offerts' : number_format($totals['shipping_ttc'] / 100, 2, ',', ' ').' €' }}</span></div>
                <div class="flex justify-between text-base font-semibold"><span>Total</span><span>{{ number_format($totals['total_ttc'] / 100, 2, ',', ' ') }} €</span></div>
                @if ($totals['planned_ship_date'])
                    <p class="text-xs text-ink-muted">📦 Expédition prévue le {{ app(\App\Services\Shipping\ShippingDateCalculator::class)->formatFrench($totals['planned_ship_date']) }}</p>
                @endif
            @endif

            <x-ui.button variant="primary" href="{{ route('panier') }}" class="mt-3 w-full justify-center">
                Voir mon panier
            </x-ui.button>
        </div>
    @endif
</div>
