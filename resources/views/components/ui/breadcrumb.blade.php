{{--
    Fil d'Ariane (PLAN.md §17). $items : liste ['label' => string, 'url' => ?string].
    Le dernier élément n'est jamais un lien (page courante).
--}}
@props(['items' => []])

<nav aria-label="Fil d'Ariane" {{ $attributes->merge(['class' => 'text-sm text-ink-muted']) }}>
    <ol class="flex flex-wrap items-center gap-1">
        <li>
            <a href="{{ route('home') }}" class="hover:text-sage">Accueil</a>
        </li>
        @foreach ($items as $item)
            <li class="flex items-center gap-1" @if ($loop->last) aria-current="page" @endif>
                <span aria-hidden="true">/</span>
                @if (! $loop->last && ($item['url'] ?? null))
                    <a href="{{ $item['url'] }}" class="hover:text-sage">{{ $item['label'] }}</a>
                @else
                    <span class="text-ink">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
