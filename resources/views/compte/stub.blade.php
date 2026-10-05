<x-layouts.auth title="Mon compte">
    <p>Bonjour {{ auth()->user()->first_name }}, votre compte est actif et votre email est vérifié.</p>
    <p>Le tableau de bord complet (commandes, factures, informations) sera construit en T18.</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Déconnexion</button>
    </form>
</x-layouts.auth>
