@php
    $seoData = app(\App\Services\Seo\StructuredData::class);
    $seoTitle = $activeCategory?->seo_title ?: $title;
    $seoDescription = $activeCategory
        ? ($activeCategory->seo_description ?: $activeCategory->description ?: "{$activeCategory->name} 100 % sans gluten, fabriqués à la commande à Avranches et livrés en point relais Chronopost.")
        : 'Pains, viennoiseries, pâtisseries et biscuits 100 % sans gluten, fabriqués à la commande à Avranches et livrés en point relais Chronopost.';
    $seoSchema = $activeCategory
        ? [$seoData->breadcrumb([['label' => $activeCategory->name, 'url' => route('content.show', $activeCategory)]])]
        : [$seoData->breadcrumb([['label' => 'Boutique', 'url' => route('boutique.index')]])];
@endphp

<x-layouts.app :title="$seoTitle" :description="$seoDescription" :schema="$seoSchema">
    <div class="mx-auto max-w-6xl px-4 py-10">
        <x-ui.breadcrumb :items="$activeCategory ? [['label' => $activeCategory->name]] : [['label' => 'Boutique']]" class="mb-6" />

        <h1 class="mb-6 text-2xl font-semibold text-ink">{{ $title }}</h1>

        @if ($activeCategory?->description)
            <p class="-mt-2 mb-8 max-w-3xl text-sm leading-relaxed text-ink-muted">{!! nl2br(e($activeCategory->description)) !!}</p>
        @endif

        <div class="mb-8 flex flex-wrap gap-2">
            <x-ui.button variant="{{ $activeCategory ? 'outline' : 'primary' }}" :href="route('boutique.index')" class="btn-sm">
                Tous les produits
            </x-ui.button>
            @foreach ($categories as $category)
                <x-ui.button
                    variant="{{ $activeCategory?->is($category) ? 'primary' : 'outline' }}"
                    :href="route('content.show', $category)"
                    class="btn-sm"
                >
                    {{ $category->name }}
                </x-ui.button>
            @endforeach
        </div>

        @if ($products->isEmpty())
            <x-ui.alert variant="info">Aucun produit disponible pour le moment.</x-ui.alert>
        @else
            <div class="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    <x-ui.product-card :product="$product" />
                @endforeach
            </div>

            <div class="mt-10">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>
