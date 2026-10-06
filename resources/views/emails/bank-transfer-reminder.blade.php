@component('mail::message')
# Rappel — commande {{ $order->number }}

Bonjour {{ $order->first_name }},

Nous n'avons pas encore reçu votre virement pour la commande **{{ $order->number }}** d'un montant de **{{ number_format($order->total_ttc / 100, 2, ',', ' ') }} €**.

@component('mail::panel')
**Titulaire du compte** : {{ $bankTransfer->account_holder ?? 'communiqué très prochainement' }}
**IBAN** : {{ $bankTransfer->iban ?? 'communiqué très prochainement' }}
**BIC** : {{ $bankTransfer->bic ?? 'communiqué très prochainement' }}
**Référence à indiquer obligatoirement** : {{ $order->number }}
@endcomponent

@if ($bankTransfer->auto_cancel_enabled)
Sans règlement sous **{{ $bankTransfer->cancel_after_days }} jours** à compter de votre commande, celle-ci sera automatiquement annulée.
@endif

Si vous avez déjà effectué ce virement, merci d'ignorer cet email.

{{ config('app.name') }}
@endcomponent
