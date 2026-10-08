# JOURNAL.md — Historique des sessions IA

> Ce fichier est le point d'entrée unique pour reprendre le contexte d'une session à l'autre. Plusieurs IA (plusieurs comptes Claude Code + ChatGPT) travaillent **en séquentiel** sur ce projet.
>
> **Règle (voir CLAUDE.md §7)** : toute IA qui intervient lit ce fichier en entier au démarrage, et y ajoute une entrée à la fin de sa tâche — sans jamais modifier ou supprimer les entrées précédentes. C'est un complément au compte rendu donné à l'humain et au commit Git, pas un remplacement.

## Format d'une entrée

**Le corps de l'entrée est le compte rendu complet au format `QUALITE.md` §10** (RÉSUMÉ, FICHIERS CRÉÉS/MODIFIÉS, ANALYSE D'IMPACT, SÉCURITÉ, PERFORMANCE, TESTS, QUALITÉ, DOCUMENTATION, À RENSEIGNER/QUESTIONS OUVERTES, POINTS À RELIRE PAR UN HUMAIN, AJOUTÉ AU BACKLOG), précédé de cet en-tête et suivi d'une dernière ligne :
```
## [AAAA-MM-JJ HH:MM] — [IA] — TXX — [titre]
STATUT : terminée / bloquée / partielle
(... compte rendu QUALITE.md §10 ...)
PROCHAINE TÂCHE SUGGÉRÉE : ...
```
Ne jamais utiliser un format allégé — voir `QUALITE.md` §10 pour le détail de chaque champ.

---

## Entrées

## [2026-10-05 22:00] — Claude Sonnet 5 — (cadrage) — Analyse des documents reçus, maquettes, mise en place du dépôt
STATUT : terminée
FAIT :
- Dépôt Git local initialisé (branche `main`).
- Analyse complète de `PLAN.md` (29 sections) et des 26 fiches de tâches `tasks/T00-T25.md` (synthèses déléguées à 2 agents de lecture).
- 3 maquettes interactives (front-office, espace client, back-office) publiées en artifact Design : https://claude.ai/artifact/Q9T5jXU1e8GT87n5euZ2Py — inspirées du vrai site https://painsansgluten.fr (couleurs, typographie) et d'un comparatif rapide de https://www.le-petit-minotier.fr.
- Mise en place du système d'historique multi-IA : `docs/JOURNAL.md` (ce fichier) + ajout de la section 7 dans `CLAUDE.md`.
- Copie de `CLAUDE.md`, `PLAN.md`, `CHECKLIST-PROD.md`, `tasks/` à la racine du dépôt (le dossier `documents/` original est conservé tel quel comme archive brute reçue du client).
FICHIERS : CLAUDE.md (racine), PLAN.md (racine), CHECKLIST-PROD.md (racine), tasks/ (racine), docs/BACKLOG.md, docs/JOURNAL.md
TESTS : sans objet (pas de code applicatif à ce stade)
À RENSEIGNER / QUESTIONS OUVERTES :
- Les 17 paramètres critiques listés dans PLAN.md §27 (TVA, textes légaux, identifiants Chronopost/Stripe/Brevo, coordonnées, assets HD, etc.) restent à fournir par la cliente / Ian / le comptable. Voir PLAN.md §27 pour le détail.
RISQUES / À RELIRE PAR UN HUMAIN :
- Planning : 1 semaine est optimiste pour une V1 "code complet en préprod" ; cible réaliste estimée à ~10 jours ouvrés avec 2 Claude en parallèle. Décision client : on vise quand même la fin de semaine, en acceptant de pousser si besoin.
AJOUTÉ AU BACKLOG :
- QR code de traçabilité produit (page publique par token, allergènes/composition/date de fabrication) — voir docs/BACKLOG.md.
PROCHAINE TÂCHE SUGGÉRÉE : T00 — Initialisation du projet (aucune dépendance aux 17 éléments "À RENSEIGNER" ; environnement local vérifié OK : PHP 8.3.6, Composer, Node, MariaDB 10.11 actifs).

---

