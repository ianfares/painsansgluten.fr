@component('mail::message')
# Merci pour votre commande {{ $order->number }}

Bonjour {{ $order->first_name }},

Votre commande d'un montant de **{{ number_format($order->total_ttc / 100, 2, ',', ' ') }} €** est enregistrée. Pour la confirmer, merci de régler par virement bancaire en utilisant les coordonnées ci-dessous.

@component('mail::panel')
**Titulaire du compte** : {{ $bankTransfer->account_holder ?? 'communiqué très prochainement' }}
**IBAN** : {{ $bankTransfer->iban ?? 'communiqué très prochainement' }}
**BIC** : {{ $bankTransfer->bic ?? 'communiqué très prochainement' }}
**Référence à indiquer obligatoirement** : {{ $order->number }}
@endcomponent

@if ($bankTransfer->auto_cancel_enabled)
Merci de régler sous **{{ $bankTransfer->cancel_after_days }} jours**, faute de quoi la commande sera automatiquement annulée.
@endif

⚠️ Votre commande sera **fabriquée et expédiée après réception de votre virement**. Pour ne pas retarder sa préparation, nous vous conseillons un **virement instantané**.

Merci de votre confiance,<br>
{{ config('app.name') }}
@endcomponent
