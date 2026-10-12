<x-layouts.app title="Votre panier">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <x-ui.breadcrumb :items="[['label' => 'Panier']]" class="mb-6" />
        <h1 class="mb-6 text-2xl font-semibold text-ink">Votre panier</h1>

        <livewire:cart-widget :on-cart-page="true" />

        @if (app(\App\Services\Analytics\Ecommerce::class)->enabled())
            @php($cartService = app(\App\Services\Cart\CartService::class))
            <x-analytics.event name="view_cart" :data="app(\App\Services\Analytics\Ecommerce::class)->cart($cartService->validItems($cartService->currentCart())['items'], auth()->user())" />
        @endif

        <a href="{{ route('boutique.index') }}" class="mt-6 inline-block text-sm text-ink-muted hover:text-sage">← Continuer mes achats</a>
    </div>
</x-layouts.app>
