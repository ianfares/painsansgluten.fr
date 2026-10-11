@component('mail::message')
# Votre compte professionnel est prêt

Bonjour {{ $proRequest->contact_first_name }},

Bonne nouvelle : votre demande de compte professionnel{{ filled($proRequest->company_name) ? ' pour **'.$proRequest->company_name.'**' : '' }} a été acceptée.

@if ($accountCreated)
Votre compte client professionnel a été créé avec l'adresse **{{ $proRequest->email }}**. Vous allez recevoir **un second email** pour choisir votre mot de passe : c'est la dernière étape avant de pouvoir commander.
@else
Votre compte client existant (**{{ $proRequest->email }}**) est désormais un compte professionnel. Il vous suffit de vous connecter.

@component('mail::button', ['url' => route('login')])
Me connecter
@endcomponent
@endif

@if ($proRequest->admin_comment)
@component('mail::panel')
{!! nl2br(e($proRequest->admin_comment)) !!}
@endcomponent
@endif

{{ config('app.name') }}
@endcomponent
