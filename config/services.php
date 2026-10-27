<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Stripe Checkout hébergé (T14, PLAN.md §10). Clés de test en local/préprod.
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'session_expires_minutes' => 30,
    ],

    // Chronopost Web Services (T11, PLAN.md §8.1). Compte de test en local,
    // identifiants du contrat en prod (fournis par Chronopost après validation).
    // Le code produit est un réglage du back-office (Paramètres > Expédition).
    'chronopost' => [
        'account_number' => env('CHRONOPOST_ACCOUNT_NUMBER'),
        'password' => env('CHRONOPOST_WS_PASSWORD'),
        // WSDL embarqué dans le dépôt : pas de téléchargement à chaque appel.
        'relay_wsdl' => resource_path('chronopost/PointRelaisServiceWS.wsdl'),
        'shipping_wsdl' => resource_path('chronopost/ShippingServiceWS.wsdl'),
        'tracking_wsdl' => resource_path('chronopost/TrackingServiceWS.wsdl'),
        // Format d'étiquette : réglage back-office `shipping.chronopost_label_mode` (27/10/2026).
        // Service 0 = livraison en semaine (expédition le jeudi, livraison le vendredi ; pas de samedi).
        'label_service' => '0',
        // Délai plus long que la recherche de relais : génération d'un PDF côté Chronopost.
        'label_timeout_seconds' => 15,
        'timeout_seconds' => 5,
        'relay_cache_minutes' => 60,
        'relay_max_results' => 10,
        'relay_max_distance_km' => 40,
    ],

    // Cloudflare Turnstile (anti-robot du formulaire de contact).
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    // Google Tag Manager (T22). GA4 est configuré DANS le conteneur GTM, jamais dans le code.
    // Chargé uniquement en production, après consentement (tarteaucitron).
    'gtm' => [
        'id' => env('GTM_ID'),
        // Test sur la préprod uniquement (GTM Aperçu) : à remettre à false après les tests.
        'enabled_outside_production' => (bool) env('GTM_ENABLED_OUTSIDE_PRODUCTION', false),
    ],

];
