@php
    // Variables injectées par App\View\Composers\HeaderComposer :
    // $homepageSettings, $shopSettings, $headerCategories.
@endphp

<header x-data="{ mobileOpen: false, megaOpen: false }">
    @if ($homepageSettings->announcement_active && $homepageSettings->announcement_text)
        <div class="bg-sage px-4 py-2 text-center text-sm text-white">
            {{ $homepageSettings->announcement_text }}
        </div>
    @endif

    <div class="border-b border-line bg-cream">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 lg:grid lg:grid-cols-[1fr_auto_1fr]">
            <button
                type="button"
                class="lg:hidden"
                aria-label="Ouvrir le menu"
                x-on:click="mobileOpen = true"
            >
                <span aria-hidden="true" class="text-2xl">☰</span>
            </button>

            <a href="{{ route('home') }}" class="hidden items-center gap-2 text-lg font-semibold text-ink lg:order-1 lg:flex lg:justify-self-start">
                @if ($homepageSettings->logo_path)
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($homepageSettings->logo_path) }}"
                        alt="{{ $shopSettings->shop_name ?? 'Mon Sans Gluten by Angélique' }}"
                        class="h-11 w-11 rounded-full object-cover"
                    >
                @else
                    {{ $shopSettings->shop_name ?? "Mon Sans Gluten" }}
                @endif
            </a>

            <nav class="hidden items-center gap-8 lg:order-2 lg:flex lg:justify-self-center" aria-label="Navigation principale">
                <a href="{{ route('home') }}" class="text-sm font-medium text-ink hover:text-sage">Accueil</a>
                <div class="relative" x-on:mouseenter="megaOpen = true" x-on:mouseleave="megaOpen = false">
                    <a href="{{ route('boutique.index') }}" class="text-sm font-medium text-ink hover:text-sage">
                        Boutique
                    </a>

                    <div
                        x-show="megaOpen"
                        x-cloak
                        x-transition
                        class="absolute left-1/2 top-full z-40 w-[40rem] -translate-x-1/2 rounded-card border border-line bg-white p-6 shadow-drawer"
                    >
                        <div class="grid grid-cols-4 gap-4">
                            @foreach ($headerCategories as $category)
                                <a href="{{ route('content.show', $category) }}" class="group flex flex-col items-center gap-2 text-center">
                                    <span class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-cream-alt to-sage/15 text-2xl shadow-sm transition group-hover:scale-105">
                                        @if ($url = $category->getFirstMediaUrl('cover', 'menu'))
                                            <img src="{{ $url }}" alt="" class="h-full w-full object-cover">
                                        @else
                                            <span aria-hidden="true">{{ $category->fallbackIcon() }}</span>
                                        @endif
                                    </span>
                                    <span class="text-xs font-medium text-ink group-hover:text-sage">{{ $category->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                <a href="{{ \Illuminate\Support\Facades\Route::has('contact') ? route('contact') : '#' }}" class="text-sm font-medium text-ink hover:text-sage">Contact</a>
            </nav>

            {{-- Logo mobile (le lien "desktop" ci-dessus reste masqué en dessous de lg) --}}
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-semibold text-ink lg:hidden">
                @if ($homepageSettings->logo_path)
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($homepageSettings->logo_path) }}"
                        alt="{{ $shopSettings->shop_name ?? 'Mon Sans Gluten by Angélique' }}"
                        class="h-11 w-11 rounded-full object-cover"
                    >
                @else
                    {{ $shopSettings->shop_name ?? "Mon Sans Gluten" }}
                @endif
            </a>

            <div class="flex items-center justify-end gap-2 lg:order-3 lg:justify-self-end">
                <a
                    href="{{ auth()->check() ? route('compte.dashboard') : route('login') }}"
                    aria-label="Mon compte"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-ink transition hover:bg-sage/10 hover:text-sage"
                >
                    <span aria-hidden="true" class="text-lg">👤</span>
                </a>
                <button
                    type="button"
                    x-on:click="window.dispatchEvent(new CustomEvent('open-drawer-cart'))"
                    class="relative flex h-10 w-10 items-center justify-center rounded-full text-ink transition hover:bg-sage/10 hover:text-sage"
                    aria-label="Panier"
                >
                    <span aria-hidden="true" class="text-lg">🛍️</span>
                    @if (($cartCount ?? 0) > 0)
                        <span class="absolute right-0.5 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-ochre text-[10px] text-white">
                            {{ $cartCount }}
                        </span>
                    @endif
                </button>
            </div>
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
                <a href="{{ route('content.show', $category) }}" class="pl-4 text-sm text-ink-muted hover:text-sage">{{ $category->name }}</a>
            @endforeach
            <a href="{{ \Illuminate\Support\Facades\Route::has('contact') ? route('contact') : '#' }}" class="text-ink hover:text-sage">Contact</a>
        </nav>
    </div>
</header>
