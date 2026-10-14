<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Alertes de sécurité
    |--------------------------------------------------------------------------
    |
    | Adresse qui reçoit toutes les alertes (fichiers modifiés, fichier PHP
    | dans un dossier d'upload, connexion admin depuis une nouvelle IP).
    | Choix de Ian (14/10/2026) : support@yafar.org.
    |
    */
    'alert_email' => env('SECURITY_ALERT_EMAIL', 'support@yafar.org'),

    /*
    |--------------------------------------------------------------------------
    | Surveillance d'intégrité des fichiers
    |--------------------------------------------------------------------------
    |
    | `watched` : fichiers et dossiers du code, comparés (SHA-256) à la
    | référence prise au déploiement (`security:integrity-snapshot`).
    | `uploads` : dossiers qui changent normalement (images, uploads) : on n'y
    | compare rien, mais aucun fichier exécutable ne doit jamais y apparaître.
    | Chemins relatifs à la racine du projet.
    |
    */
    'integrity' => [
        'root' => base_path(),
        'manifest' => storage_path('app/private/security/integrity-manifest.json'),
        'watched' => [
            'app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'vendor',
            'artisan', 'composer.json', 'composer.lock', '.env',
        ],
        'uploads' => ['storage/app'],
    ],
];
