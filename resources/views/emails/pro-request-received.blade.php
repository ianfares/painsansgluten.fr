@component('mail::message')
# Demande bien reçue

Bonjour {{ $proRequest->contact_first_name }},

Nous avons bien reçu votre demande de compte professionnel{{ filled($proRequest->company_name) ? ' pour **'.$proRequest->company_name.'**' : '' }}. Nous l'étudions et revenons vers vous dans les meilleurs délais.

{{ config('app.name') }}
@endcomponent
