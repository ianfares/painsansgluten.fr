<x-filament-widgets::widget>
    @php($items = $this->getMissingItems())

    @if (count($items) > 0)
        <x-filament::section>
            <x-slot name="heading">⚠️ Configuration incomplète</x-slot>

            <ul class="list-disc list-inside space-y-1 text-sm text-warning-600">
                @foreach ($items as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
