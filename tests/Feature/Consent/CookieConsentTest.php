<?php

declare(strict_types=1);

// T22 : tarteaucitron + Google Consent Mode v2 + GTM. GA4 n'est JAMAIS dans le code (CLAUDE.md §4).

test('en production avec un ID GTM, le consent default « denied » précède tarteaucitron et aucun tag GA4 n\'est en dur', function () {
    app()['env'] = 'production';
    config(['services.gtm.id' => 'GTM-KFK55VB8']);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain("gtag('consent', 'default'")
        ->toContain('ad_storage: \'denied\'')
        ->toContain('analytics_storage: \'denied\'')
        ->toContain('ad_user_data: \'denied\'')
        ->toContain('ad_personalization: \'denied\'')
        ->toContain('wait_for_update: 500')
        ->toContain('vendor/tarteaucitron/tarteaucitron.min.js')
        ->toContain('"gtmId":"GTM-KFK55VB8"')
        ->toContain('data-tarteaucitron-manager')
        ->not->toContain('G-XF6EHRRV48')
        ->not->toContain('googletagmanager.com/gtm.js')
        ->not->toContain('onclick');

    // Le consent par défaut doit être posé avant le script tarteaucitron (qui charge GTM).
    expect(strpos($html, "gtag('consent', 'default'"))
        ->toBeLessThan(strpos($html, 'tarteaucitron.min.js'));
});

test('hors production, ni GTM ni tarteaucitron ni bouton de préférences', function () {
    config(['services.gtm.id' => 'GTM-KFK55VB8']);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('tarteaucitron')
        ->not->toContain('GTM-KFK55VB8')
        ->not->toContain("gtag('consent'")
        ->not->toContain('data-tarteaucitron-manager');
});

test('en production sans ID GTM (ou avec la valeur factice), rien n\'est chargé', function (?string $id) {
    app()['env'] = 'production';
    config(['services.gtm.id' => $id]);

    $this->get('/')->assertOk()
        ->assertDontSee('tarteaucitron', false)
        ->assertDontSee('data-tarteaucitron-manager', false);
})->with([null, '', 'GTM-XXXXXXX', 'pas-un-id', 'GTM-"><script>']);

test('la CSP autorise Google Tag Manager et Google Analytics', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy-Report-Only');

    expect($csp)->toContain('https://www.googletagmanager.com')
        ->toContain('https://*.google-analytics.com')
        ->toContain('https://challenges.cloudflare.com');
});
