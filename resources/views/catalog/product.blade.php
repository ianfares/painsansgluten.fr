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
            <div>
                @php($mainImage = $product->getFirstMediaUrl('main', 'fiche'))
                <div class="aspect-square overflow-hidden rounded-card bg-cream-alt">
                    @if ($mainImage)
                        <img src="{{ $mainImage }}" width="800" height="800" fetchpriority="high" alt="{{ $product->getFirstMedia('main')?->getCustomProperty('alt') ?? $product->name }}" class="h-full w-full object-cover">
                    @else
                        <span class="flex h-full items-center justify-center text-sm text-ink-muted">Photo à venir</span>
                    @endif
                </div>

                @if ($product->getMedia('gallery')->isNotEmpty())
                    <div class="mt-4 grid grid-cols-4 gap-3">
                        @foreach ($product->getMedia('gallery') as $media)
                            <img src="{{ $media->getUrl('thumbnail') }}" width="150" height="150" loading="lazy" alt="{{ $media->getCustomProperty('alt') ?? '' }}" class="aspect-square rounded-card object-cover">
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-4">
                <h1 class="text-2xl font-semibold text-ink">{{ $product->name }}</h1>

                @if ($product->short_description)
                    <p class="text-ink-muted">{{ $product->short_description }}</p>
                @endif

                <div class="text-2xl font-semibold text-ink">{{ number_format($product->price_ttc / 100, 2, ',', ' ') }} €</div>

                @if ($product->is_shippable)
                    <div><x-ui.badge variant="shippable">Livraison possible</x-ui.badge></div>
                @endif

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
                    @if ($product->description)
                        <x-ui.accordion title="Description">
                            <div class="prose prose-sm max-w-none">{!! $product->description !!}</div>
                        </x-ui.accordion>
                    @endif

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

                    @if ($product->packaging || $product->net_weight_g)
                        <x-ui.accordion title="Conditionnement">
                            @if ($product->net_weight_g)<p>Poids net : {{ $product->net_weight_g }} g</p>@endif
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
