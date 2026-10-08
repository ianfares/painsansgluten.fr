@component('mail::message')
# Bienvenue{{ $name ? ' '.$name : '' }} !

Merci d'avoir créé votre compte sur {{ config('app.name') }}. Il ne reste plus qu'à confirmer votre adresse email.

@component('mail::button', ['url' => $url])
Confirmer mon adresse email
@endcomponent

Votre compte vous permet de suivre vos commandes et de retrouver vos factures.

Si vous n'avez pas créé de compte, ignorez simplement cet email.

{{ config('app.name') }}
@endcomponent
