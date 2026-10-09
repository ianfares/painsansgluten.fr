@php
    $allItems = $groups->flatten(1);
@endphp

<x-layouts.app
    title="Questions fréquentes"
    description="Toutes les réponses sur nos pains et pâtisseries 100 % sans gluten : ingrédients, allergènes, conservation, commande et livraison en point relais Chronopost."
    :schema="$allItems->isNotEmpty() ? [app(\App\Services\Seo\StructuredData::class)->faq($allItems)] : []"
>
    <div class="mx-auto max-w-3xl px-4 py-10">
        <x-ui.breadcrumb :items="[['label' => 'FAQ']]" class="mb-6" />
        <h1 class="mb-8 text-2xl font-semibold text-ink">Questions fréquentes</h1>

        @if ($allItems->isEmpty())
            <x-ui.alert variant="info">La FAQ sera bientôt disponible.</x-ui.alert>
        @endif

        @foreach ($groups as $groupName => $items)
            <h2 class="mb-4 mt-8 text-lg font-semibold text-ink first:mt-0">{{ $groupName }}</h2>
            <div class="flex flex-col gap-3">
                @foreach ($items as $item)
                    <div x-data="{ open: false }" class="rounded-card border border-line bg-white">
                        <button
                            type="button"
                            x-on:click="open = ! open"
                            class="flex w-full items-center justify-between p-4 text-left font-medium text-ink"
                            :aria-expanded="open"
                        >
                            {{ $item->question }}
                            <span x-text="open ? '−' : '+'" aria-hidden="true"></span>
                        </button>
                        <div x-show="open" x-cloak class="prose prose-sm max-w-none border-t border-line p-4">
                            {!! $item->answer !!}
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

</x-layouts.app>
