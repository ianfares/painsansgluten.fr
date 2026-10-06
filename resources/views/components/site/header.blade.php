@php
    // Variables injectées par App\View\Composers\HeaderComposer :
    // $homepageSettings, $shopSettings, $headerCategories.
@endphp

<header x-data="{ mobileOpen: false }">
    @if ($homepageSettings->announcement_active && $homepageSettings->announcement_text)
        <div class="bg-sage px-4 py-2 text-center text-sm text-white">
            {{ $homepageSettings->announcement_text }}
        </div>
    @endif

    {{-- Barre utilitaire (liens secondaires, masquée sur mobile — repris dans le menu coulissant) --}}
    <div class="hidden border-b border-line bg-white lg:block">
        <div class="mx-auto flex max-w-6xl items-center justify-end gap-6 px-4 py-2 text-xs font-medium text-ink-muted">
            <a href="{{ route('faq') }}" class="hover:text-sage">F.A.Q.</a>
            <a href="{{ route('content.show', 'contact') }}" class="hover:text-sage">Contact</a>
        </div>
    </div>

    {{-- Barre principale : logo en grand, centré --}}
    <div class="border-b border-line bg-cream">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-5 lg:grid lg:grid-cols-[1fr_auto_1fr]">
            <button
                type="button"
                class="lg:hidden"
                aria-label="Ouvrir le menu"
                x-on:click="mobileOpen = true"
            >
                <span aria-hidden="true" class="text-2xl">☰</span>
            </button>

            <div class="hidden lg:block" aria-hidden="true"></div>

            <a href="{{ route('home') }}" class="flex flex-col items-center gap-1.5 lg:justify-self-center">
                @if ($homepageSettings->logo_path)
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($homepageSettings->logo_path) }}"
                        alt="{{ $shopSettings->shop_name ?? 'Mon Sans Gluten by Angélique' }}"
                        class="h-14 w-14 rounded-full object-cover sm:h-20 sm:w-20"
                    >
                    <span class="hidden text-base font-semibold text-ink sm:block sm:text-lg">
                        {{ $shopSettings->shop_name ?? 'Mon Sans Gluten by Angélique' }}
                    </span>
                    <span class="hidden text-xs uppercase tracking-wide text-sage-dark sm:block">100&nbsp;% sans gluten</span>
                @else
                    <span class="text-lg font-semibold text-ink">
                        {{ $shopSettings->shop_name ?? 'Mon Sans Gluten by Angélique' }}
                    </span>
                @endif
            </a>

            <div class="flex items-center justify-end gap-3 lg:justify-self-end">
                <a
                    href="{{ auth()->check() ? route('compte.dashboard') : route('login') }}"
                    aria-label="Mon compte"
                    class="group flex flex-col items-center gap-1"
                >
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-sage-dark text-white transition group-hover:bg-sage">
                        <span aria-hidden="true" class="text-base">👤</span>
                    </span>
                    <span class="hidden text-[10px] font-semibold uppercase tracking-wide text-ink-muted sm:block">Mon compte</span>
                </a>
                <button
                    type="button"
                    x-on:click="window.dispatchEvent(new CustomEvent('open-drawer-cart'))"
                    class="group flex flex-col items-center gap-1"
                    aria-label="Panier"
                >
                    <span class="relative flex h-10 w-10 items-center justify-center rounded-full bg-sage-dark text-white transition group-hover:bg-sage">
                        <span aria-hidden="true" class="text-base">🛍️</span>
                        @if (($cartCount ?? 0) > 0)
                            <span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-ochre text-[10px] text-white">
                                {{ $cartCount }}
                            </span>
                        @endif
                    </span>
                    <span class="hidden text-[10px] font-semibold uppercase tracking-wide text-ink-muted sm:block">Mon panier</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Barre de catégories (persistante, masquée sur mobile — repris dans le menu coulissant) --}}
    <div class="hidden border-b border-line bg-white lg:block">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-8 gap-y-2 px-4 py-3">
            <a href="{{ route('home') }}" class="text-sm font-medium text-ink hover:text-sage">Accueil</a>
            <a href="{{ route('boutique.index') }}" class="text-sm font-medium text-ink hover:text-sage">Toute la boutique</a>
            @foreach ($headerCategories as $category)
                <a href="{{ route('content.show', $category) }}" class="flex items-center gap-1.5 text-sm font-medium text-ink hover:text-sage">
                    <span aria-hidden="true">{{ $category->fallbackIcon() }}</span>
                    {{ $category->name }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Menu mobile --}}
    <div
        x-show="mobileOpen"
        x-cloak
        class="fixed inset-0 z-50 lg:hidden"
        role="dialog"
        aria-modal="true"
        aria-label="Menu"
    >
        <div class="absolute inset-0 bg-black/40" x-on:click="mobileOpen = false"></div>
        <nav class="relative flex h-full w-64 flex-col gap-4 bg-white p-6" aria-label="Navigation mobile">
            <button type="button" class="self-end text-ink-muted" x-on:click="mobileOpen = false" aria-label="Fermer le menu">✕</button>
            <a href="{{ route('home') }}" class="text-ink hover:text-sage">Accueil</a>
            <a href="{{ route('boutique.index') }}" class="text-ink hover:text-sage">Boutique</a>
            @foreach ($headerCategories as $category)
                <a href="{{ route('content.show', $category) }}" class="flex items-center gap-2 pl-4 text-sm text-ink-muted hover:text-sage">
                    <span aria-hidden="true">{{ $category->fallbackIcon() }}</span>
                    {{ $category->name }}
                </a>
            @endforeach
            <a href="{{ route('faq') }}" class="text-ink hover:text-sage">F.A.Q.</a>
            <a href="{{ route('content.show', 'contact') }}" class="text-ink hover:text-sage">Contact</a>
        </nav>
    </div>
</header>
