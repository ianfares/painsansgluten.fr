<x-layouts.app title="Finaliser ma commande">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <x-ui.breadcrumb :items="[['label' => 'Panier', 'url' => route('panier')], ['label' => 'Commande']]" class="mb-6" />
        <h1 class="mb-6 text-2xl font-semibold text-ink">Finaliser ma commande</h1>

        <livewire:checkout-wizard />
    </div>
</x-layouts.app>
