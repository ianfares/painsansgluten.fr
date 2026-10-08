<div @if ($order->status === \App\Enums\OrderStatus::PendingPayment && $keepPolling) wire:poll.2s @endif class="mb-6">
    @switch($order->status)
        @case(\App\Enums\OrderStatus::PendingPayment)
            @if ($keepPolling)
                <x-ui.alert variant="info">Paiement en cours de validation… Cette page se met à jour automatiquement.</x-ui.alert>
            @else
                <x-ui.alert variant="info">
                    <p class="mb-3">Nous n'avons pas encore reçu la confirmation de votre paiement. Vous recevrez un email dès sa validation.</p>
                    <p class="mb-3">Vous n'avez pas finalisé le paiement ?</p>
                    <x-ui.button href="{{ route('checkout.stripe.start', $order) }}" variant="primary">Reprendre le paiement</x-ui.button>
                </x-ui.alert>
            @endif
            @break
        @case(\App\Enums\OrderStatus::PaymentFailed)
            <x-ui.alert variant="warning">
                <p class="mb-3">Votre paiement a été refusé. Aucun montant n'a été débité.</p>
                <x-ui.button href="{{ route('checkout.stripe.start', $order) }}" variant="primary">Réessayer le paiement</x-ui.button>
            </x-ui.alert>
            @break
        @case(\App\Enums\OrderStatus::Cancelled)
            <x-ui.alert variant="warning">Cette commande a été annulée (délai de paiement dépassé).</x-ui.alert>
            @break
        @default
            <x-ui.alert variant="success">Votre paiement a bien été accepté.</x-ui.alert>
    @endswitch
</div>
