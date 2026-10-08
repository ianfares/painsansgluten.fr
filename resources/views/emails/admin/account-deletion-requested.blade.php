@component('mail::message')
# Demande de suppression de compte

Le client **{{ $user->name }}** ({{ $user->email }}) demande la suppression de son compte.

Traitez la demande depuis la fiche client du back-office. La loi (RGPD) impose une réponse dans un délai d'un mois.

@component('mail::button', ['url' => url(config('admin.path').'/users/'.$user->id)])
Voir le client
@endcomponent
@endcomponent
