@component('mail::message')
# Nouvelle demande de compte professionnel

- Société : **{{ $proRequest->company_name }}**
- Activité : {{ $proRequest->activity_type }}@if ($proRequest->activity_other) ({{ $proRequest->activity_other }})@endif

- Contact : {{ $proRequest->contactName() }}

Le détail (SIRET, coordonnées, besoin) est dans le back-office.

@component('mail::button', ['url' => url(config('admin.path').'/pro-account-requests/'.$proRequest->id)])
Voir la demande
@endcomponent
@endcomponent
