@component('mail::message')
# Votre demande de compte professionnel

Bonjour {{ $proRequest->contact_first_name }},

Bonne nouvelle : votre demande de compte professionnel{{ filled($proRequest->company_name) ? ' pour **'.$proRequest->company_name.'**' : '' }} a été acceptée. Nous revenons vers vous très rapidement pour la suite.

@if ($proRequest->admin_comment)
@component('mail::panel')
{!! nl2br(e($proRequest->admin_comment)) !!}
@endcomponent
@endif

{{ config('app.name') }}
@endcomponent
