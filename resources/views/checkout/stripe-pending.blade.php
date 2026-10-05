<x-layouts.app title="Paiement en cours">
    <div class="mx-auto max-w-xl px-4 py-10 text-center">
        <h1 class="mb-4 text-2xl font-semibold text-ink">Un instant…</h1>
        <x-ui.alert variant="info" class="text-left">
            Votre commande n°{{ $order->number }} est bien enregistrée. L'intégration du paiement par carte bancaire est en cours de finalisation : nous revenons vers vous très rapidement par email ({{ $order->email }}) pour la régler en toute sécurité.
        </x-ui.alert>
        <p class="mt-6 text-sm text-ink-muted">Besoin d'aide ? Contactez-nous en mentionnant le numéro {{ $order->number }}.</p>
    </div>
</x-layouts.app>
