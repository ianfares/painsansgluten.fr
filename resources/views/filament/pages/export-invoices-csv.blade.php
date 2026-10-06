<x-filament-panels::page>
    <x-filament-panels::form id="form" wire:submit="export">
        {{ $this->form }}

        <x-filament::button type="submit">
            Exporter en CSV
        </x-filament::button>
    </x-filament-panels::form>
</x-filament-panels::page>
