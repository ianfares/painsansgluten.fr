{{--
    Page d'accueil provisoire : prouve que le gabarit public (en-tête,
    pied de page, police auto-hébergée, tiroir panier) fonctionne de bout
    en bout. Le contenu réel de l'accueil (bannière, produits mis en
    avant...) est construit en T07.
--}}
<x-layouts.app>
    <div class="mx-auto flex max-w-4xl flex-col items-center gap-6 px-4 py-24 text-center">
        <h1 class="text-4xl font-semibold text-ink">Boulangerie &amp; créations artisanales 100&nbsp;% sans gluten</h1>
        <p class="max-w-xl text-ink-muted">
            Le contenu de cette page (catalogue, produits mis en avant) sera construit à la tâche T07.
        </p>
        <x-ui.button variant="primary" href="#">Découvrir la boutique</x-ui.button>
    </div>
</x-layouts.app>
