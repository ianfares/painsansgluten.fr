@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'image' => null,
    'ogType' => 'website',
    'noindex' => false,
    'schema' => [],
])

@php
    // Balises SEO (T21, PLAN.md §17) : repli sur les paramètres SEO globaux,
    // pages privées (panier, commande, compte, connexion) jamais indexées.
    $seo = app(\App\Settings\SeoSettings::class);
    $shopName = 'Mon Sans Gluten by Angélique';
    $fullTitle = $title ? $title.' — '.$shopName : ($seo->default_title ?: $shopName.' — boulangerie 100 % sans gluten');
    $metaDescription = \Illuminate\Support\Str::limit((string) ($description ?: $seo->default_description ?: 'Boulangerie artisanale 100 % sans gluten à Avranches : pains, viennoiseries, pâtisseries et biscuits fabriqués à la commande, livrés en point relais Chronopost partout en France métropolitaine.'), 160, '…');
    $canonicalUrl = $canonical ?: url()->current();
    $ogImage = $image ?: ($seo->default_og_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($seo->default_og_image_path) : null);
    $isPrivate = $noindex || request()->is('panier', 'commande', 'commande/*', 'mon-compte', 'mon-compte/*', 'connexion', 'inscription', 'forgot-password', 'reset-password/*', 'email/*', 'user/*');
@endphp

<!doctype html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    @if ($metaDescription)
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if ($isPrivate)
        <meta name="robots" content="noindex, nofollow">
    @else
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif
    <meta property="og:site_name" content="{{ $shopName }}">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    @if ($metaDescription)
        <meta property="og:description" content="{{ $metaDescription }}">
    @endif
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    @foreach (array_filter($schema) as $block)
        <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endforeach
    @if (($homepageSettings ?? null)?->favicon_path)
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($homepageSettings->favicon_path) }}">
    @endif
    {{-- Consent Mode v2 « denied » + tarteaucitron : avant tout script d'application (T22). --}}
    <x-site.consent />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-cream font-sans text-ink antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4">Aller au contenu</a>

    {{-- $cartCount injecté par App\View\Composers\CartCountComposer --}}
    <x-site.header :cart-count="$cartCount ?? 0" />

    <main id="main">
        {{ $slot }}
    </main>

    <x-site.footer />

    <x-ui.drawer name="cart" title="Votre panier">
        <livewire:cart-widget />
    </x-ui.drawer>

    @livewireScripts
</body>
</html>
