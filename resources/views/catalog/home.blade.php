@php($homepage = $homepageSettings)

<x-layouts.app :schema="[app(\App\Services\Seo\StructuredData::class)->organization()]">
    {{-- Bannière --}}
    <div class="relative bg-cream-alt">
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -left-24 -top-24 h-72 w-72 rounded-full bg-sage/10 blur-3xl"></div>
            <div class="absolute -right-16 bottom-0 h-64 w-64 rounded-full bg-ochre/10 blur-3xl"></div>
        </div>

        <div class="relative mx-auto grid max-w-6xl items-center gap-8 px-4 py-8 sm:py-10 lg:grid-cols-2 lg:py-14">
            <div class="text-center lg:text-left">
                <span class="inline-flex items-center gap-2 rounded-full bg-sage/10 px-4 py-1 text-xs font-semibold uppercase tracking-wide text-sage-dark">
                    🌾 100&nbsp;% sans gluten · fait main à Avranches
                </span>

                <h1 class="mt-4 text-3xl font-semibold leading-tight text-ink sm:text-4xl">
                    {{ $homepage->banner_title ?: 'Boulangerie & créations artisanales 100 % sans gluten' }}
                </h1>

                <p class="mx-auto mt-3 max-w-xl text-base text-ink-muted lg:mx-0">
                    {{ $homepage->banner_subtitle ?: 'Pains, viennoiseries, pâtisseries et biscuits confectionnés à la commande, livrés près de chez vous en point relais.' }}
                </p>

                <div class="mt-6 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start">
                    <x-ui.button variant="primary" :href="route('boutique.index')" class="w-full sm:w-auto">
                        {{ $homepage->banner_button_text ?: 'Découvrir la boutique' }}
                    </x-ui.button>
                    <a href="{{ route('faq') }}" class="text-sm font-medium text-ink hover:text-sage">Comment ça marche&nbsp;? →</a>
                </div>

                {{-- Les 3 canaux de vente de la boulangerie (marchés, vente en ligne, professionnels — cf. docs/reference/audit-painsansgluten.html). --}}
                <div class="mt-6 flex flex-wrap items-center justify-center gap-2 lg:justify-start">
                    <a href="{{ route('boutique.index') }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-ink shadow-sm transition hover:shadow-button">
                        🛒 Commande en ligne
                    </a>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-ink shadow-sm">
                        🧺 Sur les marchés
                    </span>
                    @if ($shopSettings->contact_email)
                        <a href="mailto:{{ $shopSettings->contact_email }}?subject=Demande%20professionnels" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-ink shadow-sm transition hover:shadow-button">
                            🤝 Professionnels
                        </a>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-ink shadow-sm">
                            🤝 Professionnels
                        </span>
                    @endif
                </div>
            </div>

            <div class="relative mx-auto aspect-[4/3] w-full max-w-xs lg:max-w-sm">
                @if ($homepage->banner_image_path)
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($homepage->banner_image_path) }}"
                        alt=""
                        class="h-full w-full rounded-card object-cover shadow-drawer"
                    >
                @else
                    <div class="flex h-full w-full flex-col items-center justify-center gap-3 rounded-card bg-gradient-to-br from-white via-cream to-cream-alt p-5 shadow-drawer">
                        <div class="grid grid-cols-2 gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-sage/10 text-2xl sm:h-14 sm:w-14">🥖</span>
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-ochre/10 text-2xl sm:h-14 sm:w-14">🥐</span>
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-ochre/10 text-2xl sm:h-14 sm:w-14">🍪</span>
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-sage/10 text-2xl sm:h-14 sm:w-14">🍰</span>
                        </div>
                        <p class="text-xs font-medium text-ink-muted">Photos de nos créations à venir</p>
                    </div>
                @endif

                <span class="absolute -bottom-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-card bg-sage px-3 py-2 text-xs font-medium text-white shadow-button lg:left-auto lg:right-4 lg:translate-x-0">
                    📦 Expédié frais, à retirer en relais
                </span>
            </div>
        </div>
    </div>

    {{-- Slogan (T26 A9) et texte de présentation --}}
    @if ($homepage->slogan || $homepage->presentation_text)
        <div class="mx-auto max-w-3xl px-4 py-12 text-center">
            @if ($homepage->slogan)
                <p class="text-2xl font-semibold italic text-sage">« {{ $homepage->slogan }} »</p>
            @endif
            @if ($homepage->presentation_text)
                <p class="mt-4 text-ink-muted">{{ $homepage->presentation_text }}</p>
            @endif
        </div>
    @endif

    {{-- Catégories --}}
    @if ($categories->isNotEmpty())
        <div class="mx-auto max-w-6xl px-4 py-12">
            <h2 class="mb-6 text-xl font-semibold text-ink">Nos catégories</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ($categories as $category)
                    <a href="{{ route('content.show', $category) }}" class="group flex flex-col items-center gap-3 rounded-card border border-line bg-white p-6 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-sage hover:shadow-drawer">
                        <span class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-cream-alt to-sage/15 text-3xl transition group-hover:scale-105">
                            @if ($url = $category->getFirstMediaUrl('cover', 'menu'))
                                <img src="{{ $url }}" alt="" width="200" height="200" loading="lazy" class="h-full w-full object-cover">
                            @else
                                <span aria-hidden="true">{{ $category->fallbackIcon() }}</span>
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

    {{-- Engagements (livraison, sans gluten, congélation, fabrication à la commande) --}}
    <x-site.reassurance />
</x-layouts.app>
