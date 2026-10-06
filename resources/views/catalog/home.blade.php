@php($homepage = $homepageSettings)

<x-layouts.app>
    {{-- Bannière --}}
    <div class="relative bg-cream-alt">
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-sage/10 blur-3xl"></div>
            <div class="absolute -right-16 bottom-0 h-64 w-64 rounded-full bg-ochre/10 blur-3xl"></div>
        </div>

        <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-16 sm:py-24 lg:grid-cols-2 lg:py-28">
            <div class="text-center lg:text-left">
                <span class="inline-flex items-center gap-2 rounded-full bg-sage/10 px-4 py-1 text-xs font-semibold uppercase tracking-wide text-sage-dark">
                    🌾 100&nbsp;% sans gluten · fait main à Avranches
                </span>

                <h1 class="mt-6 text-4xl font-semibold leading-tight text-ink sm:text-5xl">
                    {{ $homepage->banner_title ?: 'Boulangerie & créations artisanales 100 % sans gluten' }}
                </h1>

                <p class="mx-auto mt-5 max-w-xl text-lg text-ink-muted lg:mx-0">
                    {{ $homepage->banner_subtitle ?: 'Pains, viennoiseries, pâtisseries et biscuits confectionnés à la commande, livrés près de chez vous en point relais.' }}
                </p>

                <div class="mt-8 flex flex-col items-center gap-4 sm:flex-row sm:justify-center lg:justify-start">
                    <x-ui.button variant="primary" :href="route('boutique.index')" class="w-full sm:w-auto">
                        {{ $homepage->banner_button_text ?: 'Découvrir la boutique' }}
                    </x-ui.button>
                    <a href="{{ route('faq') }}" class="text-sm font-medium text-ink hover:text-sage">Comment ça marche&nbsp;? →</a>
                </div>
            </div>

            <div class="relative mx-auto aspect-square w-full max-w-sm lg:max-w-none">
                @if ($homepage->banner_image_path)
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($homepage->banner_image_path) }}"
                        alt=""
                        class="h-full w-full rounded-card object-cover shadow-drawer"
                    >
                @else
                    <div class="flex h-full w-full flex-col items-center justify-center gap-4 rounded-card bg-gradient-to-br from-white via-cream to-cream-alt shadow-drawer">
                        @if ($homepage->logo_path)
                            <img
                                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($homepage->logo_path) }}"
                                alt=""
                                class="h-36 w-36 rounded-full object-cover shadow-button sm:h-44 sm:w-44"
                            >
                        @else
                            <span class="text-8xl" aria-hidden="true">🥖</span>
                        @endif
                        <p class="text-sm font-medium text-ink-muted">Photos de nos créations à venir</p>
                    </div>
                @endif

                <span class="absolute -bottom-4 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-card bg-sage px-4 py-3 text-sm font-medium text-white shadow-button lg:left-auto lg:right-6 lg:translate-x-0">
                    📦 Expédié frais, à retirer en relais
                </span>
            </div>
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
