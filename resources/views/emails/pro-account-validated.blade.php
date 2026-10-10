@component('mail::message')
# Votre compte professionnel est validé

Bonjour {{ $user->first_name }},

Bonne nouvelle : votre compte professionnel{{ filled($user->company_name) ? ' pour **'.$user->company_name.'**' : '' }} est validé.

@component('mail::button', ['url' => route('compte.dashboard')])
Accéder à mon compte
@endcomponent

{{ config('app.name') }}
@endcomponent
