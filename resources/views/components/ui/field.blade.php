{{-- Enveloppe label + champ + aide + erreur. Le champ lui-même est injecté via le slot. --}}
@props(['name', 'label' => null, 'hint' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1']) }}>
    @if ($label)
        <label for="{{ $name }}" class="text-sm font-medium text-ink">{{ $label }}</label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="text-xs text-ink-muted">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
