{{-- Carte produit (grille boutique/catégorie, "vous aimerez aussi" — PLAN.md §6.3). --}}
@props(['product'])

@php
    $orderable = $product->isOrderable();
    $url = \Illuminate\Support\Facades\Route::has('products.show')
        ? route('products.show', $product)
        : '#';
    $image = $product->getFirstMediaUrl('main', 'liste');
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden rounded-card border border-line bg-white']) }}>
    <a href="{{ $url }}" class="relative block aspect-square bg-cream-alt">
        @if ($image)
            <img src="{{ $image }}" width="400" height="400" loading="lazy" decoding="async" alt="{{ $product->getFirstMedia('main')?->getCustomProperty('alt') ?? $product->name }}" class="h-full w-full object-cover" loading="lazy" width="400" height="400">
        @else
            <span class="flex h-full items-center justify-center text-xs text-ink-muted">Photo à venir</span>
        @endif

        @unless ($product->is_available)
            <span class="absolute inset-0 flex items-center justify-center bg-cream/85">
                <x-ui.badge variant="unavailable">Indisponible</x-ui.badge>
            </span>
        @endunless
    </a>

    <div class="flex flex-1 flex-col gap-2 p-4">
        <span class="text-xs uppercase tracking-wide text-ochre">{{ $product->category->name }}</span>
        <a href="{{ $url }}" class="font-medium text-ink hover:text-sage">{{ $product->name }}</a>

        <div class="mt-auto flex items-center justify-between gap-2">
            <span class="font-semibold text-ink">{{ number_format($product->price_ttc / 100, 2, ',', ' ') }} €</span>

            @if ($orderable)
                <livewire:add-to-cart-button :product="$product" :key="'add-'.$product->id" />
            @elseif (! $product->is_shippable)
                <x-ui.badge variant="warning">Non expédiable</x-ui.badge>
            @else
                <x-ui.badge variant="unavailable">Indisponible</x-ui.badge>
            @endif
        </div>
    </div>
</div>
