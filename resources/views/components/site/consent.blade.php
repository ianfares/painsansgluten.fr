{{--
    Consentement cookies (T22) : Consent Mode v2 + tarteaucitron auto-hébergé.
    Ne rend RIEN hors production ou sans identifiant GTM valide (voir CookieConsent).
    Ordre garanti : 1) consent « default denied » en ligne, 2) tarteaucitron (defer),
    3) resources/js/consent.js (Vite) qui l'initialise ; GTM n'est chargé par
    tarteaucitron qu'après acceptation. Aucun tag GA4 ici : GA4 est dans GTM (docs/GTM.md).
--}}
@php($consent = app(\App\Services\Consent\CookieConsent::class))
@if ($consent->isActive())
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('consent', 'default', {
            ad_storage: 'denied',
            analytics_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
            wait_for_update: 500
        });
        var tarteaucitronForceLanguage = 'fr';
    </script>
    <script type="application/json" id="consent-config">{!! json_encode(['gtmId' => $consent->gtmId(), 'privacyUrl' => url('/politique-de-confidentialite')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) !!}</script>
    <script src="{{ asset('vendor/tarteaucitron/tarteaucitron.min.js') }}" defer></script>
@endif
