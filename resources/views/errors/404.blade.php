{{--
    Page rendue directement par le gestionnaire d'exceptions de Laravel
    (hors routage normal) : pas de contrôleur ni de View Composer fiable à
    ce stade, d'où la requête directe ici — cas limite documenté, voir
    docs/DECISIONS.md (T06).
--}}
<x-layouts.app title="Page introuvable">
    <div class="mx-auto flex max-w-2xl flex-col items-center gap-6 px-4 py-24 text-center">
        <h1 class="text-3xl font-semibold text-ink">Page introuvable</h1>
        <p class="text-ink-muted">
            Cette page n'existe pas ou n'est plus disponible. Retrouvez nos créations sans gluten ci-dessous.
        </p>

        <div class="flex flex-wrap justify-center gap-3">
            @foreach (\App\Models\Category::query()->where('is_active', true)->orderBy('position')->get() as $category)
                <x-ui.button variant="outline" :href="route('content.show', $category)">{{ $category->name }}</x-ui.button>
            @endforeach
        </div>

        <x-ui.button variant="primary" :href="route('home')">Retour à l'accueil</x-ui.button>
    </div>
</x-layouts.app>
