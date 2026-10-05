<x-layouts.app :title="$title">
    <div class="mx-auto max-w-6xl px-4 py-10">
        <x-ui.breadcrumb :items="$activeCategory ? [['label' => $activeCategory->name]] : [['label' => 'Boutique']]" class="mb-6" />

        <h1 class="mb-6 text-2xl font-semibold text-ink">{{ $title }}</h1>

        <div class="mb-8 flex flex-wrap gap-2">
            <x-ui.button variant="{{ $activeCategory ? 'outline' : 'primary' }}" :href="route('boutique.index')" class="px-4 py-2 text-xs">
                Tous les produits
            </x-ui.button>
            @foreach ($categories as $category)
                <x-ui.button
                    variant="{{ $activeCategory?->is($category) ? 'primary' : 'outline' }}"
                    :href="route('content.show', $category)"
                    class="px-4 py-2 text-xs"
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
