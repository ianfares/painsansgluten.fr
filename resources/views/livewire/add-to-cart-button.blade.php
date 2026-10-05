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
        class="inline-flex items-center justify-center gap-2 rounded-button bg-sage px-4 py-2 text-xs font-semibold text-white shadow-button hover:bg-sage-dark disabled:opacity-50"
    >
        {{ $added ? 'Ajouté ✓' : 'Ajouter au panier' }}
    </button>
</div>
