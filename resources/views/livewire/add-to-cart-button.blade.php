<div class="flex items-center gap-3">
    @if ($withQuantity)
        <div class="flex items-center gap-2">
            <button type="button" wire:click="decrementQuantity" class="h-8 w-8 rounded-full border border-line text-sm" aria-label="Diminuer la quantité">−</button>
            <span class="w-6 text-center text-sm">{{ $quantity }}</span>
            <button type="button" wire:click="incrementQuantity" class="h-8 w-8 rounded-full border border-line text-sm" aria-label="Augmenter la quantité">+</button>
        </div>
    @endif

    <button
        type="button"
        wire:click="add"
        wire:loading.attr="disabled"
        class="btn btn-sm"
        {{-- Nom du produit dans le libellé : 24 boutons identiques sinon pour un lecteur d'écran. --}}
        aria-label="{{ ($added ? 'Ajouté au panier : ' : 'Ajouter au panier : ').$product->name }}"
    >
        {{ $added ? 'Ajouté ✓' : 'Ajouter au panier' }}
    </button>
    <span class="sr-only" role="status" aria-live="polite">{{ $added ? $product->name.' ajouté au panier.' : '' }}</span>
</div>
