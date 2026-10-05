@php
    $allItems = $groups->flatten(1);
@endphp

<x-layouts.app title="FAQ">
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

    {{-- Données structurées FAQPage (PLAN.md §16.2, §17) --}}
    @if ($allItems->isNotEmpty())
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $allItems->map(fn ($item) => [
                    '@type' => 'Question',
                    'name' => $item->question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => strip_tags($item->answer),
                    ],
                ])->values()->all(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endif
</x-layouts.app>
