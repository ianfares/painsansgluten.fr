{{--
    Bandeau d'engagements (inspiré de la référence fournie par Ian, docs/reference/capture/look.png).
    Uniquement des affirmations vérifiées : pas de « bio » ni de certification (AFDIAG non confirmé,
    voir docs/DECISIONS.md), pas de durée de congélation, pas de choix de date de livraison (V2).
--}}
@php
    $items = [
        [
            'color' => '#9a4a35',
            'icon' => '<path d="M2.5 6.5h11v9.5h-11z"/><path d="M13.5 9.5h3.8l3.2 3.3v3.2h-7"/><circle cx="6.3" cy="17.4" r="1.7"/><circle cx="16.8" cy="17.4" r="1.7"/>',
            'text' => 'Une livraison <strong>en point relais Chronopost</strong> en France métropolitaine (hors Corse)',
        ],
        [
            'color' => '#d9625a',
            'icon' => '<path d="M12 21V7.5"/><path d="M12 7.5c-.6-1.6-.6-3.2 0-4.8.6 1.6.6 3.2 0 4.8z"/><path d="M12 12c-2.1-.4-3.5-1.8-4-3.8 2.1.4 3.5 1.8 4 3.8zM12 12c2.1-.4 3.5-1.8 4-3.8-2.1.4-3.5 1.8-4 3.8zM12 16.2c-2.1-.4-3.5-1.8-4-3.8 2.1.4 3.5 1.8 4 3.8zM12 16.2c2.1-.4 3.5-1.8 4-3.8-2.1.4-3.5 1.8-4 3.8z"/><path d="M4.5 19.5 19.5 4.5"/>',
            'text' => 'Une boulangerie <strong>100 % sans gluten</strong> à Avranches',
        ],
        [
            'color' => '#6b93c4',
            'icon' => '<path d="M12 2.5v19M3.8 7.25l16.4 9.5M20.2 7.25 3.8 16.75"/><path d="m9.6 4 2.4 2.2L14.4 4M9.6 20 12 17.8l2.4 2.2M4.2 10.6l3.1-.9-.7-3.2M19.8 13.4l-3.1.9.7 3.2M6.6 17.5l.7-3.2-3.1-.9M17.4 6.5l-.7 3.2 3.1.9"/>',
            'text' => 'Des pains qui <strong>se congèlent tranchés</strong> : sortez juste ce qu\'il vous faut',
        ],
        [
            'color' => '#c4932b',
            'icon' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.2"/><path d="M3.5 10h17M8 3v4M16 3v4"/><path d="m9 15.2 2.1 2.1 4-4.2"/>',
            'text' => 'Des produits <strong>fabriqués à la commande</strong>, avec une date d\'expédition annoncée',
        ],
    ];
@endphp

<section aria-label="Nos engagements" class="bg-cream-alt px-4 py-12">
    <ul class="mx-auto grid max-w-6xl grid-cols-2 gap-x-6 gap-y-10 lg:grid-cols-4">
        @foreach ($items as $item)
            <li class="flex flex-col items-center gap-4 text-center">
                <span class="relative flex h-20 w-20 items-center justify-center rounded-full" style="background-color: {{ $item['color'] }}">
                    <span class="absolute inset-1.5 rounded-full border-2 border-white/90" aria-hidden="true"></span>
                    <svg class="h-10 w-10 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
                </span>
                <p class="max-w-[16rem] text-sm leading-relaxed text-ink-muted [&_strong]:font-semibold">
                    {!! str_replace('<strong>', '<strong style="color: '.$item['color'].'">', $item['text']) !!}
                </p>
            </li>
        @endforeach
    </ul>
</section>
