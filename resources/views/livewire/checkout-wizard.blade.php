<div>
    @if ($empty)
        <x-ui.alert variant="info">
            Votre panier est vide. <a href="{{ route('boutique.index') }}" class="underline">Découvrir la boutique</a>
        </x-ui.alert>
    @else
        <ol class="mb-8 flex items-center gap-2 text-xs font-medium text-ink-muted">
            @foreach (['Coordonnées', 'Relais', 'Récapitulatif', 'Paiement'] as $i => $label)
                <li class="flex flex-1 items-center gap-2">
                    <span @class([
                        'flex h-6 w-6 shrink-0 items-center justify-center rounded-full',
                        'bg-sage text-white' => $step > $i + 1,
                        'bg-ink text-white' => $step === $i + 1,
                        'border border-line text-ink-muted' => $step < $i + 1,
                    ])>{{ $i + 1 }}</span>
                    <span class="hidden sm:inline {{ $step === $i + 1 ? 'text-ink' : '' }}">{{ $label }}</span>
                    @if ($i < 3)
                        <span class="h-px flex-1 bg-line"></span>
                    @endif
                </li>
            @endforeach
        </ol>

        @if ($error)
            <x-ui.alert variant="danger" class="mb-6">{{ $error }}</x-ui.alert>
        @endif

        @if ($step === 1)
            <div class="flex flex-col gap-4">
                <x-ui.field name="email" label="Email">
                    <x-ui.input name="email" type="email" wire:model="email" />
                </x-ui.field>
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.field name="first_name" label="Prénom">
                        <x-ui.input name="first_name" wire:model="first_name" />
                    </x-ui.field>
                    <x-ui.field name="last_name" label="Nom">
                        <x-ui.input name="last_name" wire:model="last_name" />
                    </x-ui.field>
                </div>
                <x-ui.field name="phone" label="Téléphone mobile" hint="Format 06/07 ou +336/+337 — utilisé pour la livraison en point relais.">
                    <x-ui.input name="phone" wire:model="phone" />
                </x-ui.field>
                <x-ui.field name="billing_line1" label="Adresse de facturation">
                    <x-ui.input name="billing_line1" wire:model="billing_line1" />
                </x-ui.field>
                <div class="grid grid-cols-2 gap-4">
                    <x-ui.field name="billing_postal_code" label="Code postal">
                        <x-ui.input name="billing_postal_code" wire:model="billing_postal_code" />
                    </x-ui.field>
                    <x-ui.field name="billing_city" label="Ville">
                        <x-ui.input name="billing_city" wire:model="billing_city" />
                    </x-ui.field>
                </div>

                <x-ui.button type="button" wire:click="goToStep(2)" class="mt-2 w-full justify-center">Suivant</x-ui.button>
            </div>
        @endif

        @if ($step === 2)
            <div class="flex flex-col gap-4">
                <x-ui.alert variant="warning">
                    La recherche de points relais est en cours de finalisation. Indiquez ci-dessous le relais Chronopost de votre choix (nom et ville) : nous vous confirmerons sa disponibilité par email avant expédition.
                </x-ui.alert>

                <x-ui.field name="relay_postal_code" label="Code postal du relais">
                    <x-ui.input name="relay_postal_code" wire:model="relay_postal_code" />
                </x-ui.field>
                <x-ui.field name="relay_name" label="Nom et ville du relais souhaité">
                    <x-ui.input name="relay_name" wire:model="relay_name" />
                </x-ui.field>

                <div class="mt-2 flex gap-3">
                    <x-ui.button type="button" variant="outline" wire:click="goToStep(1)" class="flex-1 justify-center">Précédent</x-ui.button>
                    <x-ui.button type="button" wire:click="goToStep(3)" class="flex-1 justify-center">Suivant</x-ui.button>
                </div>
            </div>
        @endif

        @if ($step === 3)
            <div class="flex flex-col gap-4">
                <div class="divide-y divide-line rounded-card border border-line bg-white">
                    @foreach ($items as $item)
                        <div class="flex items-center justify-between p-3 text-sm" wire:key="recap-item-{{ $item->id }}">
                            <span>{{ $item->product->name }} × {{ $item->quantity }}</span>
                            <span>{{ number_format(\App\Services\Pricing\CustomerPricing::unitNet($item->product->price_ttc, $totals['discount_percent']) * $item->quantity / 100, 2, ',', ' ') }} €</span>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-col gap-1 text-sm">
                    <div class="flex justify-between"><span>Sous-total TTC</span><span>{{ number_format(($totals['subtotal_ttc'] + $totals['discount_ttc']) / 100, 2, ',', ' ') }} €</span></div>
                    @if ($totals['discount_ttc'] > 0)
                        <div class="flex justify-between text-sage-dark"><span>Votre remise -{{ $totals['discount_percent'] }} %</span><span>-{{ number_format($totals['discount_ttc'] / 100, 2, ',', ' ') }} €</span></div>
                    @endif

                    @if ($totals['shipping_error'])
                        <x-ui.alert variant="danger">Livraison momentanément indisponible pour votre commande. Contactez-nous pour la finaliser.</x-ui.alert>
                    @else
                        <div class="flex justify-between gap-4"><span>{{ app(\App\Settings\ShippingSettings::class)->carrier_label ?: 'Frais de port' }}</span><span class="whitespace-nowrap">{{ $totals['shipping_ttc'] === 0 ? 'Offerts' : number_format($totals['shipping_ttc'] / 100, 2, ',', ' ').' €' }}</span></div>
                        <div class="flex justify-between text-base font-semibold"><span>Total</span><span>{{ number_format($totals['total_ttc'] / 100, 2, ',', ' ') }} €</span></div>
                        @if ($totals['planned_ship_date'])
                            <p class="text-xs text-ink-muted">📦 Expédition prévue le {{ app(\App\Services\Shipping\ShippingDateCalculator::class)->formatFrench($totals['planned_ship_date']) }}</p>
                        @endif
                    @endif
                </div>

                <div class="mt-2 flex gap-3">
                    <x-ui.button type="button" variant="outline" wire:click="goToStep(2)" class="flex-1 justify-center">Précédent</x-ui.button>
                    @if (! $totals['shipping_error'])
                        <x-ui.button type="button" wire:click="goToStep(4)" class="flex-1 justify-center">Suivant</x-ui.button>
                    @endif
                </div>
            </div>
        @endif

        @if ($step === 4)
            <div class="flex flex-col gap-4">
                <div>
                    <p class="mb-2 text-sm font-medium text-ink">Moyen de paiement</p>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" wire:model="paymentMethod" value="stripe"> Carte bancaire (Stripe)
                    </label>
                    <label class="mt-1 flex items-center gap-2 text-sm">
                        <input type="radio" wire:model="paymentMethod" value="bank_transfer"> Virement bancaire
                    </label>
                    @error('paymentMethod')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" wire:model="cgvAccepted" class="mt-1">
                    <span>
                        J'accepte les <a href="{{ route('content.show', 'cgv') }}" target="_blank" rel="noopener" class="underline">conditions générales de vente</a>.
                        Conformément à l'article L221-28 du Code de la consommation, le droit de rétractation ne s'applique pas aux denrées périssables confectionnées à la demande (pains et pâtisseries sans gluten préparés sur commande).
                    </span>
                </label>
                @error('cgvAccepted')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror

                <div class="mt-2 flex gap-3">
                    <x-ui.button type="button" variant="outline" wire:click="goToStep(3)" class="flex-1 justify-center">Précédent</x-ui.button>
                    <x-ui.button type="button" wire:click="pay" wire:loading.attr="disabled" class="flex-1 justify-center">Payer</x-ui.button>
                </div>
            </div>
        @endif
    @endif
</div>
