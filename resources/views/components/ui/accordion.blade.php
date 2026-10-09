@props([
    'title',
    'open' => false,
])

{{-- Accordéon natif (T26 A4) : accessible, sans JavaScript, contenu indexable. --}}
<details{{ $open ? ' open' : '' }} {{ $attributes->merge(['class' => 'group py-1']) }}>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 py-3 font-semibold text-sage transition hover:text-ochre focus-visible:text-ochre [&::-webkit-details-marker]:hidden">
        <h2>{{ $title }}</h2>
        <svg class="h-4 w-4 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M5 8l5 5 5-5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </summary>
    <div class="pb-4 text-sm text-ink">
        {{ $slot }}
    </div>
</details>
