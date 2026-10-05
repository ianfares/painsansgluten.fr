@props(['variant' => 'neutral'])

@php
    $variants = [
        'neutral' => 'bg-cream-alt text-ink',
        'unavailable' => 'bg-ink text-white',
        'success' => 'bg-sage/10 text-sage-dark',
        'warning' => 'bg-ochre/10 text-ochre',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold '.($variants[$variant] ?? $variants['neutral'])]) }}>
    {{ $slot }}
</span>
