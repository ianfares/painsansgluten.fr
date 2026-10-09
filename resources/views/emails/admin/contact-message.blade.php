@component('mail::message')
# Nouveau message de contact

- Nom : **{{ $data['name'] }}**
- Email : {{ $data['email'] }}
@if ($data['phone'])
- Téléphone : {{ $data['phone'] }}
@endif

@component('mail::panel')
{!! nl2br(e($data['message'])) !!}
@endcomponent

Pour répondre, utilisez simplement le bouton « Répondre » de votre messagerie.
@endcomponent
