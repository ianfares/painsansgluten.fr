@php
    // Variables injectées par App\View\Composers\FooterComposer :
    // $shopSettings, $footerPages.
@endphp

<footer class="border-t border-line bg-cream-alt">
    <div class="mx-auto grid max-w-6xl grid-cols-1 gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            @if ($homepageSettings->logo_path)
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($homepageSettings->logo_path) }}"
                    alt="{{ $shopSettings->shop_name ?? 'Mon Sans Gluten by Angélique' }}"
                    class="mb-3 h-16 w-16 rounded-full object-cover"
                >
            @endif
            <p class="font-semibold text-ink">{{ $shopSettings->shop_name ?? 'Mon Sans Gluten by Angélique' }}</p>
            <p class="mt-2 text-sm text-ink-muted">
                {{ $shopSettings->address_line1 }}
                @if ($shopSettings->postal_code || $shopSettings->city)
                    <br>{{ $shopSettings->postal_code }} {{ $shopSettings->city }}
                @endif
            </p>
            @if ($shopSettings->facebook_url)
                <a href="{{ $shopSettings->facebook_url }}" class="mt-2 inline-block text-sm text-ink hover:text-sage" rel="noopener" target="_blank">Facebook</a>
            @endif
        </div>

        <div>
            <p class="font-semibold text-ink">Informations</p>
            <ul class="mt-2 space-y-1 text-sm text-ink-muted">
                <li><a href="{{ route('faq') }}" class="hover:text-sage">FAQ</a></li>
                @foreach ($footerPages as $page)
                    <li>
                        <a href="{{ route('content.show', $page) }}" class="hover:text-sage">
                            {{ $page->title }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div>
            <p class="font-semibold text-ink">Contact</p>
            <ul class="mt-2 space-y-1 text-sm text-ink-muted">
                @if ($shopSettings->contact_email)
                    <li><a href="mailto:{{ $shopSettings->contact_email }}" class="hover:text-sage">{{ $shopSettings->contact_email }}</a></li>
                @endif
                @if ($shopSettings->contact_phone)
                    <li>{{ $shopSettings->contact_phone }}</li>
                @endif
            </ul>
        </div>

        <div>
            <p class="font-semibold text-ink">Cookies</p>
            <button type="button" class="mt-2 text-sm text-ink-muted hover:text-sage" data-tarteaucitron-manager>
                Gérer mes préférences de cookies
            </button>
        </div>
    </div>

    <div class="border-t border-line px-4 py-4 text-center text-xs text-ink-muted">
        &copy; {{ now()->year }} {{ $shopSettings->shop_name ?? 'Mon Sans Gluten by Angélique' }}. Tous droits réservés.
    </div>
</footer>
