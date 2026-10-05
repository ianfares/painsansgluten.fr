{{-- Modale générique, déclenchée par `$dispatch('open-modal-{{ $name }}')`. --}}
@props(['name', 'title' => null])

<div
    x-data="{ open: false }"
    x-on:open-modal-{{ $name }}.window="open = true"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-label="{{ $title }}"
>
    <div x-show="open" x-transition.opacity x-on:click="open = false" class="absolute inset-0 bg-black/40"></div>

    <div
        x-show="open"
        x-transition
        class="relative w-full max-w-lg rounded-popover bg-white p-6 shadow-drawer"
    >
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-semibold text-ink">{{ $title }}</h2>
            <button type="button" x-on:click="open = false" aria-label="Fermer" class="text-ink-muted hover:text-ink">✕</button>
        </div>

        {{ $slot }}
    </div>
</div>
