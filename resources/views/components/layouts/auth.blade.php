@props(['title' => null])

{{--
    Pages de compte (connexion, inscription, mot de passe oublié/réinitialisation,
    vérification d'email) : gabarit du site + carte centrée. Les champs des vues
    `auth/*` sont mis en forme par la classe `.auth-card` (resources/css/app.css).
--}}
<x-layouts.app :title="$title">
    <div class="mx-auto max-w-md px-4 py-12">
        <div class="auth-card rounded-card border border-line bg-white p-6 shadow-sm sm:p-8">
            <h1 class="mb-2 text-2xl font-semibold text-ink">{{ $title }}</h1>

            @if (session('status'))
                <p class="status">{{ session('status') }}</p>
            @endif

            {{ $slot }}
        </div>
    </div>
</x-layouts.app>
