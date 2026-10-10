@component('mail::message')
# Nouveau compte pro à valider

- Société : **{{ $user->company_name ?: '—' }}**
- SIRET : {{ $user->siret ?: '—' }}
- Contact : {{ $user->name }}

@component('mail::button', ['url' => url(config('admin.path').'/users/'.$user->id)])
Voir le compte
@endcomponent
@endcomponent
