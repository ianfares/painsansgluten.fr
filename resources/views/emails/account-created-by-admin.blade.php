@component('mail::message')
# Votre compte a été créé

Bonjour {{ $user->first_name }},

Un compte client vient d'être créé pour vous sur {{ config('app.name') }}. Pour y accéder, choisissez votre mot de passe en cliquant sur le bouton ci-dessous.

@component('mail::button', ['url' => $resetUrl])
Choisir mon mot de passe
@endcomponent

Ce lien est valable {{ $validityMinutes }} minutes. Passé ce délai, utilisez « Mot de passe oublié ? » sur la page de connexion.

{{ config('app.name') }}
@endcomponent
