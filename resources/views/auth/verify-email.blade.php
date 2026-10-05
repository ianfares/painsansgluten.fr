<x-layouts.auth title="Vérification de l'email">
    <p>Merci de vérifier votre adresse email en cliquant sur le lien que nous venons de vous envoyer.</p>

    @if (session('resent'))
        <p class="status">Un nouveau lien de vérification a été envoyé à votre adresse email.</p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit">Renvoyer l'email de vérification</button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Déconnexion</button>
    </form>
</x-layouts.auth>
