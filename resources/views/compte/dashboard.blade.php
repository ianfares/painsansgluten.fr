<x-compte.layout title="Mon compte" active="dashboard">
    <h1 class="mb-6 text-2xl font-semibold text-ink">Bonjour {{ auth()->user()->first_name }}</h1>

    @if ($recentOrders->isEmpty())
        <x-ui.alert variant="info">Vous n'avez pas encore passé de commande.</x-ui.alert>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($recentOrders as $order)
                <div class="flex items-center justify-between rounded-card border border-line bg-white p-4">
                    <div>
                        <p class="font-medium text-ink">{{ $order->number }}</p>
                        <p class="text-xs text-ink-muted">{{ $order->created_at->format('d/m/Y') }}</p>
                    </div>
                    <x-ui.badge>{{ $order->status->label() }}</x-ui.badge>
                </div>
            @endforeach
        </div>
        <a href="{{ route('compte.orders') }}" class="mt-4 inline-block text-sm text-sage hover:underline">Voir toutes mes commandes →</a>
    @endif
</x-compte.layout>
