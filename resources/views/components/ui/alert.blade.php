@props(['variant' => 'info'])

@php
    $variants = [
        'info' => ['classes' => 'bg-cream-alt text-ink border-ink/10', 'icon' => 'ℹ️'],
        'success' => ['classes' => 'bg-sage/10 text-sage-dark border-sage/20', 'icon' => '✅'],
        'warning' => ['classes' => 'bg-ochre/10 text-ochre border-ochre/20', 'icon' => '⚠️'],
        'danger' => ['classes' => 'bg-red-50 text-red-700 border-red-200', 'icon' => '⛔'],
    ];
    $style = $variants[$variant] ?? $variants['info'];
@endphp

<div role="alert" {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-card border p-4 text-sm '.$style['classes']]) }}>
    <span aria-hidden="true">{{ $style['icon'] }}</span>
    <div>{{ $slot }}</div>
</div>
