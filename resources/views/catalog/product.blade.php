@php
    $allergenLabels = fn (array $values) => collect(\App\Enums\Allergen::options())
        ->whereIn('value', $values)
        ->pluck('label')
        ->implode(', ');
@endphp

@php
    $seoData = app(\App\Services\Seo\StructuredData::class);
    $mainMedia = $product->getFirstMedia('main');
    $seoSchema = [
        $seoData->product($product),
        $seoData->breadcrumb([
            ['label' => $product->category->name, 'url' => route('content.show', $product->category)],
            ['label' => $product->name, 'url' => route('products.show', $product)],
        ]),
    ];
@endphp

<x-layouts.app
    :title="$product->seo_title ?: $product->name"
    :description="$product->seo_description ?: $seoData->plainText($product->short_description ?: $product->description, 160)"
    :image="$mainMedia?->getUrl('fiche')"
    og-type="product"
    :schema="$seoSchema"
>
    <div class="mx-auto max-w-6xl px-4 py-10">
        <x-ui.breadcrumb :items="[
            ['label' => $product->category->name, 'url' => route('content.show', $product->category)],
            ['label' => $product->name],
        ]" class="mb-6" />

        <div class="grid gap-10 lg:grid-cols-2">
            @php
                // Galerie (T26) : photo principale + galerie ; clic = plein écran.
                // « zoom » (1600 px) si générée, sinon « fiche » : jamais l'original.
                $photos = collect([$product->getFirstMedia('main')])->merge($product->getMedia('gallery'))->filter()->values()
                    ->map(fn ($media) => [
                        'fiche' => $media->getUrl('fiche'),
                        'zoom' => $media->hasGeneratedConversion('zoom') ? $media->getUrl('zoom') : $media->getUrl('fiche'),
                        'thumb' => $media->getUrl('thumbnail'),
                        'alt' => $media->getCustomProperty('alt') ?? $product->name,
                    ]);
            @endphp
            <div
                x-data="{ photos: @js($photos), current: 0, open: false,
                    show(i) { this.current = (i + this.photos.length) % this.photos.length } }"
                x-on:keydown.escape.window="open = false"
                x-on:keydown.arrow-right.window="open && show(current + 1)"
                x-on:keydown.arrow-left.window="open && show(current - 1)"
            >
                <div class="aspect-square overflow-hidden rounded-card bg-cream-alt">
                    @if ($photos->isNotEmpty())
                        <button type="button" class="block h-full w-full cursor-zoom-in" x-on:click="open = true" aria-label="Agrandir la photo">
                            <img src="{{ $photos[0]['fiche'] }}" x-bind:src="photos[current].fiche" x-bind:alt="photos[current].alt"
                                 width="800" height="800" fetchpriority="high" alt="{{ $photos[0]['alt'] }}" class="h-full w-full object-cover">
                        </button>
                    @else
                        <span class="flex h-full items-center justify-center text-sm text-ink-muted">Photo à venir</span>
                    @endif
                </div>

                @if ($photos->isNotEmpty())
                    {{-- Demande d'Ian (09/10/2026) : garantir l'authenticité des photos. --}}
                    <p class="mt-2 text-center text-xs text-ink-muted">📷 Photos originales de nos produits, non traitées par l'IA.</p>
                @endif

                @if ($photos->count() > 1)
                    <div class="mt-4 grid grid-cols-4 gap-3 sm:grid-cols-5">
                        @foreach ($photos as $i => $photo)
                            <button type="button" x-on:click="show({{ $i }})" aria-label="Voir la photo {{ $i + 1 }}"
                                    class="overflow-hidden rounded-card ring-2 ring-transparent transition hover:ring-ochre focus-visible:ring-ochre"
                                    x-bind:class="current === {{ $i }} && 'ring-sage!'">
                                <img src="{{ $photo['thumb'] }}" width="150" height="150" loading="lazy" alt="{{ $photo['alt'] }}" class="aspect-square w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Visionneuse plein écran --}}
                <template x-if="open">
                    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4" role="dialog" aria-modal="true" aria-label="Photos du produit" x-on:click.self="open = false">
                        <img x-bind:src="photos[current].zoom" x-bind:alt="photos[current].alt" class="max-h-full max-w-full rounded-card object-contain">
                        <button type="button" class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-xl text-ink" x-on:click="open = false" aria-label="Fermer">✕</button>
                        <template x-if="photos.length > 1">
                            <div>
                                <button type="button" class="absolute left-4 top-1/2 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-2xl text-ink" x-on:click="show(current - 1)" aria-label="Photo précédente">‹</button>
                                <button type="button" class="absolute right-4 top-1/2 flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-2xl text-ink" x-on:click="show(current + 1)" aria-label="Photo suivante">›</button>
                                <p class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-white/90 px-3 py-1 text-sm text-ink" x-text="(current + 1) + ' / ' + photos.length"></p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="flex flex-col gap-4">
                <h1 class="text-2xl font-semibold text-ink">{{ $product->name }}</h1>

                @if ($product->short_description)
                    <p class="text-ink-muted">{{ $product->short_description }}</p>
                @endif

                @if ($product->description)
                    @if ($product->short_description)
                        <details class="group text-ink">
                            <summary class="inline-flex cursor-pointer list-none items-center gap-1 text-sm font-medium text-sage transition hover:text-ochre focus-visible:text-ochre [&::-webkit-details-marker]:hidden">
                                Lire la suite
                                <svg class="h-4 w-4 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M5 8l5 5 5-5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </summary>
                            <div class="prose prose-sm max-w-none pt-2">{!! $product->description !!}</div>
                        </details>
                    @else
                        <div class="prose prose-sm max-w-none">{!! $product->description !!}</div>
                    @endif
                @endif

                <div>
                    <div class="text-2xl font-semibold text-ink">{{ number_format($product->price_ttc / 100, 2, ',', ' ') }} € <span class="text-sm font-normal text-ink-muted">TTC</span></div>
                    @if ($product->formattedNetWeight())
                        @php
                            $pricePerKg = $product->pricePerKgTtc();
                            $netWeightLine = 'Poids net : '.$product->formattedNetWeight()
                                .($pricePerKg !== null ? ' - '.number_format($pricePerKg / 100, 2, ',', ' ').' €/kg' : '');
                        @endphp
                        <p class="text-sm text-ink-muted">{{ $netWeightLine }}</p>
                    @endif
                </div>

                @if ($product->isOrderable())
                    <livewire:add-to-cart-button :product="$product" :with-quantity="true" />
                @elseif (! $product->is_shippable)
                    <x-ui.alert variant="warning">{{ $shippingSettings->non_shippable_message }}</x-ui.alert>
                @else
                    <div><x-ui.badge variant="unavailable">Indisponible</x-ui.badge></div>
                @endif

                @if ($product->is_shippable)
                    <x-ui.alert variant="info">{{ $shippingSettings->relay_pickup_message }}</x-ui.alert>
                @endif

                <div class="mt-4 divide-y divide-line border-y border-line">
                    @if ($product->ingredients)
                        <x-ui.accordion title="Ingrédients" :open="true">
                            <div class="prose prose-sm max-w-none">{!! $product->ingredients !!}</div>
                        </x-ui.accordion>
                    @endif

                    @if (! empty($product->allergens_contains) || ! empty($product->allergens_traces) || $product->allergen_note)
                        <x-ui.accordion title="Allergènes">
                            @if (! empty($product->allergens_contains))
                                <p><span class="font-medium">Contient :</span> {{ $allergenLabels($product->allergens_contains) }}</p>
                            @endif
                            @if (! empty($product->allergens_traces))
                                <p><span class="font-medium">Traces possibles :</span> {{ $allergenLabels($product->allergens_traces) }}</p>
                            @endif
                            @if ($product->allergen_note)
                                <p class="mt-1 text-ink-muted">{{ $product->allergen_note }}</p>
                            @endif
                        </x-ui.accordion>
                    @endif

                    @if ($product->nutrition)
                        <x-ui.accordion title="Valeurs nutritionnelles (pour 100 g)">
                            <table class="w-full">
                                @foreach (['kcal' => 'Énergie (kcal)', 'kj' => 'Énergie (kJ)', 'fat' => 'Matières grasses', 'saturated' => 'dont acides gras saturés', 'carbs' => 'Glucides', 'sugars' => 'dont sucres', 'fiber' => 'Fibres', 'protein' => 'Protéines', 'salt' => 'Sel'] as $key => $label)
                                    @continue(! isset($product->nutrition[$key]))
                                    <tr class="border-b border-line">
                                        <th class="py-1 text-left font-normal text-ink-muted">{{ $label }}</th>
                                        <td class="py-1 text-right">{{ $product->nutrition[$key] }}{{ in_array($key, ['kcal', 'kj']) ? '' : ' g' }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </x-ui.accordion>
                    @endif

                    @if ($product->packaging || $product->sale_unit)
                        <x-ui.accordion title="Conditionnement">
                            @if ($product->packaging)<p>{{ $product->packaging }}</p>@endif
                            @if ($product->sale_unit)<p>{{ $product->sale_unit }}</p>@endif
                        </x-ui.accordion>
                    @endif

                    @if ($product->tasting_tips)
                        <x-ui.accordion title="Conseils de dégustation">
                            <p>{{ $product->tasting_tips }}</p>
                        </x-ui.accordion>
                    @endif

                    @if ($product->storage || $product->shelf_life)
                        <x-ui.accordion title="Conservation">
                            @if ($product->storage)<p>{{ $product->storage }}</p>@endif
                            @if ($product->shelf_life)<p class="text-ink-muted">{{ $product->shelf_life }}</p>@endif
                        </x-ui.accordion>
                    @endif

                    @if (filled($shippingSettings->shipping_block_text))
                        <x-ui.accordion title="Expédition et livraison">
                            <div class="space-y-3">
                                @foreach (preg_split('/\R{2,}/', trim($shippingSettings->shipping_block_text)) as $paragraph)
                                    <p>{!! nl2br(e($paragraph)) !!}</p>
                                @endforeach
                            </div>
                        </x-ui.accordion>
                    @endif
                </div>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <div class="mt-16">
                <h2 class="mb-6 text-xl font-semibold text-ink">Vous aimerez aussi</h2>
                <div class="grid grid-cols-2 gap-6 sm:grid-cols-4">
                    @foreach ($related as $relatedProduct)
                        <x-ui.product-card :product="$relatedProduct" />
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
