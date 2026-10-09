@component('mail::message')
# Votre demande de compte professionnel

Bonjour {{ $proRequest->contact_first_name }},

Merci de l'intérêt que vous portez à nos produits. Nous ne sommes malheureusement pas en mesure de donner suite à la demande de compte professionnel de **{{ $proRequest->company_name }}** pour le moment.

@if ($proRequest->admin_comment)
@component('mail::panel')
{!! nl2br(e($proRequest->admin_comment)) !!}
@endcomponent
@endif

Vous pouvez toujours commander sur notre boutique comme tout client.

{{ config('app.name') }}
@endcomponent
