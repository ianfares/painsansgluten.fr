@props(['name', 'type' => 'text'])

@php
    $value = $attributes->get('value', old($name));
@endphp

<input
    type="{{ $type }}"
    name="{{ $name }}"
    id="{{ $name }}"
    value="{{ $value }}"
    {{ $attributes->except('value')->merge([
        'class' => 'w-full rounded-field border px-3 py-2 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-sage '
            .($errors->has($name) ? 'border-red-400' : 'border-line'),
    ]) }}
/>
