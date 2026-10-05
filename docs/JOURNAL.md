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
