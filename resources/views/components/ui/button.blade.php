@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'submit',
])

@php
    // Style unique des boutons : classes .btn dans resources/css/app.css (T26 A2).
    $classes = $variant === 'outline' ? 'btn btn-outline' : 'btn';
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
