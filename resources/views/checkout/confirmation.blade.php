<x-layouts.app title="Commande confirmée">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <x-ui.breadcrumb :items="[['label' => 'Commande']]" class="mb-6" />
        <h1 class="mb-2 text-2xl font-semibold text-ink">Merci {{ $order->first_name }} !</h1>
        <p class="mb-6 text-sm text-ink-muted">Commande n°{{ $order->number }} du {{ $order->created_at->format('d/m/Y') }}</p>

        {{--
            Virement : "pending_payment" tant que l'admin n'a pas validé le virement reçu (T15).
            Carte : le statut vient uniquement du webhook Stripe (T14), jamais du retour navigateur.
        --}}
        @if (session('payment_error'))
            <x-ui.alert variant="warning" class="mb-6">{{ session('payment_error') }}</x-ui.alert>
        @endif

        @if ($order->payment_method === \App\Enums\PaymentMethod::BankTransfer)
            <x-ui.alert variant="info" class="mb-6">
                <p class="mb-2 font-medium">Réglez par virement bancaire sous {{ $bankTransfer->cancel_after_days }} jours pour confirmer votre commande.</p>
                @if ($bankTransfer->iban)
                    <p>Titulaire du compte : {{ $bankTransfer->account_holder }}</p>
                    <p>IBAN : {{ $bankTransfer->iban }}</p>
                    <p>BIC : {{ $bankTransfer->bic }}</p>
                    <p class="mt-2">Merci d'indiquer la référence <strong>{{ $order->number }}</strong> en objet du virement.</p>
                @else
                    <p>Nos coordonnées bancaires vous seront communiquées par email très prochainement.</p>
                @endif
            </x-ui.alert>
        @else
            <livewire:stripe-payment-status :token="$order->token" />
        @endif

        <div class="mb-6 divide-y divide-line rounded-card border border-line bg-white">
            @foreach ($order->items as $item)
                <div class="flex items-center justify-between p-3 text-sm">
                    <span>{{ $item->product_name }} × {{ $item->quantity }}</span>
                    <span>{{ number_format($item->line_total_ttc / 100, 2, ',', ' ') }} €</span>
                </div>
            @endforeach
        </div>
        <div class="mb-6 flex justify-between text-base font-semibold text-ink">
            <span>Total</span><span>{{ number_format($order->total_ttc / 100, 2, ',', ' ') }} €</span>
        </div>

        {{-- Bloc "Créer mon compte" après commande invité (PLAN.md §13/§9.1). --}}
        @if (! $order->user_id)
            <x-ui.alert variant="success" class="mb-6">
                <p class="mb-2 font-medium">Créez votre compte pour suivre cette commande</p>
                <p class="mb-3">Retrouvez l'historique de vos commandes et vos factures en un clic.</p>
                <x-ui.button href="{{ route('register', ['email' => $order->email]) }}" variant="primary">Créer mon compte</x-ui.button>
            </x-ui.alert>
        @endif

        <a href="{{ route('boutique.index') }}" class="mt-6 inline-block text-sm text-ink-muted hover:text-sage">← Retour à la boutique</a>
    </div>
</x-layouts.app>
