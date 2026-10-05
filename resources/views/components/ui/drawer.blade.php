{{--
    Tiroir latéral générique (panier T08, filtres...). Déclenché par
    `$dispatch('open-drawer-{{ $name }}')` depuis n'importe quel composant Alpine.
--}}
@props(['name', 'title' => null])

<div
    x-data="{ open: false }"
    x-on:open-drawer-{{ $name }}.window="open = true"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50"
    role="dialog"
    aria-modal="true"
    aria-label="{{ $title }}"
>
    <div
        x-show="open"
        x-transition.opacity
        x-on:click="open = false"
        class="absolute inset-0 bg-black/40"
    ></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="absolute right-0 top-0 flex h-full w-full max-w-md flex-col bg-white shadow-drawer"
    >
        <div class="flex items-center justify-between border-b border-line p-4">
            <h2 class="font-semibold text-ink">{{ $title }}</h2>
            <button type="button" x-on:click="open = false" aria-label="Fermer" class="text-ink-muted hover:text-ink">✕</button>
        </div>

        <div class="flex-1 overflow-y-auto p-4">
            {{ $slot }}
        </div>
    </div>
</div>
