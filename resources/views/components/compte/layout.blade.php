@props(['title' => null, 'active' => null])

<x-layouts.app :title="$title">
    <div class="mx-auto flex max-w-5xl flex-col gap-8 px-4 py-10 sm:flex-row">
        <nav class="flex flex-shrink-0 flex-row gap-2 overflow-x-auto sm:w-56 sm:flex-col" aria-label="Mon compte">
            @foreach ([
                ['key' => 'dashboard', 'label' => 'Tableau de bord', 'route' => 'compte.dashboard'],
                ['key' => 'orders', 'label' => 'Mes commandes', 'route' => 'compte.orders'],
                ['key' => 'invoices', 'label' => 'Mes factures', 'route' => 'compte.invoices'],
                ['key' => 'informations', 'label' => 'Mes informations', 'route' => 'compte.informations'],
            ] as $item)
                <a
                    href="{{ route($item['route']) }}"
                    class="whitespace-nowrap rounded-button px-4 py-2 text-sm {{ $active === $item['key'] ? 'bg-sage text-white' : 'text-ink hover:bg-cream-alt' }}"
                >
                    {{ $item['label'] }}
                </a>
            @endforeach

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full whitespace-nowrap rounded-button px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                    Déconnexion
                </button>
            </form>
        </nav>

        <div class="flex-1">
            @if (session('status'))
                <x-ui.alert variant="success" class="mb-6">{{ session('status') }}</x-ui.alert>
            @endif

            {{ $slot }}
        </div>
    </div>
</x-layouts.app>
