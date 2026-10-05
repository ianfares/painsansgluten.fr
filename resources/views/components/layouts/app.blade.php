@props(['title' => null])

<!doctype html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — ' : '' }}Mon Sans Gluten by Angélique</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-cream font-sans text-ink antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4">Aller au contenu</a>

    <x-site.header :cart-count="$cartCount ?? 0" />

    <main id="main">
        {{ $slot }}
    </main>

    <x-site.footer />

    <x-ui.drawer name="cart" title="Votre panier">
        {{-- Contenu réel du panier : T08 --}}
        <p class="text-sm text-ink-muted">Votre panier est vide.</p>
    </x-ui.drawer>

    {{-- Bannière de consentement cookies (tarteaucitron.js) : intégration réelle en T22. --}}
</body>
</html>
