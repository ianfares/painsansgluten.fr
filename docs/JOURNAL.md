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
