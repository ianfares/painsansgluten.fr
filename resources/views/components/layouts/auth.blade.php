<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Authentification' }} — Mon Sans Gluten by Angélique</title>
    {{--
        Gabarit minimal et fonctionnel, non stylé : le design définitif
        (couleurs du site) arrive en T06/T18. Objectif T03 : un parcours
        d'authentification qui fonctionne de bout en bout.
    --}}
    <style>
        body { font-family: sans-serif; max-width: 420px; margin: 4rem auto; padding: 0 1rem; }
        label { display: block; margin-top: 1rem; font-size: 0.9rem; }
        input { width: 100%; padding: 0.5rem; margin-top: 0.25rem; box-sizing: border-box; }
        button { margin-top: 1.5rem; padding: 0.6rem 1.2rem; cursor: pointer; }
        .error { color: #b00020; font-size: 0.85rem; }
        .status { color: #1a6e2e; font-size: 0.9rem; margin-top: 1rem; }
    </style>
</head>
<body>
    <h1>{{ $title ?? '' }}</h1>

    @if (session('status'))
        <p class="status">{{ session('status') }}</p>
    @endif

    {{ $slot }}
</body>
</html>
