@php
    $tabs = collect($page->tabs ?? [])->filter(fn (array $tab): bool => filled($tab['title'] ?? null))->values()
        ->map(fn (array $tab, int $i): array => $tab + ['id' => \Illuminate\Support\Str::slug($tab['title']) ?: 'onglet-'.($i + 1)]);
    $seoText = $page->content ?: ($tabs->first()['content'] ?? '');
@endphp
<x-layouts.app
    :title="$page->seo_title ?: $page->title"
    :description="$page->seo_description ?: app(\App\Services\Seo\StructuredData::class)->plainText($seoText, 160)"
    :schema="[app(\App\Services\Seo\StructuredData::class)->breadcrumb([['label' => $page->title, 'url' => url()->current()]])]"
>
    <div class="mx-auto max-w-3xl px-4 py-10">
        <x-ui.breadcrumb :items="[['label' => $page->title]]" class="mb-6" />
        <h1 class="mb-6 text-2xl font-semibold text-ink">{{ $page->title }}</h1>
        @if ($page->content)
            <div class="prose prose-sm max-w-none">{!! $page->content !!}</div>
        @endif

        {{-- Onglets (24/10/2026) : un lien #id ouvre l'onglet correspondant ; sans JS, tout s'affiche. --}}
        @if ($tabs->isNotEmpty())
            <div class="mt-6" x-data="{ active: @js($tabs->first()['id']), init() { const id = window.location.hash.slice(1); if (@js($tabs->pluck('id')).includes(id)) this.active = id; } }">
                <div role="tablist" class="mb-6 flex gap-2 overflow-x-auto border-b border-line">
                    @foreach ($tabs as $tab)
                        <button type="button" role="tab" id="tab-{{ $tab['id'] }}" aria-controls="panel-{{ $tab['id'] }}"
                            x-on:click="active = @js($tab['id']); history.replaceState(null, '', '#' + active)"
                            x-bind:aria-selected="active === @js($tab['id'])"
                            x-bind:class="active === @js($tab['id']) ? 'border-sage text-ink font-semibold' : 'border-transparent text-ink-muted hover:text-ink'"
                            class="whitespace-nowrap border-b-2 px-3 py-2 text-sm">{{ $tab['title'] }}</button>
                    @endforeach
                </div>

                @foreach ($tabs as $tab)
                    <section role="tabpanel" id="panel-{{ $tab['id'] }}" aria-labelledby="tab-{{ $tab['id'] }}" x-show="active === @js($tab['id'])">
                        @if (filled($tab['content']))
                            <div class="prose prose-sm max-w-none">{!! $tab['content'] !!}</div>
                        @endif

                        @if ($tab['show_pickup_points'])
                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                @forelse (empty($tab['pickup_point_ids']) ? $pickupPoints : $pickupPoints->whereIn('id', $tab['pickup_point_ids']) as $point)
                                    <div class="rounded-card border border-line bg-white p-4 text-sm">
                                        <p class="font-semibold text-ink">{{ $point->name }}</p>
                                        <p class="text-ink-muted">{{ $point->address_line1 }}, {{ $point->postal_code }} {{ $point->city }}</p>
                                        @if ($point->pickupDaysLabel())
                                            <p class="mt-2 font-medium text-sage-dark">🧺 Retrait {{ $point->pickupDaysLabel() }}{{ ($hours = \App\Models\PickupPoint::formatPickupHours($point->pickup_from, $point->pickup_until)) ? ', '.$hours : '' }}</p>
                                        @endif
                                        @if ($point->opening_hours)
                                            <p class="mt-1 text-ink-muted">Horaires : {{ $point->opening_hours }}</p>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-ink-muted">Aucun point de retrait pour le moment.</p>
                                @endforelse
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
