@php
    // Variables injectées par App\View\Composers\FooterComposer :
    // $shopSettings, $homepageSettings, $footerPages, $legalPages.
@endphp

<footer class="border-t-4 border-sage bg-cream-alt">
    <div class="mx-auto grid max-w-6xl grid-cols-1 gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            @if ($homepageSettings->logo_path)
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($homepageSettings->logo_path) }}"
                    alt="{{ $shopSettings->shop_name ?? 'Mon Sans Gluten by Angélique' }}"
                    class="mb-2 h-12 w-12 rounded-full object-cover shadow-button"
                >
            @endif
            <p class="text-sm font-semibold text-ink">{{ $shopSettings->shop_name ?? 'Mon Sans Gluten by Angélique' }}</p>
            <p class="mt-2 text-sm text-ink-muted">
                {{ $shopSettings->address_line1 }}
                @if ($shopSettings->postal_code || $shopSettings->city)
                    <br>{{ $shopSettings->postal_code }} {{ $shopSettings->city }}
                @endif
            </p>
            @if ($shopSettings->address_note)
                <p class="mt-1 text-xs italic text-ink-muted">{{ $shopSettings->address_note }}</p>
            @endif
            @if ($shopSettings->facebook_url)
                <a
                    href="{{ $shopSettings->facebook_url }}"
                    class="mt-3 inline-flex h-9 w-9 items-center justify-center rounded-full bg-sage/10 text-sage-dark transition hover:bg-sage hover:text-white"
                    rel="noopener" target="_blank" aria-label="Facebook"
                >
                    <span aria-hidden="true">f</span>
                </a>
            @endif
        </div>

        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sage-dark">La boutique</p>
            <ul class="mt-3 space-y-2 text-sm text-ink-muted">
                @foreach ($footerPages as $page)
                    <li><a href="{{ route('content.show', $page) }}" class="hover:text-sage">{{ $page->title }}</a></li>
                @endforeach
                <li><a href="{{ route('faq') }}" class="hover:text-sage">FAQ</a></li>
                <li><a href="{{ route('pro.request.create') }}" class="hover:text-sage">Professionnels</a></li>
            </ul>
        </div>

        @if ($legalPages->isNotEmpty())
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-sage-dark">Informations légales</p>
                <ul class="mt-3 space-y-2 text-sm text-ink-muted">
                    @foreach ($legalPages as $page)
                        <li><a href="{{ route('content.show', $page) }}" class="hover:text-sage">{{ $page->title }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sage-dark">Contact</p>
            <ul class="mt-3 space-y-2 text-sm text-ink-muted">
                <li><a href="{{ route('contact') }}" class="hover:text-sage">Formulaire de contact</a></li>
                @if ($shopSettings->contact_phone)
                    <li class="flex items-center gap-2">
                        <span aria-hidden="true">📞</span>
                        {{ $shopSettings->contact_phone }}
                    </li>
                @endif
            </ul>
            {{-- Bouton affiché seulement si le bandeau est actif (production + GTM) ; gestionnaire : resources/js/consent.js --}}
            @if (app(\App\Services\Consent\CookieConsent::class)->isActive())
                <button type="button" class="btn btn-outline btn-sm mt-4" data-tarteaucitron-manager>
                    Gérer mes préférences
                </button>
            @endif
        </div>
    </div>

    <div class="bg-sage-dark px-4 py-3 text-center text-xs text-white/80">
        &copy; {{ now()->year }} {{ $shopSettings->shop_name ?? 'Mon Sans Gluten by Angélique' }}. Tous droits réservés.
    </div>
</footer>
