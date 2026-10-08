@component('mail::message')
# Mot de passe oublié ?

Vous avez demandé à réinitialiser le mot de passe de votre compte. Cliquez sur le bouton ci-dessous pour en choisir un nouveau.

@component('mail::button', ['url' => $url])
Choisir un nouveau mot de passe
@endcomponent

Ce lien est valable {{ $minutes }} minutes. Si vous n'êtes pas à l'origine de cette demande, ignorez cet email : votre mot de passe reste inchangé.

{{ config('app.name') }}
@endcomponent
