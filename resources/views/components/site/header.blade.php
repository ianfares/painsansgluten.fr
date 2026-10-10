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
                @else
                    <span class="text-lg font-semibold text-ink">
                        {{ $shopSettings->shop_name ?? 'Mon Sans Gluten by Angélique' }}
                    </span>
                @endif
            </a>

            <div class="flex items-center justify-end gap-3 lg:justify-self-end">
                {{-- Icônes monochromes au trait (T26 A3) : vert, ocre au survol. --}}
                <a
                    href="{{ auth()->check() ? route('compte.dashboard') : route('login') }}"
                    aria-label="Mon compte"
                    class="group flex flex-col items-center gap-1 text-sage transition hover:text-ochre focus-visible:text-ochre"
                >
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="8" r="4" />
                        <path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7" />
                    </svg>
                    <span class="hidden text-[10px] font-semibold uppercase tracking-wide sm:block">Mon compte</span>
                </a>
                <button
                    type="button"
                    x-data="{ count: {{ (int) ($cartCount ?? 0) }} }"
                    x-on:cart-count.window="count = $event.detail.count"
                    x-on:click="window.dispatchEvent(new CustomEvent('open-drawer-cart'))"
                    x-bind:aria-label="'Mon panier, ' + count + (count > 1 ? ' articles' : ' article')"
                    aria-label="Mon panier, {{ (int) ($cartCount ?? 0) }} {{ ($cartCount ?? 0) > 1 ? 'articles' : 'article' }}"
                    class="group flex flex-col items-center gap-1 text-sage transition hover:text-ochre focus-visible:text-ochre"
                >
                    <span class="relative">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 8h14l-1.2 12H6.2L5 8z" />
                            <path d="M9 8V6a3 3 0 0 1 6 0v2" />
                        </svg>
                        <span
                            x-show="count > 0"
                            x-text="count"
                            @if (($cartCount ?? 0) === 0) style="display: none" @endif
                            aria-hidden="true"
                            class="absolute -right-2 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-ochre px-1 text-[10px] font-bold text-white"
                        >{{ $cartCount ?? 0 }}</span>
                    </span>
                    <span class="hidden text-[10px] font-semibold uppercase tracking-wide sm:block">Mon panier</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Barre de catégories (persistante, masquée sur mobile — repris dans le menu coulissant) --}}
    <div class="hidden border-b border-line bg-white lg:block">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-6 gap-y-2 px-4 py-3">
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
        </nav>
    </div>
</header>
