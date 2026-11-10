@component('mail::message')
# Mot de passe oublié ?

Vous avez demandé à réinitialiser le mot de passe de votre compte. Cliquez sur le bouton ci-dessous pour en choisir un nouveau.

@component('mail::button', ['url' => $url])
Choisir un nouveau mot de passe
@endcomponent

Ce lien est valable {{ $minutes % 60 === 0 ? ($minutes / 60).' heure'.($minutes > 60 ? 's' : '') : $minutes.' minutes' }}. Passé ce délai, demandez simplement un nouveau lien depuis la page « Mot de passe oublié » : [{{ route('password.request') }}]({{ route('password.request') }})

Si vous n'êtes pas à l'origine de cette demande, ignorez cet email : votre mot de passe reste inchangé.

{{ config('app.name') }}
@endcomponent