## [2026-10-05 23:45] — Claude Sonnet 5 — T00 — Initialisation du projet
STATUT : terminée
RÉSUMÉ : Squelette Laravel 13.34 installé à la racine du dépôt, avec Filament 3.3.56 (panneau `/admin`, guard `admin` dédié, table `admins` distincte de `users`), Livewire (dépendance de Filament), Tailwind 4 + Vite (déjà fournis par le scaffold Laravel), Pest 4.7 (remplace PHPUnit), Larastan niveau 5, Pint (avec `declare_strict_types` auto-appliqué). Commande `php artisan make:admin` créée et testée. `QUALITE.md` reçu du client et relié à `CLAUDE.md` (document complémentaire obligatoire, son format de compte rendu §10 devient la référence unique). Règle CVE/obsolescence ajoutée à `CLAUDE.md` §2. Fichiers non applicatifs rangés dans `docs/reference/`.
FICHIERS CRÉÉS / MODIFIÉS :
- Squelette Laravel complet (`app/`, `routes/`, `resources/`, `database/`, `config/`, `public/`, `tests/`, `composer.json`, `package.json`, etc.)
- `app/Models/Admin.php`, migration `create_admins_table`, `app/Console/Commands/MakeAdminCommand.php`
- `config/admin.php` (nouveau), `config/auth.php` (guard + provider `admin`), `config/app.php` (timezone lu depuis env)
- `app/Providers/Filament/AdminPanelProvider.php` (guard + path configurables)
- `phpstan.neon`, `pint.json`, `phpunit.xml` (DB de test MariaDB dédiée)
- `.env`, `.env.example`, `.gitignore`, `.editorconfig`, `.gitattributes`, `.npmrc`
- `tests/Feature/AdminPanelTest.php`, `tests/Feature/ExampleTest.php` et `tests/Unit/ExampleTest.php` (convertis en Pest)
- `README.md` (nouveau), `docs/DECISIONS.md` (nouveau)
- Déplacés : `audit-painsansgluten.html` et `documents/` → `docs/reference/`
ANALYSE D'IMPACT : Aucun code métier préexistant — initialisation pure, aucun appelant à mettre à jour. Le changement de `config/app.php` (`timezone`) et l'ajout de `config/admin.php` sont des fondations utilisées par toutes les tâches suivantes (Europe/Paris partout, chemin BO configurable).
SÉCURITÉ : Guard `admin` strictement séparé du guard `web` (testé : un client connecté ne peut pas accéder à `/admin`, redirigé vers la connexion BO). `ADMIN_PATH` exposé uniquement via `config/admin.php` (jamais `env()` hors `config/`). Aucun secret dans les fichiers versionnés (mot de passe DB local vérifié absent de `phpunit.xml` et de `.env.example`, uniquement dans `.env` gitignoré). `composer audit` et `npm audit` : 0 vulnérabilité. 2FA admin non implémentée (prévue T03, hors périmètre T00).
PERFORMANCE : Sans objet à ce stade (aucune route métier, pas de requête N+1 possible).
TESTS : 4 tests Pest (2 préexistants convertis + 2 nouveaux sur le panneau admin), exécutés sur une vraie base MariaDB de test (`boulangerie_gluten_test`), pas SQLite — choix documenté dans `docs/DECISIONS.md`. Suite complète : 4 passés / 0 échec.
QUALITÉ : `pint --test` ✓ (`declare_strict_types` appliqué à tout le squelette), `phpstan analyse` niveau 5 ✓ (0 erreur), `composer audit` ✓ (0 faille), `npm audit` ✓ (0 faille, exécuté à l'install).
DOCUMENTATION : `README.md` créé (installation locale, commandes, structure du dépôt), `docs/DECISIONS.md` créé (versions, choix MariaDB pour les tests, rangement `docs/reference/`), `.env.example` complet et commenté, `CLAUDE.md` mis à jour (§2 règle CVE, lien vers `QUALITE.md`, §6/§7 pointent vers le format de compte rendu `QUALITE.md` §10).
À RENSEIGNER / QUESTIONS OUVERTES : aucune pour T00 lui-même (zéro dépendance aux 17 paramètres de `PLAN.md` §27). Reste ouvert pour les tâches suivantes : identifiants Chronopost (T01, bloquant J1), taux de TVA et paramètres d'expédition (T02/T04).
POINTS À RELIRE PAR UN HUMAIN : choix de faire tourner les tests sur MariaDB plutôt que SQLite (léger surcoût de configuration, mais cohérence avec la prod) ; abandon des fichiers `AGENTS.md`/`CLAUDE.md`/`README.md` générés par défaut par Laravel 13 au profit des fichiers du projet.
AJOUTÉ AU BACKLOG : aucun ajout pour cette tâche.
PROCHAINE TÂCHE SUGGÉRÉE : T01 — Spike Chronopost (CRITIQUE, priorité absolue J1 selon le README de cadrage) : nécessite les identifiants Chronopost (compte + mot de passe WS), actuellement en placeholder dans `.env`.

---

## [2026-10-06 00:20] — Claude Sonnet 5 — T02 — Modèle de données, migrations, seeders
STATUT : terminée
RÉSUMÉ : T01 (Chronopost) reste bloquée en attendant les identifiants du client — passage à T02, qui ne dépend que de T00. Modèle de données complet créé : 18 migrations (table `users` adaptée sans toucher à la migration déjà mergée, + 17 nouvelles tables), 4 enums PHP (`OrderStatus` avec la table de transitions PLAN §9.2, `PaymentMethod`, `InvoiceType`, `Allergen` avec les 14 allergènes réglementaires), 17 modèles Eloquent avec relations et casts typés, 17 factories, et 4 seeders (catégories, pages légales placeholder, redirections Shopify, données de démo garde-foutées contre la production).
FICHIERS CRÉÉS / MODIFIÉS :
- `database/migrations/2026_10_05_223132..223152_*.php` (18 fichiers)
- `app/Enums/{OrderStatus,PaymentMethod,InvoiceType,Allergen}.php`
- `app/Models/{Address,Category,Product,Cart,CartItem,Order,OrderItem,OrderStatusHistory,Payment,StripeEvent,Invoice,InvoiceSequence,ShippingRate,ClosedDate,Page,FaqItem,Redirect}.php` + `User.php` modifié
- `database/factories/*Factory.php` (17 fichiers)
- `database/seeders/{CategorySeeder,PageSeeder,RedirectSeeder,DemoSeeder,DatabaseSeeder}.php`
- `tests/Feature/DatabaseSchemaTest.php` (9 tests)
- `docs/DECISIONS.md` (5 décisions documentées)
ANALYSE D'IMPACT : Modifie `users` (migration additive, pas de perte de données possible : base encore vide en dev/test) ; `App\Models\User` et sa factory mis à jour en conséquence (seul appelant existant, couvert par les tests T00 déjà verts après la modif).
SÉCURITÉ : Montants systématiquement en centimes (`unsignedInteger`), jamais de float. `$fillable` explicite (attribut `#[Fillable]`) sur chaque modèle, aucun `$guarded = []`. Contraintes d'unicité en base (pas seulement applicatives) : `orders.number`, `orders.token`, `stripe_events.event_id`, `invoices.number`, `cart_items` (cart_id+product_id) — toutes testées (`QueryException` attendue). Suppression en cascade jamais appliquée aux commandes/factures (`restrictOnDelete`/`nullOnDelete` choisis consciemment). Aucun texte juridique inventé : pages légales seedées non publiées avec placeholder neutre.
PERFORMANCE : Index ajoutés sur toutes les colonnes de recherche/tri fréquentes (slugs, `reference`, `orders.status`, `orders.planned_ship_date`, `products.is_published+is_available`, etc.), en plus des unicités qui indexent déjà `number`/`token`/`event_id`.
TESTS : 9 nouveaux tests Pest (factories valides, relations catégorie/produit et commande/lignes/historique/factures, cast enum, 3 contraintes d'unicité, `migrate:fresh --seed`). Suite complète : **13 passés / 0 échec** (4 T00 + 9 T02).
QUALITÉ : `pint --test` ✓, `phpstan analyse` niveau 5 ✓ (0 erreur), `composer audit` ✓ (0 faille).
DOCUMENTATION : `docs/DECISIONS.md` complété (5 entrées : séparation users, JSON du snapshot relais, report du seed des taux de TVA à T04, règle de redirection générique reportée à T20, encodage des transitions de statut sur l'enum).
À RENSEIGNER / QUESTIONS OUVERTES : taux de TVA réels (reporté à T04, nécessite le comptable) ; contenu des 6 pages légales (nécessite la cliente/un juriste) ; champs exacts du "snapshot relais" une fois le WS Chronopost testé en T01 (actuellement JSON libre, pourra être affiné).
POINTS À RELIRE PAR UN HUMAIN : choix de stocker `relay_snapshot` en JSON plutôt qu'en colonnes dédiées — à reconfirmer une fois T01 fait et la forme exacte de la réponse Chronopost connue.
AJOUTÉ AU BACKLOG : aucun ajout pour cette tâche.
PROCHAINE TÂCHE SUGGÉRÉE : T03 — Authentification clients et administrateurs (dépend de T02, aucun blocage externe) ; T01 (Chronopost) reste la priorité dès réception des identifiants.

---

## [2026-10-06 01:10] — Claude Sonnet 5 — (décision) — 2FA admin reporté en V2
STATUT : terminée
RÉSUMÉ : Décision du client, en cours de préparation de T03 : le 2FA obligatoire sur le back-office (mentionné à plusieurs endroits dans PLAN.md, QUALITE.md, CHECKLIST-PROD.md, tasks/T03) est retiré du périmètre V1 et reporté en V2.
FICHIERS CRÉÉS / MODIFIÉS : `PLAN.md` (amendement en tête), `QUALITE.md` §2.4, `CHECKLIST-PROD.md`, `tasks/T03-authentification.md`, `docs/BACKLOG.md`, `docs/DECISIONS.md`.
ANALYSE D'IMPACT : Aucun sur T00/T02 (déjà livrées, ne touchaient pas à l'auth). Change directement le périmètre de T03 (pas de package 2FA à installer).
SÉCURITÉ : Risque accepté par le client (connexion admin par mot de passe seul en V1, guard séparé + rate limiting restent en place).
À RENSEIGNER / QUESTIONS OUVERTES : aucune.
AJOUTÉ AU BACKLOG : 2FA admin (V2).
PROCHAINE TÂCHE SUGGÉRÉE : T03, sans 2FA.

---

## [2026-10-06 01:45] — Claude Sonnet 5 — T03 — Authentification clients et administrateurs
STATUT : terminée
RÉSUMÉ : Authentification client complète via Laravel Fortify (guard `web`) : inscription (prénom/nom/téléphone/email/mot de passe), connexion, mot de passe oublié/réinitialisation, vérification d'email obligatoire avant `/mon-compte`, mise à jour profil/mot de passe. URLs en français. 2FA et passkeys explicitement désactivés (décision ci-dessus) ; leurs migrations ont été supprimées plutôt que laissées mortes. Guard `admin` (T00) non affecté, testé à nouveau étanche.
FICHIERS CRÉÉS / MODIFIÉS :
- `config/fortify.php`, `app/Providers/FortifyServiceProvider.php` (+ enregistré dans `bootstrap/providers.php`)
- `app/Actions/Fortify/{CreateNewUser,UpdateUserProfileInformation}.php` (adaptés first_name/last_name/phone)
- `app/Models/User.php` (implémente `MustVerifyEmail`, accesseur `name`)
- `resources/views/components/layouts/auth.blade.php`, `resources/views/auth/{login,register,forgot-password,reset-password,verify-email}.blade.php`, `resources/views/compte/stub.blade.php`
- `routes/web.php` (`/mon-compte` protégée `auth`+`verified`)
- `tests/Feature/Auth/{RegistrationTest,LoginTest,PasswordResetTest}.php`
- Supprimés : migrations `add_two_factor_columns_to_users_table`, `create_passkeys_table`
ANALYSE D'IMPACT : Aucun appelant existant cassé (T00/T02 n'utilisaient pas l'auth client). `App\Models\User` modifié (ajout interface + trait + accesseur) : rétrocompatible, testé.
SÉCURITÉ : Rate limiting 5/min (email+IP) sur la connexion, testé (HTTP 429 au-delà). Vérification d'email obligatoire avant tout accès à l'espace client, testée. Validation serveur systématique (Form via Fortify + règles explicites). Aucun secret, aucun mécanisme cryptographique maison (Hash::make natif). Séparation guard `admin`/`web` re-testée après l'installation de Fortify.
PERFORMANCE : Sans objet (pas de requêtes N+1 introduites).
TESTS : 10 nouveaux tests Pest (inscription + validation téléphone obligatoire, vérification email bloquante puis déblocante, connexion succès/échec, rate limiting, demande + exécution + échec de réinitialisation de mot de passe). Suite complète : **23 passés / 0 échec**.
QUALITÉ : `pint --test` ✓, `phpstan` niveau 5 ✓ (0 erreur, après correction d'un `instanceof` toujours vrai détecté par l'outil), `composer audit` ✓ (0 faille — note : `laravel/passkeys`, `pragmarx/google2fa`, `bacon/bacon-qr-code` installés comme dépendances dures de Fortify mais non utilisés par notre code).
DOCUMENTATION : `docs/DECISIONS.md` complété (choix Fortify, désactivation 2FA/passkeys, URLs françaises, vues non stylées).
À RENSEIGNER / QUESTIONS OUVERTES : aucune pour T03.
POINTS À RELIRE PAR UN HUMAIN : vues d'authentification volontairement non stylées (texte brut) — à ne pas prendre pour la version finale, le design arrive en T06/T18.
AJOUTÉ AU BACKLOG : rien de nouveau (2FA déjà ajouté dans la décision ci-dessus).
PROCHAINE TÂCHE SUGGÉRÉE : T04 — Paramètres boutique et apparence (dépend de T02, T03 ; aucun blocage externe pour construire l'infrastructure de paramètres, mais les **valeurs réelles** resteront "À RENSEIGNER" tant que la cliente/le comptable n'ont pas répondu). T01 (Chronopost) reste prioritaire dès réception des identifiants.

---

## [2026-10-06 02:30] — Claude Sonnet 5 — T04 — Paramètres boutique et apparence
STATUT : terminée
RÉSUMÉ : Infrastructure complète de paramètres via `spatie/laravel-settings` : 6 groupes (boutique, facturation, virement, expédition, accueil/apparence, SEO), chacun avec sa page Filament. CRUD Filament pour `shipping_rates` et `closed_dates`. Service unique `ConfigurationStatus` + widget d'alerte "Configuration incomplète" sur le tableau de bord. Règle de validation IBAN maison. Toutes les valeurs métier restent vides sauf les 2 explicitement validées (délai virement 5j, quantité max 20) et 2 textes déjà rédigés dans PLAN.md lui-même.
FICHIERS CRÉÉS / MODIFIÉS :
- `app/Settings/{Shop,Billing,BankTransfer,Shipping,Homepage,Seo}Settings.php`
- `app/Filament/Pages/Manage{Shop,Billing,BankTransfer,Shipping,Homepage,Seo}Settings.php`
- `app/Filament/Resources/{ShippingRate,ClosedDate}Resource.php` (+ pages générées)
- `app/Filament/Widgets/ConfigurationAlertsWidget.php` + vue associée
- `app/Services/Settings/ConfigurationStatus.php`, `app/Rules/Iban.php`
- `database/migrations/2026_10_05_225200_create_settings_table.php`, `database/settings/2026_10_05_225201_create_settings_groups.php`
- `app/Models/Admin.php` (+ `HasFactory`), `database/factories/AdminFactory.php` (manquait depuis T00)
- Tests : `tests/Unit/IbanRuleTest.php`, `tests/Feature/Settings/{ConfigurationStatusTest,SettingsPagesTest}.php`
ANALYSE D'IMPACT : Ajout de `HasFactory` sur `App\Models\Admin` (manquait depuis T00, découvert en écrivant les tests de ce groupe) — rétrocompatible, aucun appelant cassé.
SÉCURITÉ : IBAN validé côté serveur (format + somme de contrôle). Aucun secret ajouté en dur. Upload de fichiers (logo/bannière) limité aux images (`FileUpload::image()`), stockage disque `public` standard Filament.
PERFORMANCE : Widget d'alerte volontairement non lazy (calcul trivial) ; pas de requêtes N+1 (lectures settings mises en cache par le package lui-même).
TESTS : 9 nouveaux tests Pest (IBAN valide/invalide/espaces, statut de configuration incomplet puis complet, accès aux 6 pages de paramètres, CRUD frais de port/jours fermés, alerte visible sur le dashboard). Suite complète : **32 passés / 0 échec**.
QUALITÉ : `pint --test` ✓, `phpstan` niveau 5 ✓ (0 erreur), `composer audit` ✓, `npm audit` ✓ (0 faille).
DOCUMENTATION : `docs/DECISIONS.md` complété (choix du package, FileUpload simple vs medialibrary différé à T05, service unique, widget non lazy, ordre des migrations settings).
À RENSEIGNER / QUESTIONS OUVERTES : toutes les valeurs métier des 6 groupes de paramètres (voir PLAN.md §27) — infrastructure prête, en attente des réponses cliente/Ian/comptable.
POINTS À RELIRE PAR UN HUMAIN : **déviation de process repérée après coup** — cette tâche a été commencée et commitée directement sur `develop` au lieu de `feature/T04-settings` (oubli, CLAUDE.md §3.2 non respecté sur le coup). Corrigé a posteriori en posant une branche `feature/T04-settings` sur le même commit pour garder une référence nommée, mais l'historique ne montre pas de merge `--no-ff` comme pour T00/T02/T03. Sans conséquence fonctionnelle, mais à surveiller pour les tâches suivantes : **toujours vérifier `git branch` avant le premier commit d'une tâche.**
AJOUTÉ AU BACKLOG : rien de nouveau.
PROCHAINE TÂCHE SUGGÉRÉE : T05 — Catalogue dans le back-office (dépend de T02, T04 ; aucun blocage externe, peut être fait intégralement maintenant).

---

## [2026-10-06 03:40] — Claude Sonnet 5 — T05 — Catalogue dans le back-office
STATUT : terminée
RÉSUMÉ : Ressources Filament Catégories (tri glisser-déposer) et Produits (8 onglets conformes à PLAN §6.2 : Général, Descriptions, Composition, Nutrition, Conditionnement, Conseils, Images, SEO). Allergènes en 2 groupes de 14 cases. Prix saisi en euros dans le formulaire, stocké en centimes. `spatie/laravel-medialibrary` installé (a nécessité l'extension PHP `gd`, absente puis installée en cours de tâche) : image principale + galerie, conversions WebP (miniature/liste/fiche/zoom), texte alternatif obligatoire par image via un Repeater dédié (limite du plugin Filament documentée). Publication bloquée tant que les champs "obligatoires pour publier" manquent (brouillon toujours autorisé). Dupliquer, bascule disponible/indisponible, suppression bloquée si le produit a déjà été commandé.
FICHIERS CRÉÉS / MODIFIÉS :
- `app/Filament/Resources/{Category,Product}Resource.php` (+ pages Create/Edit/List)
- `app/Models/Product.php` (HasMedia, collections/conversions médias, relation `orderItems`, `hasBeenOrdered()`)
- `config/media-library.php`, `database/migrations/2026_10_06_020100_create_media_table.php`
- `tests/Feature/Catalog/{CategoryResourceTest,ProductResourceTest}.php`
ANALYSE D'IMPACT : Aucun appelant existant cassé. `Product` gagne une interface (`HasMedia`) et deux méthodes, rétrocompatible.
SÉCURITÉ : Suppression de produit commandé bloquée à 3 endroits (action ligne, ForceDelete, suppression groupée) — pas de contrainte BDD équivalente (`nullOnDelete`), donc règle strictement applicative, documentée comme telle pour éviter qu'une future IA s'y fie à tort comme garde-fou BDD.
PERFORMANCE : Conversions d'images **non queued** (exécutées à l'upload, pas en file d'attente) — choix délibéré pour un back-office à faible volume, documenté dans DECISIONS.md avec le compromis assumé.
TESTS : 10 nouveaux tests Pest : création en brouillon, blocage de publication si champs manquants, génération réelle d'une conversion WebP (vérifiée sur disque, `Storage::fake('public')`), duplication, blocage de suppression si commandé, persistance des textes alternatifs de galerie. Suite complète : **42 passés / 0 échec**.
QUALITÉ : `pint --test` ✓, `phpstan` niveau 5 ✓ (0 erreur, après correction d'un piège d'ordre d'appel `nonQueued()` avant `fit()`/`format()` sur les conversions), `composer audit` ✓, `npm audit` ✓.
DOCUMENTATION : `docs/DECISIONS.md` complété (medialibrary + gd, piège nonQueued/fit, solution alt-texte par Repeater, taux de TVA en Select de paliers légaux, suppression bloquée).
À RENSEIGNER / QUESTIONS OUVERTES : aucune pour T05 lui-même. Les ~70 vraies fiches produits (photos, textes) restent à saisir par la cliente une fois l'outil livré.
POINTS À RELIRE PAR UN HUMAIN : vérifier manuellement qu'une fiche complète (type « Mie'miam ») est saisissable en moins de 5 minutes (critère d'acceptation PLAN.md, non automatisable par un test).
AJOUTÉ AU BACKLOG : rien de nouveau.
PROCHAINE TÂCHE SUGGÉRÉE : T06 — Design système et layout public (dépend de T00 ; peut être fait en parallèle du reste, aucun blocage externe).

---

## [2026-10-06 04:50] — Claude Sonnet 5 — T06 — Design système et layout public
STATUT : terminée
RÉSUMÉ : Tokens visuels (couleurs, rayons, ombres) relevés directement sur https://painsansgluten.fr via les custom properties CSS de son thème (inspection navigateur, aucun code Liquid/CSS copié), transposés en thème Tailwind v4. Police Inter auto-hébergée (mécanisme natif Laravel 13 `laravel-vite-plugin/fonts`, pas de CDN). En-tête (bandeau d'annonce, méga-menu catégories avec visuel, icônes compte/panier), pied de page (pages légales, contact, Facebook, gestion cookies), composants Blade réutilisables (bouton, badge, carte produit, fil d'Ariane, alerte, champ de formulaire, tiroir, modale), menu mobile, page 404 personnalisée avec liens catégories. Alpine.js pour l'interactivité UI pure (tiroir/modale/menus), sans solliciter Livewire.
FICHIERS CRÉÉS / MODIFIÉS :
- `resources/css/app.css` (thème Tailwind), `vite.config.js` (police Inter), `resources/js/app.js` (Alpine)
- `resources/views/components/layouts/app.blade.php`, `components/site/{header,footer}.blade.php`
- `resources/views/components/ui/{button,badge,breadcrumb,alert,field,input,drawer,modal,product-card}.blade.php`
- `resources/views/errors/404.blade.php`, `resources/views/welcome.blade.php` (minimal, contenu réel en T07)
- `app/View/Composers/{Header,Footer}Composer.php`, `app/Providers/AppServiceProvider.php`
- `app/Models/Category.php` (+ visuel medialibrary `cover`), `app/Filament/Resources/CategoryResource.php` (champ image ajouté a posteriori)
- `tests/Feature/Site/PublicLayoutTest.php`
ANALYSE D'IMPACT : Ajout d'un visuel à `Category` (T05 déjà mergée) — rétrocompatible, n'affecte aucun test existant. `welcome.blade.php` remplacé : seule la page d'accueil Laravel par défaut, sans utilisateur réel à ce stade.
SÉCURITÉ : Liens externes (Facebook) avec `rel="noopener"`. `alt` systématique sur les images (y compris repli explicite quand absent). Focus visible sur les boutons (`focus-visible:outline`). Bouton "passer au contenu" (`skip to content`) pour la navigation clavier.
PERFORMANCE : Police auto-hébergée en WOFF2 (pas de requête tierce), images en `loading="lazy"` sur les cartes produit, visuel de catégorie en conversion WebP 200×200.
TESTS : 4 nouveaux tests Pest (en-tête/pied de page affichés avec les vraies données de paramètres, bandeau d'annonce conditionnel, méga-menu filtré sur les catégories actives, page 404 avec liens de catégories). Suite complète : **46 passés / 0 échec**.
QUALITÉ : `pint --test` ✓, `phpstan` niveau 5 ✓ (0 erreur), `composer audit` ✓, `npm audit` ✓ (0 faille, y compris `alpinejs` et le changement de police).
DOCUMENTATION : `docs/DECISIONS.md` complété (tokens relevés, choix police/Alpine, ajout du visuel catégorie, exception documentée pour la page 404 hors cycle de View Composer).
À RENSEIGNER / QUESTIONS OUVERTES : le vrai logo et les visuels HD (PLAN §27, point 17) restent à fournir — en attendant, le nom de la boutique s'affiche en texte dans l'en-tête.
POINTS À RELIRE PAR UN HUMAIN : **critère d'acceptation de cette tâche** = captures desktop/mobile comparées côte à côte avec le site actuel, validées par Ian — nécessite une revue humaine visuelle, non automatisable. Auto-corrigé en cours de tâche une tentative d'injection HTML non échappée (`{!! !!}` avec `old()`) dans le composant `<x-ui.input>` avant tout commit — jamais exposée.
AJOUTÉ AU BACKLOG : rien de nouveau.
PROCHAINE TÂCHE SUGGÉRÉE : T07 — Accueil, boutique, catégories, fiche produit (dépend de T05, T06 ; aucun blocage externe, peut être fait maintenant).

---

## [2026-10-06 05:20] — Claude Sonnet 5 — T07 — Pages catalogue (accueil, boutique, catégories, fiche produit)
STATUT : terminée
RÉSUMÉ : Pages publiques du catalogue : accueil (bannière, catégories, produits mis en avant depuis les paramètres, info livraison), `/boutique` + pages catégories (grille filtrable), fiche produit complète (galerie, prix, allergènes, nutrition, blocs PLAN §6.3 dans l'ordre et masqués si vides, "vous aimerez aussi"). Ajout de `mews/purifier` pour purifier à l'écriture les champs RichEditor affichés en `{!! !!}`.
FICHIERS : `app/Http/Controllers/Catalog/{Home,Boutique,Product}Controller.php`, `resources/views/catalog/{home,boutique,product}.blade.php`, routes catégories/produit/boutique, `Product::scopePublished()` + mutateurs de purification, `welcome.blade.php` supprimé (remplacé par `catalog.home`).
SÉCURITÉ : purification à l'écriture (`description`, `ingredients`), produit non publié → 404 (testé), visibilité catalogue strictement sur `is_published`.
TESTS : 7 nouveaux (pages 200, 404 si non publié, bouton ajouter absent si indisponible/non expédiable avec message paramétré, blocs vides masqués, grille sans brouillon). Suite complète : **53 passés / 0 échec**.
QUALITÉ : pint/phpstan niveau 5/composer+npm audit : tout vert.
À RENSEIGNER : aucun blocage pour cette tâche.
POINTS À RELIRE PAR UN HUMAIN : route catégorie générique à la racine (regex d'exclusion) — à mettre à jour à chaque nouvelle route racine (ex. `/faq`, `/contact` en T20). Bouton "Ajouter au panier" encore non fonctionnel (T08).
PROCHAINE TÂCHE SUGGÉRÉE : T08 — Panier.

---

## [2026-10-06 05:35] — Claude Sonnet 5 — T10 — Frais de port
STATUT : terminée
RÉSUMÉ : `ShippingCostCalculator::forWeight()` — tranches de poids, franco optionnel, exceptions `ShippingNotConfigured`/`WeightOutOfRange`. **Ordre réordonné** (T10/T09 avant T08, voir docs/DECISIONS.md) sous contrainte de temps réel annoncée par le client — T08 consommera directement ces services au lieu d'un texte de repli temporaire.
FICHIERS : `app/Services/Shipping/ShippingCostCalculator.php`, `app/Exceptions/Shipping/{ShippingNotConfigured,WeightOutOfRange}.php`, `tests/Feature/Shipping/ShippingCostCalculatorTest.php`.
TESTS : 5 nouveaux (grille vide, hors grille, bornes de tranches, franco atteint/désactivé). Suite complète : **58 passés / 0 échec**. pint/phpstan niveau 5 : vert.
PROCHAINE TÂCHE SUGGÉRÉE : T09 — Date d'expédition, puis T08 — Panier.

---

## [2026-10-06 05:50] — Claude Sonnet 5 — T09 — Date d'expédition
STATUT : terminée
RÉSUMÉ : `ShippingDateCalculator::forInstant()`, algorithme PLAN §8.4 (heure limite stricte, délai en jours ouvrés sautant les jours fermés, avance jusqu'au premier jour d'expédition non fermé), formatage FR, garde-fou 60 jours.
FICHIERS : `app/Services/Shipping/ShippingDateCalculator.php`, `tests/Feature/Shipping/ShippingDateCalculatorTest.php`.
TESTS : 12 nouveaux cas (tous ceux listés par la fiche T09). Suite complète : **70 passés / 0 échec**. pint/phpstan niveau 5/audits : tout vert.
POINTS À RELIRE PAR UN HUMAIN : interprétation de "jours fermés" vs "jours non ouvrés" dans le calcul du budget de délai — voir docs/DECISIONS.md, à reconfirmer en recette (T24).
PROCHAINE TÂCHE SUGGÉRÉE : T08 — Panier (peut maintenant consommer directement T09/T10, pas de texte de repli nécessaire).

---

## [2026-10-06 06:15] — Claude Sonnet 5 — T08 — Panier
STATUT : terminée
RÉSUMÉ : `CartService` (add/update/remove/merge/totals), tiroir latéral + page `/panier` en Livewire (`CartWidget`), bouton "Ajouter au panier" réutilisable (`AddToCartButton`, avec sélecteur de quantité sur la fiche produit). Retrait automatique des lignes devenues invalides avec message. Fusion du panier invité dans celui du client à la connexion. Purge planifiée quotidienne des paniers invités > 30 jours.
FICHIERS : `app/Services/Cart/CartService.php`, `app/Livewire/{CartWidget,AddToCartButton}.php` + vues, `app/Console/Commands/PurgeOldGuestCarts.php`, `routes/console.php` (schedule), `routes/web.php` (`/panier`), `app/Providers/AppServiceProvider.php` (listener Login, composer `CartCountComposer`), `resources/views/components/layouts/app.blade.php` (Livewire scripts/styles, tiroir réel).
SÉCURITÉ : IDOR vérifié (`CartWidget::authorizeItem`), quantité plafonnée côté serveur (jamais confiance au client), produit non commandable → 403 explicite sur `AddToCartButton::add()`.
TESTS : 11 nouveaux (service + Livewire). Suite complète : **81 passés / 0 échec**. pint/phpstan niveau 5/audits : tout vert.
À RENSEIGNER : aucun pour cette tâche (dépend des paramètres déjà en place depuis T04/T09/T10).
POINTS À RELIRE PAR UN HUMAIN : le panier calcule déjà le total avec frais de port — à re-vérifier visuellement une fois T06 pleinement appliqué au tiroir (styles Livewire).
PROCHAINE TÂCHE SUGGÉRÉE : T11 (sélecteur de relais Chronopost) reste bloquée par T01 (identifiants Chronopost). À défaut, T12 (commandes : création, statuts, transitions) peut démarrer sans Chronopost pour la partie machine à états, ou T03/T18 (espace client) pour enrichir l'authentification déjà posée.

---

## [2026-10-06 06:45] — Claude Sonnet 5 — T18 (anticipée, partielle) — Espace client
STATUT : partielle
RÉSUMÉ : À la demande explicite du client ("surtout l'interface cliente"), espace client construit par anticipation malgré la dépendance officielle à T12/T16 (non faites). Tableau de bord, "Mes commandes" (liste, IDOR vérifié), "Mes informations" (profil + mot de passe via les routes Fortify de T03, adresse de facturation), demande de suppression de compte. **"Mes factures" non construit** (dépend entièrement de T16).
FICHIERS : `app/Http/Controllers/Compte/AccountController.php`, `resources/views/compte/{dashboard,orders,informations}.blade.php`, `resources/views/components/compte/layout.blade.php`, routes `compte.*`, `tests/Feature/Compte/AccountTest.php`.
SÉCURITÉ : IDOR vérifié ("Mes commandes" filtré sur l'utilisateur connecté), `deletion_requested_at` non fillable (affectation directe + save), non connecté → redirigé vers connexion.
TESTS : 6 nouveaux. Suite complète : **87 passés / 0 échec**. pint/phpstan niveau 5/audits : tout vert.
À RENSEIGNER / QUESTIONS OUVERTES : aucun blocage technique, mais fonctionnellement incomplet tant que T12 (création de vraies commandes) et T16 (factures) ne sont pas faites.
POINTS À RELIRE PAR UN HUMAIN : **tâche volontairement anticipée hors du graphe de dépendances officiel** — à valider que ça ne pose pas de problème de cohérence avec une IA qui, en lisant tasks/T18-espace-client.md isolément, pourrait croire la tâche entièrement neuve. Le présent compte rendu et docs/DECISIONS.md documentent l'état réel (partiel).
PROCHAINE TÂCHE SUGGÉRÉE : T12 — Commandes : création, statuts et transitions (débloque T16 pour compléter "Mes factures", et T13/T14/T15 pour un vrai tunnel d'achat). T11 (Chronopost) reste bloquée par T01.

---

## [2026-10-06 07:10] — Claude Sonnet 5 — T12 — Commandes : création, statuts et transitions
STATUT : terminée
RÉSUMÉ : `CreateOrderAction` (snapshot complet, numéro séquentiel sans trou en réutilisant `invoice_sequences` avec `type='order'`, token aléatoire, totaux recalculés serveur) et `OrderStateMachine` (seul point d'écriture du statut, verrouillé, historisé, événements `OrderPaid/Shipped/Cancelled/Refunded`). Pas de listeners branchés sur les événements pour l'instant (arriveront avec T16/T19).
FICHIERS : `app/Actions/Orders/CreateOrderAction.php`, `app/Services/Orders/OrderStateMachine.php`, `app/Events/Orders/*.php`, `app/Exceptions/Orders/InvalidOrderTransition.php`, `app/Models/Order.php` (annotations `@property`), `tests/Feature/Orders/{CreateOrderActionTest,OrderStateMachineTest}.php`.
SÉCURITÉ : transition verrouillée (`lockForUpdate`), totaux jamais acceptés depuis une entrée externe (toujours `CartService::totals()`), aucune écriture directe de `status` ailleurs dans le code (vérifié).
TESTS : 27 nouveaux (les 12 transitions autorisées, les 5 interdites rejetées avec statut inchangé, historique, événements déclenchés une fois, numéros séquentiels, panier vidé, panier vide refusé). Suite complète : **113 passés / 0 échec**. pint/phpstan niveau 5 (annotations `@property` ajoutées pour corriger une lacune d'inférence Larastan sur les écritures de propriétés castées)/audits : tout vert.
À RENSEIGNER : aucun pour cette tâche.
POINTS À RELIRE PAR UN HUMAIN : répartition HT/TVA par taux moyen pondéré sur `orders` (une commande peut mélanger des produits à taux de TVA différents) — à affiner en T16 où la facture, elle, doit présenter un récapitulatif par taux.
PROCHAINE TÂCHE SUGGÉRÉE : T16 — Factures et avoirs (débloque "Mes factures" dans l'espace client déjà construit en T18). T11/T13/T14/T15 restent bloquées par T01.

---

## [2026-10-06 07:45] — Claude Sonnet 5 — T16 (partielle) — Factures et avoirs PDF
STATUT : partielle
RÉSUMÉ : `IssueInvoiceAction`/`IssueCreditNoteAction` (numérotation séquentielle via `SequenceGenerator` partagé avec T12, snapshot JSON immuable, PDF dompdf en stockage privé), listeners idempotents sur `OrderPaid`/`OrderRefunded`, téléchargement sécurisé (policy propriétaire ou URL signée invité 30j). "Mes factures" dans l'espace client (T18) est maintenant fonctionnel. **Non fait** : ressource Filament BO "Factures & avoirs" et export comptable CSV (PLAN §14) — reportés faute de temps.
FICHIERS : `app/Actions/Invoicing/{IssueInvoiceAction,IssueCreditNoteAction}.php`, `app/Services/Sequencing/SequenceGenerator.php` (refactor partagé avec `CreateOrderAction`), `app/Listeners/Orders/Generate{Invoice,CreditNote}On*.php`, `app/Policies/InvoicePolicy.php`, `app/Http/Controllers/Compte/InvoiceDownloadController.php`, `resources/views/pdf/invoice.blade.php`, `resources/views/compte/invoices.blade.php`, migration `alter_invoices_totals_signed` (correctif schéma).
SÉCURITÉ : policy IDOR testée (tiers refusé), URL signée pour invités, listeners idempotents (pas de doublon si événement rejoué), aucun texte/mention légale inventé (placeholders explicites si `BillingSettings` vide).
TESTS : 7 nouveaux. Suite complète : **120 passés / 0 échec**. pint/phpstan niveau 5 (annotations `@property` ajoutées sur `Invoice`)/audits : tout vert.
À RENSEIGNER : mentions légales de facturation, formats de numéros, taux de TVA — toujours en attente du comptable (PLAN §27).
POINTS À RELIRE PAR UN HUMAIN : **bug de schéma découvert et corrigé en cours de tâche** (colonnes `invoices.total_*` non signées, incompatibles avec les avoirs négatifs) — migration séparée ajoutée, à vérifier en relecture. Export CSV comptable et ressource Filament factures restent à faire.
PROCHAINE TÂCHE SUGGÉRÉE : ressource Filament "Factures & avoirs" + export CSV (complément T16), ou T20 (contenus : pages légales, FAQ, redirections) qui est entièrement débloquée et indépendante.

---

## [2026-10-06 08:15] — Claude Sonnet 5 — T20 — Contenus (pages, FAQ, redirections)
STATUT : terminée
RÉSUMÉ : Ressources Filament Pages/FAQ/Redirections. Résolveur d'URL unique (`SlugController`) pour catégories et pages au niveau racine. Page FAQ groupée avec JSON-LD. Middleware global `HandleLegacyRedirects` (redirections 301 Shopify depuis la table + règle générique `/products/{slug}`).
FICHIERS : `app/Filament/Resources/{Page,FaqItem,Redirect}Resource.php`, `app/Http/Controllers/Content/{SlugController,FaqController}.php`, `app/Http/Middleware/HandleLegacyRedirects.php`, `resources/views/content/{page,faq}.blade.php`, `routes/web.php` (route `content.show` remplace `categories.show`), `tests/Feature/Content/ContentPagesTest.php`.
TESTS : 8 nouveaux. Suite complète : **128 passés / 0 échec**. pint/phpstan niveau 5/audits : tout vert.
PROCHAINE TÂCHE SUGGÉRÉE : T21 (SEO technique, débloquée par T07+T20) ou finir la ressource BO Factures/export CSV (T16).

---

## [2026-10-06 09:30] — Claude Sonnet 5 — T13 — Tunnel de commande
STATUT : terminée (avec deux provisoires assumés et documentés — voir "À RENSEIGNER" et docs/DECISIONS.md)
CONTEXTE POUR LA PROCHAINE IA : cette tâche a été décidée par la cliente comme un **découplage volontaire** du blocage T01 (Chronopost) : au lieu d'attendre les identifiants/API Chronopost pour faire tout le reste de la chaîne de commande, T13 avance avec une étape "Relais" en saisie manuelle (provisoire, clairement marquée partout dans le code et l'UI), et enchaîne immédiatement sur **T14 (Stripe)** puis **T15 (virement)** — ce sont les 3 prochaines tâches à faire, dans cet ordre, sans attendre T01/T11. Lire docs/DECISIONS.md §T13 AVANT de toucher à ce code : plusieurs pièges y sont documentés (Blade `@disabled` sur un composant, règle de validation avec `|` dans une regex).
RÉSUMÉ : Composant Livewire `CheckoutWizard` (4 étapes : Coordonnées → Relais (provisoire) → Récapitulatif → Paiement), pré-rempli si client connecté. Validation complète à chaque étape (email/téléphone FR/CP, relais obligatoire, Corse refusée, CGV obligatoire). `pay()` recalcule tout côté serveur (`CartService::totals()`), bloque avec message neutre + `Log::critical()` si la config de livraison est incomplète, puis appelle `CreateOrderAction` (T12, inchangée) et redirige vers Stripe (page d'attente provisoire, T14 à venir) ou vers la page de confirmation. Page de confirmation (`/commande/confirmation/{token}`) avec instructions de virement (`BankTransferSettings`) ou statut de paiement carte, et bloc "Créer mon compte" si commande invité.
FICHIERS : `app/Livewire/CheckoutWizard.php`, `resources/views/livewire/checkout-wizard.blade.php`, `app/Http/Controllers/Checkout/CheckoutController.php`, `resources/views/checkout/{wizard,stripe-pending,confirmation}.blade.php`, `routes/web.php` (routes `checkout`, `checkout.stripe.start`, `checkout.confirmation` + ajout de `commande` à la liste d'exclusion du résolveur générique), `resources/views/auth/register.blade.php` (une ligne : préremplissage email depuis `?email=`), `tests/Feature/Checkout/CheckoutWizardTest.php`.
SÉCURITÉ : routes `checkout.stripe.start`/`checkout.confirmation` liées au `token` aléatoire de la commande (jamais son `id` auto-incrémenté) pour éviter toute énumération. Totaux et numéro de commande toujours recalculés/générés serveur (`CreateOrderAction`, inchangé depuis T12). Garde-fou double-clic sur "Payer" testé explicitement.
TESTS : 7 nouveaux (invité, connecté/pré-rempli, CGV non cochée, relais manquant, Corse refusée, configuration de livraison incomplète, double-clic). Tous les cas listés par la fiche tasks/T13-tunnel-commande.md sont couverts. Suite complète : **135 passés / 0 échec**. pint/phpstan niveau 5/`composer audit` : tout vert (pas de nouvelle dépendance ajoutée, donc pas de `npm audit` à relancer pour cette tâche).
À RENSEIGNER / PROVISOIRES À LEVER (dans l'ordre de priorité) :
1. **T11 (Chronopost)** reste bloquée par **T01** (identifiants + documentation API jamais fournis par la cliente). Tant que non levé, l'étape "Relais" du tunnel restera en saisie manuelle.
2. **T14 (Stripe)** : clés API Stripe de test peuvent être auto-générées par la cliente sur stripe.com (pas besoin d'attendre T01) — c'est la toute prochaine tâche à faire, elle remplacera le contenu de `CheckoutController::stripeStart()` par une vraie création de session Stripe Checkout + un webhook.
3. CGV/mention de rétractation (article L221-28 cité) : texte à faire valider par la cliente/son comptable avant mise en production réelle (texte générique de loi, mais le choix de l'appliquer aux produits de la boulangerie doit être confirmé).
POINTS À RELIRE PAR UN HUMAIN : le bouton "Payer Stripe" mène aujourd'hui à une page d'attente ("paiement en cours de finalisation"), pas à un vrai paiement — c'est attendu et documenté (provisoire T14), mais à vérifier visuellement en recette pour ne pas confondre avec un bug. Voir aussi docs/DECISIONS.md §T13 pour le détail des deux pièges techniques corrigés (Blade `@disabled` sur composant, regex de validation avec `|`).
PROCHAINE TÂCHE SUGGÉRÉE : **T14 — Paiement Stripe Checkout** (webhook, idempotence `stripe_events`, transition `OrderStateMachine` vers `Paid`), puis **T15 — Virement bancaire** (validation BO du virement reçu, relance/annulation planifiée). Ordre explicitement validé par la cliente : T13 → T14 → T15, sans attendre T01/T11.

---

## [2026-10-06 10:15] — Claude Sonnet 5 — T15 — Paiement par virement
STATUT : terminée
CONTEXTE POUR LA PROCHAINE IA : décision cliente ("ok va y pour le 15") de faire T15 **avant** T14 (ordre inverse de la suggestion précédente), car T15 ne dépend ni de Stripe ni de Chronopost/Colissimo — entièrement indépendante. **T14 (Stripe) reste donc la tâche suivante logique**, toujours non commencée. Lire docs/DECISIONS.md §T15 avant de toucher à ce code : le périmètre de `OrderResource` est **volontairement minimal** (juste ce que T15 exige), à **étendre** (pas recréer) en T17.
RÉSUMÉ : `ValidateBankTransferPaymentAction` (vérifie virement = bank_transfer + pending_payment, recalcule la date d'expédition, transitionne vers `paid` via `OrderStateMachine`, envoie l'email de confirmation). Action BO « Valider le virement reçu » sur une nouvelle ressource Filament `OrderResource` (liste + filtres statut/paiement + filtre bascule « Virements en attente »). Commande planifiée horaire `orders:process-bank-transfer-deadlines` : relance email unique à mi-délai (`cancel_after_days / 2`), annulation automatique à échéance si `auto_cancel_enabled`. 4 emails Markdown (`BankTransferInstructionsMail` envoyé à la commande, `BankTransferPaidMail`/`BankTransferReminderMail`/`BankTransferCancelledMail`), tous déjà prêts pour un vrai transport (Brevo, T19) sans modification à venir.
FICHIERS : `app/Actions/Orders/ValidateBankTransferPaymentAction.php`, `app/Exceptions/Orders/BankTransferValidationNotAllowed.php`, `app/Console/Commands/ProcessBankTransferDeadlinesCommand.php`, `app/Mail/BankTransfer{Instructions,Paid,Reminder,Cancelled}Mail.php` + vues `resources/views/emails/bank-transfer-*.blade.php`, `app/Filament/Resources/OrderResource.php` + `Pages/ListOrders.php`, migration `add_bank_transfer_reminder_sent_at_to_orders_table`, `app/Models/Order.php` (annotation `@property` pour `planned_ship_date`, piège Larastan déjà connu), `app/Livewire/CheckoutWizard.php` (envoi de l'email d'instructions à la création), `routes/console.php` (schedule horaire).
SÉCURITÉ : action BO protégée par exception dédiée (impossible de valider deux fois ou sur un paiement carte), toute la logique métier verrouillée en transaction (`lockForUpdate`, même pattern que `OrderStateMachine`), `bank_transfer_reminder_sent_at` non fillable en masse (seule la commande planifiée l'écrit).
TESTS : 15 nouveaux (8 sur `ValidateBankTransferPaymentAction`/commande planifiée, 3 sur `OrderResource`/Filament, 1 sur l'email de création via le tunnel T13 — voir tests/Feature/Orders/{BankTransferTest,OrderResourceTest}.php). Suite complète : **146 passés / 0 échec**. pint/phpstan niveau 5/`composer audit` : tout vert (aucune nouvelle dépendance).
À RENSEIGNER : les vraies coordonnées bancaires (IBAN/BIC/titulaire, `BankTransferSettings`, toujours en attente depuis T04) — sans elles, les emails envoyés affichent "communiqué très prochainement" à la place (pas de valeur inventée, CLAUDE.md §3.1).
POINTS À RELIRE PAR UN HUMAIN : vérifier en recette que le cron `php artisan schedule:run` tourne bien en prod/préprod (T24/T25) — sans lui, ni la relance ni l'annulation automatique ne se déclenchent jamais, silencieusement.
PROCHAINE TÂCHE SUGGÉRÉE : **T14 — Paiement Stripe Checkout** (webhook, idempotence `stripe_events`, transition vers `Paid`) — c'est la dernière pièce bloquante du tunnel de commande, le reste (virement, factures, espace client) est déjà fonctionnel.

---

## [2026-10-06 11:00] — Claude Sonnet 5 — T16 (complément) — Factures & avoirs : ressource BO + export CSV
STATUT : terminée (complète enfin T16, resté partiel depuis la session précédente)
CONTEXTE POUR LA PROCHAINE IA : la cliente n'a pas encore ses accès Stripe (compte à créer, aucune urgence de son côté) — **T14 reste donc en attente côté cliente**, pas bloqué techniquement. En attendant, décision explicite de la cliente de faire **T16 (complément) puis T17** plutôt que d'attendre. Lire docs/DECISIONS.md §T16 (complément) avant de toucher à `InvoiceResource`/`ExportInvoicesCsv` : le format du CSV est une proposition, **`À VALIDER` par le comptable**.
RÉSUMÉ : Ressource Filament `InvoiceResource` (liste factures/avoirs, filtre type, filtre période, téléchargement via URL signée réutilisant le contrôleur existant) — lecture seule, aucune édition possible. Page `ExportInvoicesCsv` (formulaire du/au → export CSV `;`/UTF-8 BOM : numéro, date, type, n° commande, client, moyen de paiement, HT/TVA pour les 4 taux légaux français, totaux), déclenché via `Livewire`/`response()->streamDownload()`, pas de route HTTP dédiée.
FICHIERS : `app/Filament/Resources/InvoiceResource.php` + `Pages/ListInvoices.php`, `app/Filament/Pages/ExportInvoicesCsv.php` + vue, `app/Models/Invoice.php` (annotation `@property Carbon $issued_at`), `tests/Feature/Invoicing/InvoiceResourceTest.php`.
SÉCURITÉ : aucune nouvelle policy (réutilise le mécanisme signé déjà audité en T16 initial) ; `canCreate() => false` sur `InvoiceResource` pour garantir qu'aucun formulaire de création/édition n'est jamais exposé par erreur sur un document immuable.
TESTS : 4 nouveaux (liste, filtre type, action téléchargement, contenu et format de l'export CSV sur une période incluant un cas hors-période pour vérifier le filtrage). Suite complète : **150 passés / 0 échec**. pint/phpstan niveau 5/`composer audit` : tout vert (aucune nouvelle dépendance).
À RENSEIGNER : format exact des colonnes CSV à faire valider par le comptable (PLAN §27, point 7) avant tout usage réel en production — ce qui existe est une proposition fonctionnelle, pas un format figé.
POINTS À RELIRE PAR UN HUMAIN : **glissement de process corrigé en cours de tâche** — le travail a été commencé par erreur directement sur `develop` au lieu d'une branche dédiée, corrigé avant tout commit (voir docs/DECISIONS.md). Aucun impact réel, mais à signaler par transparence.
PROCHAINE TÂCHE SUGGÉRÉE : **T17 — Back-office commandes/expéditions/clients, tableau de bord** (la cliente a validé qu'on enchaîne directement dessus). L'action "Rembourser" qui appelle Stripe devra rester un mock/placeholder tant que T14 n'est pas livrée (en attente des accès cliente). **T14 reste la priorité dès que les clés Stripe de test arrivent.**

---

## [2026-10-06 11:45] — Claude Sonnet 5 — T17 — Back-office commandes, expéditions, clients, tableau de bord
STATUT : terminée (remboursement carte volontairement non fonctionnel — voir ci-dessous, en attente de T14)
CONTEXTE POUR LA PROCHAINE IA : T14 (Stripe) reste **la seule tâche vraiment bloquée**, en attente que la cliente crée son compte Stripe (aucune urgence de son côté, confirmé en session). Tout le reste du périmètre V1 raisonnablement faisable sans Stripe/Chronopost est maintenant fait : tunnel de commande, virement, factures/avoirs + export CSV, back-office commandes complet. Lire docs/DECISIONS.md §T17 avant de toucher à `RefundOrderAction` : le remboursement carte est **un refus explicite**, pas un mock — à remplacer par un vrai appel Stripe dès que T14 est livrée, sans changer le reste de l'action.
RÉSUMÉ : `OrderResource` étendu (détail complet via Infolist — lignes, relais, historique, lien facture —, actions de transition `prepare`/`ship` (n° de suivi obligatoire)/`deliver`/`refund`, chacune visible uniquement selon le statut courant et protégée par `OrderStateMachine`). Nouvelle page `Expeditions` (commandes `paid`/`preparing` triées par date d'expédition, badge coloré aujourd'hui/en retard). Nouvelle ressource `UserResource` (Clients, lecture seule : recherche, détail avec adresses + commandes via `RelationManager`, badge "suppression demandée"). Nouvelle ressource `AdminResource` (CRUD, auto-suppression bloquée). Widget `OrdersStatsWidget` (CA TTC du mois, nb commandes du mois, virements en attente, à expédier aujourd'hui/en retard) sur le tableau de bord, à côté du widget d'alertes de configuration déjà existant (T04).
FICHIERS : `app/Filament/Resources/{Order,User,Admin}Resource.php` + leurs `Pages/`, `app/Filament/Resources/UserResource/RelationManagers/OrdersRelationManager.php`, `app/Filament/Pages/Expeditions.php` + vue, `app/Filament/Widgets/OrdersStatsWidget.php`, `app/Actions/Orders/{ShipOrderAction,RefundOrderAction}.php`, `app/Exceptions/Orders/RefundNotAvailable.php`, `tests/Feature/Orders/{OrderTransitionsTest,BackofficeExtrasTest}.php` + complément de `OrderResourceTest.php`.
SÉCURITÉ : chaque action de transition protégée par `OrderStateMachine` (impossible de forcer une transition interdite, même en appelant l'action BO directement) ; `UserResource` ne rend jamais le mot de passe (testé explicitement) ; un admin ne peut pas se supprimer lui-même.
TESTS : 15 nouveaux (visibilité des actions selon statut, expédition sans n° refusée, remboursement virement vs carte, vue Expéditions, clients/adresses/commandes, auto-suppression admin bloquée, calcul du widget de stats, rendu de la fiche détail commande). Suite complète : **165 passés / 0 échec**. pint/phpstan niveau 5/`composer audit` : tout vert (aucune nouvelle dépendance).
À RENSEIGNER : aucun pour cette tâche elle-même. Le remboursement carte reste bloqué par T14 (clés Stripe, toujours côté cliente).
POINTS À RELIRE PAR UN HUMAIN : le CA TTC du tableau de bord n'exclut pas les commandes remboursées dans le mois (limitation assumée, documentée dans DECISIONS — le chiffre comptable exact reste l'export CSV de T16). À vérifier en recette que les badges de couleur (aujourd'hui/en retard) sur la vue Expéditions sont lisibles pour une utilisatrice non technique.
PROCHAINE TÂCHE SUGGÉRÉE : **T14 — Paiement Stripe Checkout** dès que la cliente fournit ses clés de test (webhook, idempotence `stripe_events`, transition vers `Paid`, puis brancher le vrai remboursement dans `RefundOrderAction`). En attendant : T19 (emails Brevo, même logique "clé à venir, code déjà prêt" que T15), T21 (SEO technique), T23 (passe sécurité dédiée) restent disponibles sans dépendance externe.

---

## [2026-10-06 12:30] — Claude Sonnet 5 — (hors plan) — Logo client + refonte de la bannière d'accueil
STATUT : terminée
CONTEXTE POUR LA PROCHAINE IA : demande directe de la cliente, pas une tâche `tasks/`. Le logo (`logo.png` déposé à la racine par la cliente) est maintenant stocké comme un média BO classique (`storage/app/public/branding/logo.png`, `HomepageSettings::logo_path`/`favicon_path`). **Important** : ce fichier n'est **pas versionné dans Git** (comme tout média uploadé) — à re-uploader via Paramètres → Apparence sur tout nouvel environnement (préprod/prod, T25). Voir docs/DECISIONS.md pour le détail.
RÉSUMÉ : Logo affiché en en-tête, pied de page, favicon du site public et logo du panneau admin Filament. Bannière d'accueil entièrement refaite (badge "100% sans gluten", titre/sous-titre existants mieux mis en valeur, visuel à droite avec le logo en vedette dans un cadre dégradé + pastille flottante, formes décoratives en arrière-plan) — prévue pour s'améliorer automatiquement dès qu'une vraie photo sera uploadée via `banner_image_path` (aucune vraie photo fournie pour l'instant).
FICHIERS : `app/View/Composers/{CartCountComposer,FooterComposer}.php` (ajout de `homepageSettings`), `app/Providers/Filament/AdminPanelProvider.php` (`brandLogo`/`favicon`), `resources/views/components/site/{header,footer}.blade.php`, `resources/views/components/layouts/app.blade.php` (favicon), `resources/views/catalog/home.blade.php` (bannière).
TESTS : aucun nouveau test (changement purement visuel/de présentation, pas de nouvelle règle métier) ; suite complète inchangée exécutée pour non-régression : **165 passés / 0 échec**. pint/phpstan niveau 5/`composer audit` : tout vert. Vérifié visuellement via headless Chrome (desktop, mobile, page de connexion admin) avant de considérer la tâche terminée.
À RENSEIGNER : une version simplifiée du logo (icône seule, sans texte) serait plus lisible en favicon — à fournir par la cliente si elle le souhaite, non fabriquée ici (CLAUDE.md §3.1).
POINTS À RELIRE PAR UN HUMAIN : **glissement de process corrigé en cours de tâche** — travail commencé par erreur directement sur `develop`, corrigé avant tout commit en créant `feature/branding-logo-and-homepage-design` (même type de glissement déjà documenté en T04/T16, à surveiller : toujours créer la branche avant d'écrire la première ligne de code).
PROCHAINE TÂCHE SUGGÉRÉE : reprendre la liste normale (T14 dès les clés Stripe, sinon T19/T21/T23).

---

## [2026-10-06 13:15] — Claude Sonnet 5 — (hors plan) — Retouches de design (en-tête, bannière, pied de page, icônes)
STATUT : terminée
CONTEXTE POUR LA PROCHAINE IA : suite directe de l'entrée précédente ("Logo client + refonte de la bannière"). La cliente a demandé une relecture visuelle précise (logo à gauche, menu centré, logo "déformé" dans la bannière, icônes de catégories "pas terribles", pied de page "pas joli"). `claude-in-chrome` a été tenté pour regarder le site en direct : échoue toujours sur ce poste (réseau isolé du bac à sable, cf. docs/DECISIONS.md). Utiliser Chrome headless en CLI (`google-chrome --headless=new ...`) pour toute vérification visuelle sur ce projet tant que ce n'est pas résolu.
RÉSUMÉ : En-tête réorganisé (logo gauche / nav centrée / icônes compte+panier droite, boutons ronds avec hover). Cause du logo "déformé" trouvée et corrigée : le fichier fourni ne fait que 100×100px, agrandi à tort dans la bannière → remplacé par une grille 2×2 de pictogrammes de catégories (plus de logo agrandi dans la bannière). Nouvelle méthode `Category::fallbackIcon()` (emoji par catégorie selon son nom) utilisée partout où l'icône générique unique posait problème (méga-menu, grille catégories accueil). Pied de page redessiné (bordure sage, intitulés en majuscules, icônes contact, bandeau copyright plein `sage-dark`).
FICHIERS : `app/Models/Category.php` (`fallbackIcon()`), `resources/views/components/site/{header,footer}.blade.php`, `resources/views/catalog/home.blade.php`, `tests/Unit/Models/CategoryTest.php`, `tests/Feature/Site/PublicLayoutTest.php` (texte du bouton cookies raccourci).
TESTS : 1 nouveau test unitaire (`fallbackIcon()` sur les 4 catégories + repli), suite complète réexécutée pour non-régression : **170 passés / 0 échec**. pint/phpstan niveau 5/`composer audit` : tout vert. Vérifié visuellement (desktop, mobile, admin, pied de page).
À RENSEIGNER : rien de nouveau — la limitation de résolution du logo (100×100px) reste un point à signaler à la cliente si elle veut un rendu net en grand format un jour (bannière, réseaux sociaux, etc.).
POINTS À RELIRE PAR UN HUMAIN : aucun nouveau glissement de process cette fois (branche créée avant le premier commit). `claude-in-chrome` reste non fonctionnel sur cet environnement pour atteindre le serveur de dev local — à garder en tête pour toute future tâche visuelle plutôt que de retenter à chaque fois sans prévenir l'humain.
PROCHAINE TÂCHE SUGGÉRÉE : reprendre la liste normale (T14 dès les clés Stripe, sinon T19/T21/T23).

---

## [2026-10-06 13:30] — Claude Sonnet 5 — (hors plan) — Bannière d'accueil réduite en hauteur
STATUT : terminée
RÉSUMÉ : la bannière "prenait tout l'écran" (retour cliente) — padding vertical et tailles réduits (voir docs/DECISIONS.md), aucun changement de contenu.
FICHIERS : `resources/views/catalog/home.blade.php`.
TESTS : aucun nouveau (changement de dimensions CSS uniquement) ; suite complète réexécutée : **170 passés / 0 échec**. pint/phpstan niveau 5/`composer audit` : tout vert. Vérifié visuellement (desktop, mobile).
PROCHAINE TÂCHE SUGGÉRÉE : reprendre la liste normale (T14 dès les clés Stripe, sinon T19/T21/T23).


---

## [2026-10-06 14:00] — Claude Sonnet 5 — (hors plan) — 3 canaux de vente dans la bannière + icônes compte/panier
STATUT : terminée
CONTEXTE POUR LA PROCHAINE IA : suite directe des deux entrées précédentes. La cliente a demandé d'ajouter les 3 canaux de vente ("commande en ligne, marché, professionnels") dans la bannière, en référence à painsansgluten.fr, et a déposé 3 captures d'écran dans un dossier `foto-dev/` à la racine (déplacé dans `docs/reference/foto-dev/`, voir docs/DECISIONS.md) — ces captures proviennent en fait du site d'une autre boulangerie ("Le Petit Minotier"), traitées comme inspiration de style fournie par la cliente, pas comme contenu à copier.
RÉSUMÉ : Bannière d'accueil : 3 badges "Commande en ligne" (lien boutique) / "Sur les marchés" (texte, pas de lien) / "Professionnels" (mailto si `contact_email` renseigné), contenu tiré de `docs/reference/audit-painsansgluten.html` (pas inventé). Icônes compte/panier de l'en-tête restylées (cercle plein + libellé, inspiré des captures fournies). Bug préexistant corrigé : le lien "Contact" ne menait nulle part depuis le début (route `contact` inexistante) — pointe maintenant vers la vraie page de contact (encore un brouillon non publié côté cliente).
FICHIERS : `app/Http/Controllers/Catalog/HomeController.php` (injection `ShopSettings`), `resources/views/catalog/home.blade.php`, `resources/views/components/site/header.blade.php`, `tests/Feature/Site/PublicLayoutTest.php`, `docs/reference/foto-dev/` (nouveau, captures de référence de la cliente).
TESTS : 3 nouveaux (lien Contact correct, 3 badges affichés, badge "Professionnels" cliquable seulement si email configuré). Suite complète : **173 passés / 0 échec**. pint/phpstan niveau 5/`composer audit` : tout vert. Vérifié visuellement (desktop, mobile).
À RENSEIGNER : aucun nouveau — toujours en attente que la cliente rédige et publie sa page Contact (texte réel, adresse, marchés).
POINTS À RELIRE PAR UN HUMAIN : aucun glissement de process. Pas d'icône "Rechercher" ajoutée (pas de fonctionnalité de recherche produit dans ce projet — déjà au backlog V1.1/V2).
PROCHAINE TÂCHE SUGGÉRÉE : reprendre la liste normale (T14 dès les clés Stripe, sinon T19/T21/T23).


---

## [2026-10-06 14:30] — Claude Sonnet 5 — (hors plan) — En-tête refondu en 3 bandeaux
STATUT : terminée
CONTEXTE POUR LA PROCHAINE IA : la cliente a fourni une nouvelle capture (`docs/reference/capture/menu-haut.png`, toujours du site "Le Petit Minotier") et a demandé de **remplacer tout l'en-tête** dans cet esprit — un logo plus grand et centré, une barre utilitaire fine au-dessus, une barre de catégories permanente en dessous. **Ceci remplace la disposition "logo à gauche / menu centré" mise en place plus tôt dans la journée** (voir l'entrée "Retouches de design..." du même jour) : changement de direction explicite de la cliente après avoir vu un exemple concret, pas une erreur de ma part. Lire docs/DECISIONS.md pour le détail, en particulier **pourquoi les badges de certification (AB, AFDIAG, Trophées) visibles sur la référence n'ont volontairement PAS été reproduits** (allégation de certification non vérifiée pour notre cliente).
RÉSUMÉ : En-tête à 3 bandeaux — (1) barre utilitaire fine (F.A.Q., Contact), masquée sur mobile ; (2) barre principale avec logo agrandi (reste dans sa résolution native, donc net) + nom de la boutique + "100% sans gluten" en dessous, icônes compte/panier inchangées ; (3) barre de catégories permanente (remplace le menu déroulant au survol `megaOpen`, supprimé). Menu coulissant mobile mis à jour en conséquence (ajout F.A.Q., pictogrammes sur les catégories).
FICHIERS : `resources/views/components/site/header.blade.php` (réécriture complète), `docs/reference/capture/` (nouveau, captures de référence).
TESTS : aucun nouveau test (structure HTML changée, mais le contenu testé — catégories actives visibles/inactives masquées, lien Contact, badges de la bannière — reste inchangé) ; suite complète réexécutée pour non-régression : **173 passés / 0 échec**. pint/phpstan niveau 5/`composer audit` : tout vert. Vérifié visuellement (desktop, mobile).
À RENSEIGNER : si la cliente détient réellement des certifications/labels (bio, AFDIAG ou autre) qu'elle souhaite afficher, il faudra qu'elle le confirme explicitement et fournisse les vrais visuels — jamais une supposition de notre part.
POINTS À RELIRE PAR UN HUMAIN : troisième changement de direction sur l'en-tête dans la même journée (logo centré → logo gauche → logo centré en grand) — comportement normal d'un cycle d'itération avec retours visuels rapides, mais à signaler si la cliente hésite encore après cette version.
PROCHAINE TÂCHE SUGGÉRÉE : reprendre la liste normale (T14 dès les clés Stripe, sinon T19/T21/T23).

---

## [2026-10-07 23:00] — Claude Sonnet 5 — (hors plan) — Accès SSH VPS agent-ia + initialisation du repo GitHub
STATUT : terminée
CONTEXTE POUR LA PROCHAINE IA : session de debug infra avec Ian (pas une tâche du PLAN.md), démarrée sur une coupure de connexion rencontrée en testant l'accès de la clé `agent-ia-painsansgluten` au VPS preprod (voir `docs/reference/vps-setup-commands.sh` et l'entrée DECISIONS.md du 2026-10-07 "Infra VPS preprod" pour le contexte du setup initial). Diagnostic fait en pair-debugging : Ian collait les logs/commandes depuis le VPS et son poste, je n'ai pas d'accès direct au serveur.
RÉSUMÉ : Deux problèmes cumulés empêchaient `painsansgluten` (et donc l'agent IA dédié) de se connecter en SSH au VPS : (1) `authorized_keys` était placé sous `/home/painsansgluten/.ssh/` alors que le vrai `$HOME` de cet utilisateur (créé via `adduser --home /var/www/painsansgluten`) est `/var/www/painsansgluten` ; (2) même corrigé, `StrictModes` de sshd aurait quand même refusé la clé car `/var/www/painsansgluten` est volontairement en `2775` (bit d'écriture groupe, pour que `deploy` puisse y déployer). Fix retenu : sortir `AuthorizedKeysFile` du home dir via un bloc `Match User painsansgluten` dans `/etc/ssh/sshd_config`, pointant vers `/etc/ssh/authorized_keys/%u` (racine root, hors d'atteinte de `StrictModes`), sans toucher au `2775` nécessaire au déploiement. Confirmé fonctionnel via `ssh -vvv -o IdentitiesOnly=yes`.
Ensuite, initialisation du dépôt GitHub distant : remote `origin` ajouté (`git@github.com:ianfares/painsansgluten.fr.git`), repo vide poussé avec les 24 branches locales (`main`, `develop`, tous les `feature/*`/`fix/*`), `develop` configurée pour suivre `origin/develop`. Deux clés SSH ajoutées côté GitHub par Ian lui-même : sa clé perso (`id_ed25519`, compte `ianfares`) et `agent-ia-painsansgluten`. **Décision notée** : les deux ont été ajoutées comme clés SSH du compte `ianfares` (accès à tous les repos de ce compte), pas comme *deploy key* scopée en lecture seule à ce seul repo comme initialement envisagé — accepté tel quel par Ian car un seul projet existe sous ce compte pour l'instant (voir docs/DECISIONS.md).
FICHIERS : aucun fichier applicatif touché. Côté VPS (hors dépôt Git) : `/etc/ssh/sshd_config` (bloc `Match User painsansgluten` ajouté), `/etc/ssh/authorized_keys/painsansgluten` (nouveau, root:root 644) ; `/home/painsansgluten/.ssh/` laissé en l'état (obsolète, non nettoyé). Côté dépôt local : remote `origin` ajouté (`.git/config`), aucun fichier versionné modifié par cette session.
TESTS : pas de suite Pest concernée (infra pure). Vérifications manuelles : `ssh -vvv -p 54000 -o IdentitiesOnly=yes -i ~/.ssh/agent_ia_painsansgluten painsansgluten@<IP_VPS>` → connexion acceptée ; `ssh -T git@github.com -i ~/.ssh/agent_ia_painsansgluten -o IdentitiesOnly=yes` → authentifié (`Hi ianfares!`) ; `git push --all origin` → 24 branches poussées avec succès.
À RENSEIGNER : nettoyer `/home/painsansgluten/.ssh/` sur le VPS (dossier mort, risque de confusion pour la prochaine personne qui debug un accès SSH). Si un deuxième repo GitHub arrive sous le compte `ianfares`, revoir la clé `agent-ia-painsansgluten` pour la scoper en *deploy key* dédiée (lecture seule) plutôt que clé de compte à portée large.
POINTS À RELIRE PAR UN HUMAIN : la modification de `/etc/ssh/sshd_config` (bloc `Match User painsansgluten` + `AuthorizedKeysFile` custom) — vérifier qu'elle n'interfère pas avec un futur deuxième site/utilisateur sur le même VPS (le bloc est scopé au seul user `painsansgluten`, a priori sans impact sur les autres, mais à confirmer si un nouvel utilisateur système est créé). Portée large de la clé `agent-ia-painsansgluten` sur le compte GitHub (cf. "À RENSEIGNER").
PROCHAINE TÂCHE SUGGÉRÉE : reprendre la liste normale (T14 dès les clés Stripe, sinon T19/T21/T23) ; penser à nettoyer `/home/painsansgluten/.ssh/` et à reconsidérer le scope de la clé GitHub si un 2e repo arrive.

---

## [2026-10-08 10:30] — Claude Opus 5.5 — (hors plan) — Correctifs : faille npm, fuseau horaire par défaut, exclusion du résolveur de slug
STATUT : terminée
CONTEXTE POUR LA PROCHAINE IA : correctifs issus d'une analyse globale du projet demandée par Ian (2026-10-08). Les proxies de confiance Cloudflare (aussi relevés) sont **volontairement non traités ici** à la demande d'Ian : ils restent dans T23.
RÉSUMÉ : (1) `npm audit` remontait 2 failles critiques (`shell-quote` via `concurrently`, dev uniquement) → `overrides` npm vers `shell-quote ^1.11.0` plutôt que `npm audit fix --force` qui rétrogradait `concurrently`. (2) `config/app.php` : fuseau par défaut `Europe/Paris` au lieu de `UTC`. (3) Résolveur générique `/{slug}` : exclusion des routes réservées ancrée sur le segment entier (un slug `faq-livraison` ou `commandes-speciales` ne renvoie plus 404).
FICHIERS : `package.json`, `package-lock.json`, `config/app.php`, `routes/web.php`, `tests/Feature/Content/ContentPagesTest.php`, `docs/DECISIONS.md`, `docs/JOURNAL.md`.
ANALYSE D'IMPACT : `overrides` limité à une dépendance de dev (aucun impact sur le bundle front). Fuseau : aucun changement effectif là où `.env` contient déjà `APP_TIMEZONE=Europe/Paris`. Regex : les routes réservées restent exclues (`/faq`, `/panier`, `/commande/...` testés), seuls les slugs qui *commencent* par un mot réservé sont désormais résolus.
SÉCURITÉ : faille critique npm supprimée. Pas d'autre surface modifiée.
PERFORMANCE : sans objet.
TESTS : 1 nouveau test (slugs préfixés par un mot réservé + non-régression `/faq`, `/panier`). Suite complète : **174 passés / 0 échec**.
QUALITÉ : pint OK, phpstan niveau 5 : 0 erreur, `composer audit` : aucune faille, `npm audit` : 0 vulnérabilité, `npm run build` OK.
DOCUMENTATION : `docs/DECISIONS.md` (entrée 2026-10-08).
À RENSEIGNER / QUESTIONS OUVERTES : aucune.
POINTS À RELIRE PAR UN HUMAIN : l'`overrides` npm est à retirer quand `concurrently` publiera une version qui dépend d'un `shell-quote` corrigé.
AJOUTÉ AU BACKLOG : rien.
PROCHAINE TÂCHE SUGGÉRÉE : mise en place de la préprod sur le VPS (demandée par Ian), puis T23 (dont proxies Cloudflare), T19, T21/T22 ; T14 dès les clés Stripe.

---

## [2026-10-08 12:00] — Claude Opus 5.5 — (hors plan) — Revue de simplicité du projet + suppression de `concurrently`
STATUT : terminée
CONTEXTE POUR LA PROCHAINE IA : Ian demande que le projet reste **le plus simple possible** (boutique en lancement). Avant de proposer une solution (code, infra, déploiement), choisir le minimum qui marche. Revue complète faite le 2026-10-08 : pas de sur-architecture trouvée dans le code (voir RÉSUMÉ). Déploiement préprod retenu : envoi des fichiers (dont `vendor/` et `public/build/`) par rsync via l'alias SSH `painsansgluten`, dans `/var/www/painsansgluten` (son `public/` est déjà le dossier servi par Apache), import de la BDD, `.env` serveur. Pas de clé GitHub sur le serveur, pas de changement de config Apache. En attente : mot de passe MariaDB préprod (Ian).
RÉSUMÉ : 136 fichiers / ~6 600 lignes dans `app/`, toutes les dépendances Composer sont celles de la stack imposée (+ Fortify pour l'auth, `mews/purifier` pour nettoyer le HTML des pages). Aucune interface/abstraction inutile. Seul superflu trouvé et supprimé : `concurrently` (npm, dev), qui remplace aussi le correctif `overrides` de l'entrée précédente. Les événements `OrderShipped`/`OrderCancelled` n'ont pas encore d'écouteur : ils sont conservés car ils servent de point d'accroche aux emails de T19.
FICHIERS : `package.json`, `package-lock.json`, `docs/DECISIONS.md`, `docs/JOURNAL.md`.
ANALYSE D'IMPACT : dépendance de dev uniquement, aucun impact sur le site.
SÉCURITÉ : la faille `shell-quote` disparaît avec la dépendance.
PERFORMANCE : sans objet.
TESTS : suite complète 174 passés / 0 échec ; `php artisan dev` démarre.
QUALITÉ : `npm audit` 0 vulnérabilité, `npm run build` OK, `composer audit` inchangé (aucune faille).
DOCUMENTATION : `docs/DECISIONS.md`.
À RENSEIGNER / QUESTIONS OUVERTES : mot de passe MariaDB de la préprod.
POINTS À RELIRE PAR UN HUMAIN : aucun.
AJOUTÉ AU BACKLOG : rien.
PROCHAINE TÂCHE SUGGÉRÉE : mise en ligne préprod (version simple ci-dessus) dès réception du mot de passe MariaDB.

---

## [2026-10-08 21:30] — Claude Opus 5.5 — (hors plan) — Mise en ligne de la préprod
STATUT : terminée
CONTEXTE POUR LA PROCHAINE IA : la préprod tourne sur http://preprod.painsansgluten.fr (protégée par mot de passe, détenu par Ian). Méthode d'envoi et de mise à jour : `docs/DECISIONS.md`, entrée 2026-10-08 « Mise en ligne préprod ». Ne jamais réimporter la base locale par-dessus celle de la préprod.
RÉSUMÉ : fichiers envoyés par rsync dans `/var/www/painsansgluten` (vendor et assets compilés inclus), `.env` serveur créé (staging, debug désactivé, mails en log, clés Stripe/Chronopost/Brevo factices), clé d'application générée, base locale importée (22 produits de démo, 1 admin, sans sessions ni cache), migrations à jour, `storage:link`, caches Laravel/Filament. `public/.htaccess` serveur = protection par mot de passe + `X-Robots-Tag: noindex` + règles Laravel.
FICHIERS : dépôt : `docs/DECISIONS.md`, `docs/JOURNAL.md`. Serveur : tout le site, `.env`, `public/.htaccess`.
ANALYSE D'IMPACT : aucun code modifié.
SÉCURITÉ : APP_DEBUG=false, `.env` en 640, préprod protégée par mot de passe et non indexable. **Pas de HTTPS** : le mot de passe de la préprod circule en clair (certbot à installer en root).
PERFORMANCE : caches de config/routes/vues activés.
TESTS : test des pages directement sur le serveur : `/`, `/boutique`, `/pains-sans-gluten`, `/panier`, `/faq`, `/commande`, `/connexion`, `/admin/login` → 200 ; page inexistante → 404 ; `/mentions-legales` → 404 car les 6 pages légales ne sont pas publiées (textes à rédiger, comportement normal). Accès HTTP public → 401 (protection active).
QUALITÉ : sans objet (pas de code).
DOCUMENTATION : `docs/DECISIONS.md`.
À RENSEIGNER / QUESTIONS OUVERTES : installer `cron` et certbot (root, Ian) ; sans cron, les tâches planifiées et la file d'emails ne tournent pas sur la préprod.
POINTS À RELIRE PAR UN HUMAIN : vérifier visuellement le site et la connexion au back-office avec le compte admin existant (même identifiants qu'en local).
AJOUTÉ AU BACKLOG : rien.
PROCHAINE TÂCHE SUGGÉRÉE : T23 (dont proxies Cloudflare), T19, T21/T22 ; T14 dès les clés Stripe.

---

## [2026-10-08 22:30] — Claude Opus 5.5 — (correctif) — Fiche produit BO : enregistrement bloqué par les valeurs nutritionnelles à 2 décimales
STATUT : terminée
CONTEXTE POUR LA PROCHAINE IA : bug signalé par Ian sur la préprod (bouton « Sauvegarder » sans effet sur une fiche produit). Reproduit en local dans un navigateur.
RÉSUMÉ : les champs de l'onglet Nutrition avaient `step(0.1)` : une valeur à 2 décimales (sel 0,34 g) est refusée par la validation native du navigateur, qui bloque l'envoi sans rien afficher car le champ est dans un onglet masqué. Passé à `step('any')`.
FICHIERS : `app/Filament/Resources/ProductResource.php`, `tests/Feature/Catalog/ProductResourceTest.php`, `docs/JOURNAL.md`.
ANALYSE D'IMPACT : formulaire produit du BO uniquement ; le stockage (JSON `nutrition`) acceptait déjà toute valeur.
SÉCURITÉ : sans objet.
PERFORMANCE : sans objet.
TESTS : 1 nouveau test (attribut HTML + enregistrement de 0.34). Suite complète : 175 passés / 0 échec.
QUALITÉ : pint OK, phpstan niveau 5 : 0 erreur. Pas de nouvelle dépendance.
DOCUMENTATION : ce journal.
À RENSEIGNER / QUESTIONS OUVERTES : aucune.
POINTS À RELIRE PAR UN HUMAIN : piège général à retenir : dans un formulaire Filament à onglets, une erreur de validation *native du navigateur* (step, min, max, pattern) dans un onglet masqué bloque l'envoi en silence.
AJOUTÉ AU BACKLOG : rien.
PROCHAINE TÂCHE SUGGÉRÉE : T23 (dont proxies Cloudflare), T19, T21/T22 ; T14 dès les clés Stripe.

---

## [2026-10-08 23:00] — Claude Opus 5.5 — (correctif) — Panier : tiroir vide après ajout, compteur de l'en-tête figé
STATUT : terminée
CONTEXTE POUR LA PROCHAINE IA : test manuel du parcours « ajouter au panier » demandé par Ian, fait en local dans un navigateur piloté (visiteur neuf).
RÉSUMÉ : (1) Alpine.js chargé deux fois (import dans `app.js` + celui de Livewire) : le tiroir s'ouvrait avec « Votre panier est vide » ou ne s'ouvrait plus, et deux paniers pouvaient être créés pour le même visiteur. Import supprimé, dépendance `alpinejs` retirée. (2) Le compteur d'articles de l'en-tête n'était calculé qu'au chargement de la page : `CartWidget` envoie désormais l'événement `cart-count`, le badge (Alpine) se met à jour et se masque à 0. Vérifié dans le navigateur : ajout, +/−, retrait, compteur 2→3→masqué.
FICHIERS : `resources/js/app.js`, `package.json`, `package-lock.json`, `app/Livewire/CartWidget.php`, `resources/views/components/site/header.blade.php`, `tests/Feature/Cart/CartLivewireTest.php`, `docs/DECISIONS.md`, `docs/JOURNAL.md`.
ANALYSE D'IMPACT : tout le front utilisant Alpine (tiroirs, menu mobile) : il tourne désormais sur l'instance de Livewire, aucune différence fonctionnelle attendue.
SÉCURITÉ : sans objet (le compteur n'est qu'un affichage, le serveur reste seul juge du panier).
PERFORMANCE : un JS de moins à télécharger.
TESTS : 2 nouveaux tests. Suite complète : 177 passés / 0 échec.
QUALITÉ : pint OK, phpstan niveau 5 : 0 erreur, `npm audit` : 0 vulnérabilité, build OK.
DOCUMENTATION : `docs/DECISIONS.md`.
À RENSEIGNER / QUESTIONS OUVERTES : la grille de frais de port n'est pas renseignée (message affiché dans le panier, normal tant que les tarifs ne sont pas saisis en BO).
POINTS À RELIRE PAR UN HUMAIN : vérifier le menu mobile et les tiroirs sur la préprod (Alpine désormais fourni par Livewire seul).
AJOUTÉ AU BACKLOG : rien.
PROCHAINE TÂCHE SUGGÉRÉE : poursuivre le test du parcours (page panier → tunnel de commande), puis T23, T19, T21/T22 ; T14 dès les clés Stripe.

---

## [2026-10-08 23:59] — Claude Opus 5.5 — T14 — Paiement Stripe Checkout et webhooks
STATUT : terminée côté code. **Reste à faire par Ian** : créer l'endpoint webhook dans le tableau de bord Stripe (mode test) et me transmettre le `whsec_…` (voir « À RENSEIGNER »). Tant que ce n'est pas fait, le webhook refuse tout (503) et aucune commande carte ne passe en « payée ».
CONTEXTE POUR LA PROCHAINE IA : clés Stripe de **test** fournies par Ian et posées dans `.env` (local + préprod). Lire docs/DECISIONS.md « T14 » avant de toucher au paiement. La préprod exempte `/webhooks/stripe` de la protection par mot de passe (`public/.htaccess` du serveur).
RÉSUMÉ : « Payer par carte » redirige vers Stripe Checkout hébergé (montants issus de la commande, garde-fou si le total diffère), relance possible d'un paiement abandonné/refusé. Webhook signé et idempotent (`stripe_events`) : paiement → `paid` + date d'expédition figée + facture (listener existant, en queue) ; anomalie de montant → statut inchangé + drapeau + log + email admin ; paiement asynchrone refusé → `payment_failed` ; session expirée → `cancelled` (sauf paiement relancé) ; remboursement total depuis Stripe → `refunded` + avoir. Page de confirmation : statut réel en BDD, rafraîchi toutes les 2 s pendant 30 s, puis bouton « Reprendre le paiement ». Remboursement BO : vrai appel Stripe avec clé d'idempotence. Planificateur : la file d'attente est traitée chaque minute par cron.
FICHIERS : créés : `app/Services/Payments/StripeCheckoutService.php`, `app/Actions/Payments/HandleStripeWebhookAction.php`, `app/Http/Controllers/Checkout/StripeWebhookController.php`, `app/Livewire/StripePaymentStatus.php`, `app/Mail/StripePaymentAnomalyMail.php`, `resources/views/livewire/stripe-payment-status.blade.php`, `resources/views/emails/stripe-payment-anomaly.blade.php`, `tests/Feature/Payments/StripeWebhookTest.php`, `tests/Feature/Payments/StripeCheckoutTest.php`, `tests/Support/FakeStripeHttpClient.php`. Modifiés : `app/Actions/Orders/RefundOrderAction.php`, `app/Exceptions/Orders/RefundNotAvailable.php`, `app/Filament/Resources/OrderResource.php`, `app/Http/Controllers/Checkout/CheckoutController.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `config/services.php`, `resources/views/checkout/confirmation.blade.php`, `routes/web.php`, `routes/console.php`, `composer.json`, `composer.lock`, `docs/DECISIONS.md`. Supprimé : `resources/views/checkout/stripe-pending.blade.php` (provisoire T13).
ANALYSE D'IMPACT : tunnel de commande (bouton carte), page de confirmation (virement inchangé), action « Rembourser » du BO (virement inchangé), planificateur. `OrderStateMachine`, `CreateOrderAction` et les listeners facture/avoir réutilisés sans modification.
SÉCURITÉ : signature Stripe vérifiée, webhook refusé tant que le secret est vide ou factice (faille trouvée en auto-revue et corrigée avant livraison : la valeur de `.env.example` est publique). Idempotence par `event.id` UNIQUE + verrou `lockForUpdate` sur la commande. Le retour navigateur ne change jamais le statut. Montant et devise contrôlés. Logs sans donnée personnelle (numéro de commande uniquement ; le message d'erreur Stripe n'est pas journalisé). Pages liées au `token` de commande, jamais à l'id.
PERFORMANCE : le webhook ne fait que des écritures BDD courtes ; PDF de facture et emails en queue.
TESTS : 22 nouveaux tests (signature invalide, secret factice, paiement OK, doublon, déjà payée, anomalie + email admin, asynchrone non payé/refusé, expiration, expiration d'une ancienne session, remboursement Stripe total/partiel, réglages d'expédition incomplets, redirection et montants envoyés, commande déjà payée, relance, Stripe indisponible, confirmation, fin du rafraîchissement, remboursement BO avec clé d'idempotence, remboursement sans paiement connu). Vérification réelle : création d'une session sur le vrai Stripe (mode test) OK. Suite complète : 199 passés / 0 échec.
QUALITÉ : pint OK, phpstan niveau 5 : 0 erreur, `composer audit` : aucune faille, `npm audit` : 0 vulnérabilité.
DOCUMENTATION : `docs/DECISIONS.md` (T14).
À RENSEIGNER / QUESTIONS OUVERTES : (1) **`STRIPE_WEBHOOK_SECRET`** : Ian crée dans Stripe (mode test) → Développeurs → Webhooks un endpoint `https://preprod.painsansgluten.fr/webhooks/stripe` avec les événements `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired`, `charge.refunded`, puis transmet le `whsec_…`. (2) `admin_notification_email` à renseigner en BO pour recevoir les alertes d'anomalie. (3) Emails client au paiement : T19.
POINTS À RELIRE PAR UN HUMAIN : parcours complet à faire sur la préprod une fois le secret posé (carte test 4242 4242 4242 4242) : commande payée, facture générée une seule fois, remboursement depuis le BO.
AJOUTÉ AU BACKLOG : rien.
PROCHAINE TÂCHE SUGGÉRÉE : recette T14 sur la préprod dès le `whsec_…`, puis T19 (emails Brevo : accès disponibles chez Ian, configuration DNS en cours).

---

## [2026-10-09 00:30] — Claude Opus 5.5 — (hors plan) — Brevo branché en SMTP (local + préprod)
STATUT : terminée
CONTEXTE POUR LA PROCHAINE IA : Ian a configuré les DNS Brevo (vérifiés depuis 1.1.1.1 et 8.8.8.8 : `brevo-code`, DKIM `brevo1`/`brevo2`, DMARC `p=none` ; SPF limité à OVH, non bloquant). **Reprise prévue le 2026-10-09** : Ian fournit le `whsec_…` du webhook Stripe (mode test) → le poser dans `STRIPE_WEBHOOK_SECRET` du `.env` préprod (puis `php artisan config:cache`), et faire avec lui la commande de test complète (carte 4242 4242 4242 4242). Ensuite : T19 (emails clients), non commencée.
RÉSUMÉ : envoi des emails par le relais SMTP Brevo (mailer `smtp` natif de Laravel, aucune dépendance ajoutée) : `MAIL_MAILER=smtp`, `MAIL_HOST=smtp-relay.brevo.com`, `MAIL_PORT=587`, identifiant et clé SMTP fournis par Ian, expéditeur `contact@painsansgluten.fr` (proposé, non contredit par Ian). Authentification Brevo vérifiée en local et en préprod ; email de test envoyé depuis la préprod et **reçu par Ian**. Les tests restent sur le mailer `array` (phpunit.xml) : aucun envoi réel.
FICHIERS : dépôt : `docs/JOURNAL.md`. Hors dépôt : `.env` local et `.env` préprod.
ANALYSE D'IMPACT : tous les emails existants (virement, alerte d'anomalie Stripe) partent désormais réellement, via la file traitée par cron chaque minute. ⚠️ En local aussi : une commande de test avec une adresse inventée part chez Brevo.
SÉCURITÉ : clés hors Git. La clé SMTP et la clé secrète Stripe de test ont transité dans la conversation : à régénérer pour la production (Ian les collera lui-même dans le `.env` de prod).
PERFORMANCE : sans objet (envois en queue).
TESTS : pas de code modifié ; vérification réelle (authentification SMTP + email reçu).
QUALITÉ : sans objet.
DOCUMENTATION : ce journal. `docs/DECISIONS.md` : choix SMTP plutôt qu'API Brevo (pas de dépendance) à consigner avec T19.
À RENSEIGNER / QUESTIONS OUVERTES : `whsec_…` Stripe (demain) ; confirmation de l'adresse d'expédition ; `admin_notification_email` et grille de frais de port à saisir en BO avant la commande de test.
POINTS À RELIRE PAR UN HUMAIN : aucun.
AJOUTÉ AU BACKLOG : rien.
PROCHAINE TÂCHE SUGGÉRÉE : fin de T14 (secret webhook + commande test sur la préprod), puis T19.

---

## [2026-10-09 01:30] — Claude Opus 5.5 — T19 — Emails transactionnels (Brevo)
STATUT : terminée. Exemplaires des 13 emails envoyés à Ian (webmaster@yafar.org) pour relecture par la cliente : **les textes vont probablement changer** (Ian les fait relire).
CONTEXTE POUR LA PROCHAINE IA : pour modifier un texte, éditer le fichier correspondant dans `resources/views/emails/` (liste ci-dessous), puis renvoyer les exemplaires avec `php artisan emails:samples <adresse>`. Lire docs/DECISIONS.md « T19 » (où part chaque email et pourquoi).
RÉSUMÉ : liste complète PLAN §15. Client : confirmation carte + facture (`order-confirmed`), virement : instructions / relance / paiement reçu + facture / annulation (`bank-transfer-*`), expédition + suivi + rappel retrait le jour même (`order-shipped`), remboursement + avoir (`order-refunded`), bienvenue + vérification email (`auth/verify-email`), mot de passe oublié (`auth/reset-password`). Admin (`admin/*` + `stripe-payment-anomaly`) : commande payée, virement en attente, anomalie de paiement, demande de suppression de compte. Charte de la boutique (logo, couleurs). BO : bouton « Renvoyer l'email de confirmation ». Commande `emails:samples`. Bug corrigé : écouteurs exécutés deux fois.
FICHIERS : créés : `app/Mail/{OrderConfirmedMail,OrderShippedMail,OrderRefundedMail}.php`, `app/Mail/Admin/{NewPaidOrderMail,NewBankTransferOrderMail,AccountDeletionRequestedMail}.php`, `app/Services/Mail/{AdminMailer,InvoiceLink}.php`, `app/Listeners/Orders/SendOrderStatusEmails.php`, `app/Actions/Orders/ResendOrderConfirmationAction.php`, `app/Console/Commands/SendEmailSamplesCommand.php`, `resources/views/emails/{order-confirmed,order-shipped,order-refunded}.blade.php`, `resources/views/emails/partials/order-summary.blade.php`, `resources/views/emails/admin/*.blade.php`, `resources/views/emails/auth/*.blade.php`, `resources/views/vendor/mail/…` (header, message, thème), `tests/Feature/Emails/OrderEmailsTest.php`. Modifiés : `app/Mail/BankTransferPaidMail.php`, `resources/views/emails/bank-transfer-paid.blade.php`, `app/Listeners/Orders/{GenerateInvoiceOnOrderPaid,GenerateCreditNoteOnOrderRefunded}.php`, `app/Actions/Orders/ValidateBankTransferPaymentAction.php`, `app/Actions/Payments/HandleStripeWebhookAction.php`, `app/Livewire/CheckoutWizard.php`, `app/Http/Controllers/Compte/AccountController.php`, `app/Filament/Resources/OrderResource.php`, `app/Providers/AppServiceProvider.php`, `tests/Feature/Orders/BankTransferTest.php`, `docs/DECISIONS.md`.
ANALYSE D'IMPACT : tous les changements de statut de commande déclenchent désormais des emails réels (Brevo) ; la facturation n'est plus déclenchée deux fois. Le paiement par virement validé n'envoie plus l'email depuis l'action mais depuis la tâche de facturation (même email, avec lien vers la facture en plus).
SÉCURITÉ : liens de facture/avoir signés et expirant à 30 jours ; aucune donnée personnelle ajoutée aux logs ; emails admin seulement vers l'adresse paramétrée.
PERFORMANCE : tous les emails en queue (cron chaque minute) ; le bouton BO ne fait aucune requête par ligne de la liste.
TESTS : 13 nouveaux tests (chaque déclencheur → bon email une seule fois, lien de facture, lien de suivi, pas d'email si transaction annulée, avoir, sans email admin, suppression de compte, textes français des emails de compte, renvoi de confirmation, commande d'exemples sans trace en base, unicité des écouteurs). Suite complète : 211 passés / 0 échec.
QUALITÉ : pint OK, phpstan niveau 5 : 0 erreur, `composer audit` : aucune faille, `npm audit` : 0 vulnérabilité. Pas de nouvelle dépendance.
DOCUMENTATION : `docs/DECISIONS.md` (T19).
À RENSEIGNER / QUESTIONS OUVERTES : (1) relecture des textes par la cliente (via Ian) ; (2) `admin_notification_email`, `reply_to_email` à saisir en BO ; (3) modèle d'URL de suivi Chronopost (BO > Expédition) pour le bouton « Suivre mon colis ». (4) T14 : `whsec_…` Stripe attendu le 2026-10-09.
POINTS À RELIRE PAR UN HUMAIN : rendu des emails dans les principales messageries (Gmail, Outlook, mobile) à partir des exemplaires reçus.
AJOUTÉ AU BACKLOG : rien.
PROCHAINE TÂCHE SUGGÉRÉE : fin de T14 (secret webhook + commande test), corrections de textes d'emails au retour de la cliente, puis T21 (SEO) / T22 (analytics) / T23 (sécurité, dont proxies Cloudflare).

---

## [2026-10-09 02:00] — Claude Opus 5.5 — (hors plan) — Reprise des 8 fiches produits Shopify + document client
STATUT : terminée, **avec un incident** (voir SÉCURITÉ / POINTS À RELIRE).
CONTEXTE POUR LA PROCHAINE IA : à la demande d'Ian, les 8 produits publiés sur l'ancien site Shopify (painsansgluten.fr, données publiques `/products/<handle>.json` + page produit) ont été importés **sur la préprod uniquement**, en brouillon (non publiés, non disponibles, prix 0, TVA vide). Script d'import ponctuel, non versionné (exécuté puis supprimé du serveur). La cliente complète prix, TVA, poids, puis publie. ⚠️ La préprod contient désormais de vraies saisies : **toujours sauvegarder la base avant d'y écrire** (`storage/app/backups/` sur le serveur).
RÉSUMÉ : récupérés : nom, description, photos (16, converties par la médiathèque), ingrédients, allergènes, valeurs nutritionnelles, conditionnement, conseils, conservation, référence, titre et description SEO. Catégories : 4 pains, 4 pâtisseries (collections Shopify). Allergènes relus un par un à la main : le Miel'lleux est « sans lactose et sans œuf » (détection automatique corrigée) ; pour l'Insolent (amande) et le Zest'moelleux (beurre, amande), des allergènes présents dans les ingrédients mais absents de la mention d'origine ont été **ajoutés par prudence** et signalés. Anomalies de la source signalées, jamais corrigées : poids « XXX g », prix 0 €, « 22, g », « 4, g », texte « brownie » sur le cookie, nutrition identique Insolent/Brownheur, sel 9,72 g du Bunheur. Document client PDF : `docs/client/Mon-Sans-Gluten-parcours-commande-et-fiches-produits.pdf` (parcours de commande + 13 emails + à-faire par fiche) ; version en ligne du parcours : https://claude.ai/artifact/V6L5Jos4VppbpNvc3sgoBW.
FICHIERS : dépôt : `docs/client/…pdf`, `docs/JOURNAL.md`. Préprod : tables `products`, `media`, `storage/app/public/<id>/`.
ANALYSE D'IMPACT : catalogue de la préprod uniquement ; aucun code modifié.
SÉCURITÉ : descriptions passées par le nettoyeur HTML du modèle (Purifier). **Incident** : `updateOrCreate` sur le slug a écrasé 2 produits **saisis à la main par Ian** sur la préprod (ids 1 et 2, slugs « Le-pain-nordique-sans-gluten » / « La-cabosse-sans-gluten », identiques à la casse près, MariaDB comparant sans casse) : prix, TVA, poids, statut publié/disponible, textes, et **5 photos supprimées** (médias 1 à 5). Pas de sauvegarde antérieure : non récupérable. Sauvegarde complète faite juste après : `storage/app/backups/preprod-20261008-2234.sql.gz`.
PERFORMANCE : sans objet.
TESTS : pas de code ; vérification des 8 fiches importées (catégorie, allergènes, nutrition, photos).
QUALITÉ : sans objet.
DOCUMENTATION : ce journal.
À RENSEIGNER / QUESTIONS OUVERTES : prix, TVA, poids et publication des 8 fiches (cliente) ; corrections des textes sources ; Ian doit ressaisir ce qu'il avait mis sur le Pain Nordique et la Cabosse (prix, TVA, poids, photos s'il en avait d'autres).
POINTS À RELIRE PAR UN HUMAIN : l'incident ci-dessus. Proposition : sauvegarde automatique quotidienne de la base préprod par cron (non faite, à valider par Ian).
AJOUTÉ AU BACKLOG : rattachement des commandes invité au compte après vérification de l'email (prévu par CLAUDE.md §4, constaté non implémenté le 2026-10-08, à planifier — pas du hors-V1, c'est un manque).
PROCHAINE TÂCHE SUGGÉRÉE : fin de T14 (secret webhook Stripe), corrections des textes d'emails, rattachement des commandes invité.

---

## [2026-10-09 02:30] — Claude Opus 5.5 — (correctif) — Liste des produits du BO triée par dernière modification
STATUT : terminée
CONTEXTE POUR LA PROCHAINE IA : Ian ne voyait pas les 8 fiches importées dans l'admin : la liste n'avait pas de tri par défaut (ordre des ids) et 10 lignes par page, les fiches importées étaient en page 3. Cloudflare a été vérifié : la préprod passe désormais par Cloudflare (DNS proxifié), mais les pages admin ne sont pas mises en cache (`cf-cache-status: DYNAMIC`).
RÉSUMÉ : tri par défaut `updated_at` décroissant + colonne « Modifié le » (triable).
FICHIERS : `app/Filament/Resources/ProductResource.php`, `tests/Feature/Catalog/ProductResourceTest.php`, `docs/JOURNAL.md`.
ANALYSE D'IMPACT : affichage de la liste BO uniquement.
SÉCURITÉ : sans objet. PERFORMANCE : tri sur une table de quelques dizaines de lignes, sans objet.
TESTS : 1 nouveau test (ordre d'affichage). Suite complète au vert.
QUALITÉ : pint OK, phpstan 0 erreur.
DOCUMENTATION : ce journal.
À RENSEIGNER / QUESTIONS OUVERTES : Cloudflare devant la préprod → T23 (proxies de confiance) devient utile ; vérifier que le WAF/anti-bot Cloudflare laisse passer `POST /webhooks/stripe` (CLAUDE.md §4).
POINTS À RELIRE PAR UN HUMAIN : aucun.
AJOUTÉ AU BACKLOG : rien.
PROCHAINE TÂCHE SUGGÉRÉE : fin de T14, puis T23 (proxies Cloudflare).
