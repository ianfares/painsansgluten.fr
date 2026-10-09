@component('mail::message')
# Demande bien reçue

Bonjour {{ $proRequest->contact_first_name }},

Nous avons bien reçu la demande de compte professionnel de **{{ $proRequest->company_name }}**. Nous l'étudions et revenons vers vous dans les meilleurs délais.

{{ config('app.name') }}
@endcomponent
