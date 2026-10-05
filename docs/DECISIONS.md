# DECISIONS.md — Choix techniques et justifications

> Convention (CLAUDE.md §2, §12 ; QUALITE.md §5) : chaque choix technique non trivial est consigné ici (date, décision, raison, alternatives écartées).

---

## 2026-10-05 — T00 — Versions et stack

- **Laravel 13.34.0** (dernière stable au moment de l'installation). PHP requis `^8.3` — confirmé disponible (PHP 8.3.6).
- **Filament 3.3.56** (`filament/filament ^3.3`). Choisi en version majeure 3 (stable et largement déployée) plutôt que la 4 (plus récente mais moins éprouvée en production au moment du démarrage) ; `composer audit` et le check Composer au moment de l'installation : aucune faille connue.
- **Pest 4.7** + `pestphp/pest-plugin-laravel ^4.1`, en remplacement de PHPUnit pur (requis par CLAUDE.md §2). Les tests d'exemple générés par Laravel (PHPUnit) ont été réécrits en syntaxe Pest.
- **Larastan 3.x** (`larastan/larastan ^3.0`), configuré au niveau **5** dans `phpstan.neon` (minimum imposé par CLAUDE.md §2). 0 erreur sur le squelette initial.
- **Pint** : configuration `pint.json` ajoutée (preset `laravel` + règle `declare_strict_types` active) pour imposer automatiquement `declare(strict_types=1);` sur chaque fichier PHP de l'application (QUALITE.md §4.1), plutôt que de compter sur la discipline manuelle de chaque IA.

## 2026-10-05 — T00 — Base de données

- **MariaDB** utilisée aussi bien en développement qu'en test (pas de SQLite, même en test) : le projet repose sur des verrous transactionnels (`lockForUpdate`) et une numérotation séquentielle critique (factures, commandes) dont le comportement diffère entre moteurs SQL. Tester sur un moteur différent de la production aurait pu masquer de vrais bugs de concurrence. Alternative écartée : SQLite en mémoire (défaut Laravel), plus rapide mais jugé risqué ici.
- Deux bases locales créées manuellement (accès root MariaDB non disponible depuis les outils automatisés — nécessite un geste humain avec mot de passe) : `boulangerie_gluten` (dev) et `boulangerie_gluten_test` (tests), toutes deux accessibles par un utilisateur MariaDB dédié `boulangerie_gluten` (pas de compte `root` utilisé par l'application : moindre privilège).
- `phpunit.xml` ne surcharge que `DB_CONNECTION` et `DB_DATABASE` pour les tests ; hôte/identifiants restent lus depuis `.env` (jamais commités) pour ne jamais exposer de secret dans un fichier versionné.

## 2026-10-05 — T00 — Authentification back-office

- Table **`admins`** distincte de `users`, guard **`admin`** distinct de `web` (config/auth.php), modèle `App\Models\Admin implements FilamentUser`. Conforme à CLAUDE.md §1 ("un client ne peut jamais accéder au BO, un admin n'est pas un client") : les deux guards étant totalement séparés, un client connecté en guard `web` est traité comme anonyme sur `/admin` — testé (`tests/Feature/AdminPanelTest.php`).
- Chemin du panneau configurable via `ADMIN_PATH` (`.env`) → exposé par `config/admin.php` (jamais `env()` directement dans le code applicatif, uniquement dans les fichiers `config/`, conformément à QUALITE.md §2.7/§4.2).
- 2FA obligatoire admin : **non implémenté en T00**, prévu en T03 (authentification) — hors périmètre de l'initialisation.

## 2026-10-05 — T00 — Fichiers générés par l'installeur Laravel

- Laravel 13 génère par défaut `AGENTS.md`, `CLAUDE.md` et `README.md` génériques ("Laravel Boost"). **Non utilisés** : le `CLAUDE.md` du projet est écrit à la main et fait autorité ; les génériques ont été écartés (ni copiés, ni fusionnés) pour éviter toute confusion entre les règles du projet et un gabarit générique. `README.md` réécrit spécifiquement pour ce projet.

## 2026-10-05 — T00 — Environnement local

- Extension PHP `bcmath` **non installée** sur la machine de développement (vérifié, absente). Non bloquant : ni Laravel, ni les packages installés en T00 n'en dépendent. Montants gérés en entiers (centimes) conformément à CLAUDE.md §3.6, qui ne nécessite pas `bcmath`. À surveiller si une future dépendance (T14 Stripe ?) venait à le requérir.

## 2026-10-05 — T02 — Modèle de données

- **Table `users`** : la migration par défaut de Laravel (`name`) a été adaptée dans une **nouvelle** migration (`adjust_users_table_for_v1`) plutôt que modifiée directement, conformément à CLAUDE.md §3.4 (une migration déjà mergée dans `develop` ne se modifie jamais). Résultat : `first_name`/`last_name` séparés (PLAN.md §22), `phone` (obligatoire pour le SMS Chronopost, PLAN §9.1), `deletion_requested_at` (PLAN §13).
- **`orders.relay_snapshot`** stocké en JSON plutôt qu'en colonnes séparées (`relay_address_*`) : PLAN.md §9.4 identifie explicitement cette zone comme ambiguë ("quels champs exacts pour le snapshot complet ?"). Le JSON permet de capturer tout ce que l'API Chronopost renvoie (nom, adresse, horaires, distance) sans décider prématurément d'un schéma rigide ; `relay_id` et `relay_name` restent des colonnes dédiées (recherche/affichage rapides).
- **Pas de table `vat_rates`** : le modèle de données `PLAN.md §22` ne prévoit pas de table séparée pour les taux de TVA, seulement une colonne `products.vat_rate` (decimal). La "liste paramétrable" des taux sélectionnables en BO (mentionnée dans la fiche T02) relève de `spatie/laravel-settings`, installé en **T04** — non disponible en T02. Reporté à T04 : seeding réel des taux à ce moment-là (valeurs toujours **À CONFIRMER** par le comptable, CLAUDE.md §3.1).
- **Redirection générique `/products/{slug}` → `/produit/{slug}`** (PLAN §23) : non seedée dans `redirects` (table de correspondances exactes). C'est une règle de motif, implémentée dans le middleware de redirection (**T20**), pas une ligne de données.
- **`order_status_histories`** : table d'audit append-only, `UPDATED_AT` désactivé (`const UPDATED_AT = null`) — une ligne d'historique ne se modifie jamais.
- Les transitions de statut autorisées (PLAN §9.2) sont encodées directement sur l'enum `OrderStatus::allowedNextStatuses()` (donnée statique), pas dans un service séparé : `App\Services\Orders\OrderStateMachine` (T12) s'appuiera dessus plutôt que de redéfinir la table de transitions.

## 2026-10-06 — T09 — Date d'expédition

- `ShippingDateCalculator::forInstant()` : algorithme PLAN §8.4 implémenté au pied de la lettre, y compris la frontière **stricte** ("si heure **>** heure limite" — pile à l'heure limite = encore dans les temps, pas `>=`).
- Les jours fermés (`closed_dates`) comptent dans le budget de jours de délai de fabrication **uniquement s'ils sont fermés** ; un jour non-ouvré (ex. samedi) qui n'est pas explicitement fermé compte quand même comme un jour de délai écoulé — seule l'étape finale (avancer jusqu'au premier jour d'expédition) filtre sur `shipping_weekdays`. Comportement déduit du texte PLAN ("en sautant les jours fermés" ≠ "en sautant les jours non ouvrés") — à reconfirmer avec la cliente si le comportement observé en recette (T24) ne correspond pas à son intuition métier.
- Réutilisation de `App\Exceptions\Shipping\ShippingNotConfigured` (déjà créée en T10) pour la configuration de date incomplète : même famille d'erreur "paramètre d'expédition manquant, tunnel bloqué".

## 2026-10-06 — T10 — Frais de port

- **Priorité réordonnée** : la liste de tâches transmise (T07→T08→T09→T10) a été traitée **T07, puis T10, T09, T08** — T10/T09 sont des services de calcul purs sans dépendance à T08, et T08 (panier) les consomme directement. Évite de coder un panier avec un texte de repli temporaire ("calculés à l'étape suivante") puis de le retirer juste après. Décision prise sous contrainte de temps (session limitée), signalée au client.
- `ShippingCostCalculator::forWeight()` : exceptions dédiées `ShippingNotConfigured` (grille vide) et `WeightOutOfRange` (poids hors grille), jamais de port à 0 € silencieux (CLAUDE.md §4).
- Franco : vérifié **avant** la recherche de tranche (court-circuit), lit `ShippingSettings::free_shipping_enabled/threshold_ttc` (T04).

## 2026-10-06 — T07 — Pages catalogue

- **URLs catégories à la racine** (`/{category:slug}`, ex. `/pains-sans-gluten`), avec contrainte regex d'exclusion des autres routes nommées (`boutique`, `produit`, `mon-compte`, etc.) pour éviter toute collision — cohérent avec les redirections 301 Shopify déjà seedées en T02. **À maintenir** : toute nouvelle route racine (ex. `/faq`, `/contact` en T20) devra être ajoutée à la liste d'exclusion.
- **`mews/purifier` ^3.4** ajouté : les champs RichEditor (`description`, `ingredients`) sont affichés via `{!! !!}` côté public — purifiés **à l'écriture** (mutateur sur `Product`), pas à la lecture, pour une seule passe et un point d'application unique. Un admin compromis ne peut pas injecter de script via ces champs. Anticipe partiellement T23 ("revue purification contenu riche"), qui devra vérifier `Page`/`FaqItem` de la même façon.
- **`Product::scopePublished()`** : seul filtre de visibilité catalogue (`is_published`). `is_available`/`is_shippable` n'affectent que la commandabilité (produit toujours visible), conformément à PLAN §5.3.
- Bouton « Ajouter au panier » **non fonctionnel en V1 de cette tâche** (placeholder visuel) : le service de panier réel arrive en T08.

## 2026-10-06 — T06 — Design système et layout public

- **Tokens visuels relevés sur https://painsansgluten.fr** via les custom properties CSS de son thème (devtools, pas de code Liquid/CSS copié) : fond crème `#FEFCF2`, vert sauge `#5D6E41` (titres + boutons primaires), ocre `#B47C38` (accent/hover), texte `rgba(0,0,0,.81)`, rayons (boutons/pills 10px, champs 4px, cartes 0.8rem), ombres (`0 2px 3px rgb(0 0 0/20%)` boutons, `0px 4px 20px rgb(0 0 0/.15)` tiroirs/popovers). Transposés en thème Tailwind v4 (`@theme` dans `resources/css/app.css`), jamais de valeur inventée.
- **Police Inter auto-hébergée** via `laravel-vite-plugin/fonts` (`bunny()`, déjà dans le scaffold Laravel 13 pour "Instrument Sans", simplement reconfiguré pour "Inter" — la police réelle du site, vérifiée via `--font-body--family` du thème). Choix préféré à `@fontsource/inter` (installé puis retiré) : un seul mécanisme de self-hosting, intégré nativement à Vite, pas de dépendance supplémentaire.
- **Alpine.js** (`npm install alpinejs`) pour les interactions UI pures (tiroir, modale, méga-menu, menu mobile) sans solliciter Livewire sur de la simple UI côté client — Livewire reste réservé au panier/tunnel (état serveur, T08).
- **Visuel de catégorie** (`Category::cover`, medialibrary) ajouté a posteriori à la ressource Filament Catégories de T05 : le méga-menu (exigé par cette tâche T06, PLAN §5.1) a besoin d'un visuel par catégorie, qui n'existait pas dans le modèle de données initial.
- **Logique hors des vues** (QUALITE.md §4.2) : `App\View\Composers\{Header,Footer}Composer` injectent les données partagées (paramètres, catégories, pages) plutôt que des requêtes dans les composants Blade. Exception assumée pour `errors/404.blade.php` : rendue directement par le gestionnaire d'exceptions Laravel (hors cycle de routage normal), un View Composer ne s'y déclenche pas de manière fiable — requête directe documentée en commentaire dans le fichier.
- Page d'accueil (`welcome.blade.php`) remplacée par une version minimale utilisant le nouveau layout, pour valider le pipeline de bout en bout ; le contenu réel (bannière, produits mis en avant) reste à construire en **T07**.

## 2026-10-06 — T05 — Catalogue back-office

- **`spatie/laravel-medialibrary` ^11.23** + **`filament/spatie-laravel-media-library-plugin` ^3.3**. Nécessite l'extension PHP `gd` (absente par défaut sur la machine de dev — installée en cours de tâche : `sudo apt-get install php8.3-gd`). Driver d'image : `gd` (config par défaut du package), conversions converties en **WebP**, **non queued** (`->nonQueued()`) : exécutées immédiatement à l'upload plutôt qu'en file d'attente — plus simple pour un back-office à faible volume (~70 produits), évite un état transitoire "image pas encore convertie" si le worker de queue n'est pas démarré.
- **Piège Larastan résolu** : sur un objet `Conversion`, `->nonQueued()` doit être appelé **avant** `->fit()`/`->format()` (ces méthodes de manipulation d'image proxient ensuite vers `Spatie\Image\Drivers\ImageDriver`, qui n'a plus la méthode `nonQueued()`). Documenté ici pour la prochaine IA qui ajouterait une conversion.
- **Texte alternatif par image (obligatoire pour publier, PLAN.md §6.2)** : le plugin Filament ne permet pas nativement un champ de formulaire par fichier individuel dans un upload multiple. Solution retenue : `SpatieMediaLibraryFileUpload` gère l'upload/réordonnancement, et un `Repeater` séparé (`galleryAltTexts`) — hydraté depuis les médias déjà attachés, sauvegardé via `afterSave()`/`afterCreate()` sur les pages Filament — gère le texte alternatif de chaque image. **Limite connue** : à la création, les images tout juste uploadées n'apparaissent dans ce Repeater qu'après un premier enregistrement (flux "uploader puis légender à l'édition"), ce qui est raisonnable en usage réel BO.
- **Taux de TVA** : proposé en `Select` avec les 4 taux légaux français (2,1 % / 5,5 % / 10 % / 20 %) plutôt qu'un champ libre — ce sont des paliers légaux publics, pas une valeur métier inventée ; le taux **applicable à chaque produit** reste à valider par le comptable (inchangé depuis T02/T04).
- **Suppression bloquée si le produit a été commandé** (`Product::hasBeenOrdered()`) : appliqué sur l'action de suppression individuelle, la suppression définitive (ForceDelete) et la suppression groupée — pas de contrainte FK en base (`order_items.product_id` en `nullOnDelete`), donc la règle est strictement applicative (Filament), pas un piège silencieux au niveau BDD.
- Tests medialibrary : disque `public` **toujours faké** (`Storage::fake('public')`) pour ne pas écrire de vrais fichiers sur disque pendant la suite de tests.

## 2026-10-06 — T04 — Paramètres boutique et apparence

- **`spatie/laravel-settings` ^3.9** + **`filament/spatie-laravel-settings-plugin` ^3.3** : 6 classes de paramètres (`ShopSettings`, `BillingSettings`, `BankTransferSettings`, `ShippingSettings`, `HomepageSettings`, `SeoSettings`), chacune avec sa page Filament dédiée (auto-découverte via `discoverPages`). C'est le "helper/service unique" demandé par la fiche T04 : tout le code lit/écrit via `app(XxxSettings::class)`, jamais de configuration dupliquée ailleurs.
- **Toutes les valeurs restent `null`/vides** sauf les deux explicitement validées par la cliente (`bank_transfer.cancel_after_days = 5`, `shipping.max_quantity_per_line = 20`) et deux textes déjà rédigés dans PLAN.md lui-même (§5.3, §8.4 : message produit non expédiable, message retrait relais) — jamais de valeur inventée (CLAUDE.md §3.1).
- **`App\Services\Settings\ConfigurationStatus`** : point d'entrée unique pour savoir si la configuration bloquante est complète (coordonnées boutique, SIRET/TVA, IBAN/BIC, paramètres d'expédition, grille de frais de port non vide). Utilisé par le widget d'alerte du tableau de bord (T04) ; sera réutilisé tel quel pour bloquer le tunnel de commande (T09/T13) plutôt que de dupliquer cette logique.
- **IBAN validé par une règle maison** (`App\Rules\Iban`, somme de contrôle mod-97) plutôt qu'un package dédié : validation triviale (QUALITE.md §2.14).
- **Upload logo/favicon/bannière** : `Filament\Forms\Components\FileUpload` simple (disque `public`), **sans** `spatie/laravel-medialibrary` — ce package est réservé aux images produits (galerie, conversions WebP), prévu en T05. Pas de conversions d'images pour les visuels de paramètres en V1.
- Widget `ConfigurationAlertsWidget` **non lazy** (`$isLazy = false`) : calcul trivial, doit être visible immédiatement au chargement du tableau de bord (pas de round-trip Livewire différé).
- Migration `database/settings/..._create_settings_groups.php` renommée/réordonnée pour s'exécuter **après** `create_settings_table` (les migrations de `database/settings/` et `database/migrations/` sont fusionnées et triées par nom de fichier par le migrateur Laravel).

## 2026-10-06 — T03 — Authentification

- **Laravel Fortify ^1.40** pour l'authentification client (guard `web`) : inscription, connexion, mot de passe oublié/réinitialisation, vérification d'email, mise à jour du profil/mot de passe — toutes "officielles", aucun mécanisme maison (CLAUDE.md §3.1).
- **2FA et passkeys désactivés** dans `config/fortify.php` (`features`) : non requis en V1 (voir décision ci-dessous). Les migrations publiées par le package pour ces fonctionnalités (`two_factor_columns`, `passkeys_table`) ont été **supprimées** plutôt que migrées : elles créeraient des colonnes/tables mortes (QUALITE.md §4.3 YAGNI). Les paquets `laravel/passkeys`, `pragmarx/google2fa`, `bacon/bacon-qr-code` restent installés (dépendances dures de Fortify), mais aucun code applicatif ne les utilise.
- **URLs en français** pour les routes d'authentification (`config/fortify.php` → `paths`) : `/connexion`, `/inscription`, `/mot-de-passe-oublie`, `/verifier-email`, etc., conformément à CLAUDE.md §5 ("nommage routes FR côté public"). Les noms de routes Laravel restent en anglais (`login`, `register`…), seule l'URL change.
- **`App\Models\User`** implémente `MustVerifyEmail` (email obligatoire avant accès à `/mon-compte`, PLAN §13) et expose un accesseur `name` calculé (`first_name` + `last_name`) pour la compatibilité avec les notifications Fortify/Laravel qui l'attendent.
- **Vues Blade minimales, non stylées** (`resources/views/auth/*.blade.php`, `resources/views/compte/stub.blade.php`) : fonctionnelles mais sans design, conformément à la fiche T03 ("finalisées en T06/T18"). `/mon-compte` est un stub de redirection, le vrai tableau de bord client se construit en T18.
- **Rate limiting** : 5 tentatives/minute par (email + IP) sur la connexion (limiteur `login`), comportement par défaut de Laravel (middleware `throttle`), réponse HTTP 429 au-delà — testé.
- Guard `admin` (Filament, T00) non affecté par l'installation de Fortify (`fortify.guard = web` uniquement) ; testé que la séparation reste étanche.

## 2026-10-06 — Changement de périmètre : 2FA admin reporté en V2

- **Décision client**, pendant la préparation de T03. Le PLAN.md (et QUALITE.md, CHECKLIST-PROD.md) mentionnaient à plusieurs endroits un 2FA **obligatoire** sur le back-office Filament en V1.
- **Changement** : le 2FA n'est plus exigé pour la V1. La connexion admin reste guard `admin` séparé + rate limiting, mais sans second facteur.
- **Documents amendés** (note visible, texte original conservé en historique via Git) : `PLAN.md` (amendement en tête de document), `QUALITE.md` §2.4, `CHECKLIST-PROD.md` §recette parcours admin, `tasks/T03-authentification.md`.
- Ajouté à `docs/BACKLOG.md` pour V2.
- Impact : aucun sur T00/T02 déjà livrées. T03 ne doit pas installer de package 2FA.

## 2026-10-05 — Rangement des fichiers non applicatifs

- `audit-painsansgluten.html` (audit du site Shopify existant) et `documents/` (archive brute reçue de la cliente) déplacés dans `docs/reference/` pour ne pas mélanger matériel de référence et structure applicative Laravel (qui doit rester à la racine du dépôt — conventions `artisan`/`public/index.php`/déploiement Nginx déjà décrites dans `CHECKLIST-PROD.md` et la tâche T25).
