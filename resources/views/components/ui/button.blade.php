@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'submit',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-button px-6 py-3 text-sm font-semibold transition shadow-button focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sage disabled:opacity-50 disabled:cursor-not-allowed';

    $variants = [
        'primary' => 'bg-sage text-white hover:bg-sage-dark',
        'secondary' => 'bg-ochre text-white hover:brightness-95',
        'outline' => 'bg-transparent text-ink border border-ink/20 hover:bg-cream-alt shadow-none',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
