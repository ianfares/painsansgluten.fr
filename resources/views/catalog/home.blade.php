@php($homepage = $homepageSettings)

<x-layouts.app>
    {{-- Bannière --}}
    <div class="bg-cream-alt px-4 py-20 text-center">
        @if ($homepage->banner_title)
            <h1 class="text-4xl font-semibold text-ink">{{ $homepage->banner_title }}</h1>
        @else
            <h1 class="text-4xl font-semibold text-ink">Boulangerie &amp; créations artisanales 100&nbsp;% sans gluten</h1>
        @endif

        @if ($homepage->banner_subtitle)
            <p class="mx-auto mt-4 max-w-xl text-ink-muted">{{ $homepage->banner_subtitle }}</p>
        @endif

        <div class="mt-8">
            <x-ui.button variant="primary" :href="route('boutique.index')">
                {{ $homepage->banner_button_text ?: 'Découvrir la boutique' }}
            </x-ui.button>
        </div>
    </div>

    {{-- Texte de présentation --}}
    @if ($homepage->presentation_text)
        <div class="mx-auto max-w-3xl px-4 py-12 text-center text-ink-muted">
            {{ $homepage->presentation_text }}
        </div>
    @endif

    {{-- Catégories --}}
    @if ($categories->isNotEmpty())
        <div class="mx-auto max-w-6xl px-4 py-12">
            <h2 class="mb-6 text-xl font-semibold text-ink">Nos catégories</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ($categories as $category)
                    <a href="{{ route('content.show', $category) }}" class="flex flex-col items-center gap-3 rounded-card border border-line bg-white p-6 text-center hover:border-sage">
                        <span class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-cream-alt">
                            @if ($url = $category->getFirstMediaUrl('cover', 'menu'))
                                <img src="{{ $url }}" alt="" class="h-full w-full object-cover">
                            @else
                                <span aria-hidden="true" class="text-2xl">🥖</span>
                            @endif
                        </span>
                        <span class="font-medium text-ink">{{ $category->name }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Produits mis en avant --}}
    @if ($featured->isNotEmpty())
        <div class="mx-auto max-w-6xl px-4 py-12">
            <h2 class="mb-6 text-xl font-semibold text-ink">Nos créations du moment</h2>
            <div class="grid grid-cols-2 gap-6 sm:grid-cols-4">
                @foreach ($featured as $product)
                    <x-ui.product-card :product="$product" />
                @endforeach
            </div>
        </div>
    @endif

    {{-- Info livraison --}}
    <div class="bg-sage px-4 py-12 text-center text-white">
        <div class="mx-auto grid max-w-4xl gap-8 sm:grid-cols-3">
            <div>
                <p class="text-2xl" aria-hidden="true">🍞</p>
                <p class="mt-2 font-medium">Fabrication à la commande</p>
            </div>
            <div>
                <p class="text-2xl" aria-hidden="true">📦</p>
                <p class="mt-2 font-medium">Livraison Chronopost Relais</p>
            </div>
            <div>
                <p class="text-2xl" aria-hidden="true">🔒</p>
                <p class="mt-2 font-medium">Paiement sécurisé</p>
            </div>
        </div>
    </div>
</x-layouts.app>
