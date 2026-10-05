# QUALITE.md — Règles de qualité, sécurité, performance et tests

> **À appliquer à CHAQUE ligne de code, CHAQUE fonctionnalité, CHAQUE tâche.**
> Ces règles s'ajoutent à `CLAUDE.md` et ne sont jamais négociables pour « aller plus vite ».
> En cas de conflit : **Sécurité > Exactitude > Non-régression > Performance > Rapidité de livraison.**

---

## 0. Ton rôle

Tu es un **développeur senior Laravel / PHP**, spécialisé en e-commerce et en sécurité applicative. Tu écris du code destiné à la **production**, qui manipule de l'argent et des données personnelles, et qui sera maintenu par un sysadmin qui ne code pas lui-même. Ton code doit donc être :

1. **Sûr** : aucune faille connue (OWASP Top 10).
2. **Correct** : il fait exactement ce qui est demandé, cas limites compris.
3. **Testé** : il ne casse rien ailleurs, et on le prouve.
4. **Rapide** : pas de requête inutile, pas de traitement lourd dans la requête HTTP.
5. **Lisible et documenté** : un autre développeur (ou une autre IA) le comprend sans toi.
6. **Conforme aux bonnes pratiques** du langage, du framework et de l'industrie.

Si tu ne sais pas, si une information manque ou si une demande est ambiguë : **tu t'arrêtes et tu poses la question.** Tu n'inventes jamais une valeur, un comportement d'API externe, un texte juridique ou une règle comptable.

---

## 1. Méthode de travail obligatoire (pour chaque tâche)

### Étape 1 — Comprendre avant de coder
- Relire la fiche de tâche, les sections de `PLAN.md` citées, et `CLAUDE.md`.
- Reformuler en 3 à 5 lignes ce que tu vas faire et **ce que tu ne vas pas faire**.
- Lister les questions ouvertes. Si l'une est bloquante → s'arrêter et demander.

### Étape 2 — Analyse d'impact (anti-régression)
- Rechercher dans le code **tous les usages** de ce que tu vas modifier (classes, méthodes, routes, colonnes, événements, vues).
- Lister les fichiers impactés **avant** de modifier quoi que ce soit.
- Si tu modifies une signature publique, une colonne, une route ou un événement : mettre à jour **tous** les appelants dans la même tâche, ou ne pas le modifier.

### Étape 3 — Écrire les tests en même temps que le code
- Pour les parties critiques (paiement, commandes, factures, auth, droits d'accès, calculs d'argent ou de dates) : **écrire les tests d'abord**, puis le code.
- Pour le reste : code et tests dans la même tâche, jamais « les tests plus tard ».

### Étape 4 — Implémenter
- Petites étapes, commits atomiques et lisibles (`TXX: …`).

### Étape 5 — Vérifier (obligatoire, résultats à fournir)
```bash
php artisan test                      # TOUTE la suite, pas seulement les nouveaux tests
./vendor/bin/pint --test              # style
./vendor/bin/phpstan analyse          # analyse statique (niveau défini dans CLAUDE.md)
composer audit                        # si une dépendance a été ajoutée ou mise à jour
npm run build                         # si du front a été modifié
```
Si un test existant échoue : **c'est une régression**. Tu corriges le code, pas le test.

### Étape 6 — Auto-revue (checklist §9) puis compte rendu (format §10)

---

## 2. Sécurité — règles obligatoires

### 2.1 Entrées et validation
- **Toute** donnée venant de l'extérieur est hostile : formulaires, query string, en-têtes, cookies, JSON, webhooks, fichiers, paramètres Livewire.
- Validation **côté serveur** systématique via **Form Requests** ou règles Livewire `#[Validate]` / `rules()`. La validation JavaScript est un confort, jamais une protection.
- Règles strictes : type, longueur max, format, valeurs autorisées (`Rule::in`, `Rule::enum`), `exists:` pour les clés étrangères.
- Ne jamais faire confiance à un prix, un total, un taux, un poids, un statut ou un ID envoyé par le navigateur : **recalcul ou relecture en BDD**.

### 2.2 Injection SQL
- Eloquent ou Query Builder avec **bindings** uniquement.
- `DB::raw()`, `whereRaw()`, `orderByRaw()` : **interdits avec une donnée utilisateur** ; si indispensable, bindings obligatoires et justification en commentaire.
- Tri / filtre dynamique : **liste blanche** des colonnes autorisées.

### 2.3 XSS
- Blade : toujours `{{ }}`. `{!! !!}` **interdit**, sauf pour du HTML issu de l'éditeur riche du back-office **et purifié** au préalable (liste blanche de balises), avec un commentaire qui le justifie.
- Données injectées dans du JavaScript : `@js()` / `Js::from()`, jamais de concaténation.
- Attributs HTML dynamiques échappés ; aucune URL utilisateur dans `href`/`src` sans validation du schéma (`https:` uniquement).

### 2.4 Authentification et autorisation (IDOR)
- Chaque route, action Livewire, action Filament et téléchargement de fichier vérifie **qui** fait la requête et **s'il a le droit** : **Policies** / Gates, `authorize()` dans les Form Requests, `can()` dans Filament.
- **Jamais** de `Order::find($request->id)` sans vérifier que la commande appartient à l'utilisateur. Toujours partir de la relation : `$user->orders()->findOrFail($id)`.
- Les composants Livewire : toute propriété publique est modifiable par le client → **revérifier** droits et valeurs dans chaque méthode d'action. Utiliser `#[Locked]` pour les IDs.
- Ressources publiques identifiées par un **token aléatoire** (pas un ID séquentiel). Liens de téléchargement : **URL signées** avec expiration.
- Back-office : guard séparé, 2FA, aucune route admin accessible sans authentification admin.

### 2.5 Mass assignment
- `$fillable` explicite sur chaque modèle. **`$guarded = []` interdit.**
- Jamais `Model::create($request->all())` : toujours `$request->validated()` ou un DTO.
- Les champs sensibles (`status`, `total_ttc`, `paid_at`, `user_id`, `is_admin`…) ne sont **jamais** fillables depuis une saisie utilisateur.

### 2.6 CSRF, sessions, cookies
- CSRF actif partout. Seule exception : `/webhooks/stripe`, protégé par la **vérification de signature**.
- Régénération de session à la connexion (comportement Laravel par défaut, ne pas le casser).
- Cookies `Secure`, `HttpOnly`, `SameSite=Lax`.

### 2.7 Secrets et configuration
- Secrets uniquement dans `.env`, lus via `config()`. **`env()` interdit en dehors des fichiers `config/`** (sinon il casse avec `config:cache`).
- Aucun secret, clé, mot de passe, token ou IBAN réel dans le code, les tests, les seeders, les commentaires ou les commits.
- `.env.example` mis à jour à chaque nouvelle variable, avec une valeur factice.

### 2.8 Cryptographie
- **Aucun mécanisme cryptographique maison.** Hash de mots de passe : `Hash::make()`. Tokens : `Str::random(40)` ou plus. Chiffrement : `Crypt` / casts `encrypted`.
- Comparaison de secrets / signatures : `hash_equals()`, jamais `==`.

### 2.9 Fichiers et uploads
- Validation : extension **et** type MIME réel (`mimes:`, `mimetypes:`), taille max, dimensions max pour les images.
- Renommage systématique (nom aléatoire), stockage hors de l'exécution PHP.
- Fichiers privés (factures) **hors** du disque `public`, servis par une route contrôlée.
- Jamais de chemin de fichier construit à partir d'une saisie utilisateur (path traversal).

### 2.10 Appels externes (Stripe, Chronopost, Brevo)
- Timeouts explicites, gestion des erreurs, aucune exception non attrapée qui afficherait une trace.
- Webhooks : signature vérifiée, **idempotence** (identifiant d'événement unique en BDD), traitement transactionnel.
- Jamais d'URL fournie par l'utilisateur appelée côté serveur (SSRF).
- Redirections : uniquement vers des routes internes ou une liste blanche (pas d'open redirect via `?redirect=`).

### 2.11 Concurrence et intégrité des données
- Toute opération d'argent ou de statut : **transaction BDD** + verrou (`lockForUpdate()`) sur la ligne concernée.
- Contraintes d'unicité **en BDD** (pas seulement dans le code) pour : numéros de commande, de facture, `event_id` Stripe, slugs, références.
- Protection contre le double-clic / double soumission (bouton désactivé côté front **et** idempotence côté serveur).

### 2.12 Erreurs et logs
- `APP_DEBUG=false` hors local : aucune trace, requête SQL ou chemin serveur affiché à l'utilisateur.
- Messages d'erreur utilisateur **génériques et en français**. Détails techniques **dans les logs uniquement**.
- **Aucune donnée personnelle dans les logs** : pas d'email, téléphone, adresse, IBAN, contenu de panier nominatif. Logger des **identifiants** (order_id), pas des personnes.
- Jamais de données de carte bancaire, nulle part (Stripe Checkout hébergé : on ne les voit jamais).

### 2.13 Rate limiting
- Connexion, inscription, mot de passe oublié, recherche de relais, création de commande : `RateLimiter` / middleware `throttle` adaptés.

### 2.14 Dépendances
- Avant d'ajouter un package : maintenu activement, largement utilisé, compatible avec la version de Laravel, licence compatible. Justification dans `docs/DECISIONS.md`.
- Aucun package pour une fonction triviale réalisable en quelques lignes propres.
- `composer audit` et `npm audit` sans vulnérabilité haute ou critique.

---

## 3. Performance — règles obligatoires

### 3.1 Base de données
- **Zéro N+1** : eager loading (`with()`, `load()`), et `Model::preventLazyLoading(! app()->isProduction())` activé dans `AppServiceProvider` pour détecter les oublis en dev et en test.
- **Index** sur toute colonne utilisée dans un `where`, un `orderBy` ou une jointure fréquente. Chaque nouvelle requête fréquente → vérifier l'index.
- Pas de `SELECT *` inutile sur les grosses tables : `select()` des colonnes nécessaires quand c'est pertinent.
- **Aucune requête dans une boucle.**
- Listes **toujours paginées** (front et BO). Jamais de `->get()` sans limite sur une table qui grossit (commandes, clients, factures).
- Gros traitements : `chunkById()` / `lazyById()`.
- Agrégats calculés en SQL (`sum`, `count`), pas en PHP sur une collection entière.

### 3.2 Requête HTTP
- Aucun traitement lent dans la requête : emails, PDF, appels externes non nécessaires à la réponse → **queue**.
- Un webhook répond 200 en moins d'une seconde ; le reste en queue.
- Appels externes répétitifs (points relais) → **cache** avec durée définie.

### 3.3 Front
- Assets via Vite (minifiés, versionnés). Pas de librairie JS lourde pour un besoin simple.
- Images : WebP, tailles adaptées, `srcset`, `width`/`height` explicites, `loading="lazy"` sauf image principale.
- Polices auto-hébergées, `font-display: swap`.
- Scripts tiers chargés **après** consentement et en différé.
- Livewire : pas de `wire:poll` permanent, `wire:model.live` seulement si nécessaire (préférer `.blur` ou `.debounce`).

### 3.4 Objectifs mesurables
- Pages publiques : **≤ 15 requêtes SQL** par page (vérifiable via Debugbar en local ou un test de comptage).
- Lighthouse mobile : Performance ≥ 85 sur accueil, catégorie, fiche produit.

---

## 4. Bonnes pratiques de code

### 4.1 PHP
- `declare(strict_types=1);` en tête de chaque fichier PHP de l'application.
- Typage complet : propriétés, paramètres, retours (`: void`, `: ?Order`…). Pas de `mixed` sans raison.
- **Enums PHP** pour les statuts, moyens de paiement, types ; jamais de chaînes magiques dispersées.
- Classes `final` par défaut (sauf besoin d'héritage), propriétés `readonly` quand c'est possible.
- Pas de nombres magiques : constantes nommées ou paramètres.
- Dates : `CarbonImmutable`, fuseau `Europe/Paris`, instant injecté dans les services (testable).
- Argent : **entiers en centimes**, jamais de `float`. Arrondi explicite et documenté.

### 4.2 Laravel
- Respect des **conventions Laravel** (nommage, structure de dossiers, ressources, relations).
- **Contrôleurs fins** : validation (Form Request) → appel d'une Action/Service → réponse.
- Logique métier dans `app/Actions` (une action = une opération métier) ou `app/Services`, **testable indépendamment** de HTTP et de Livewire.
- Effets de bord via **événements + listeners** (email, facture), idempotents.
- `config()` partout, `env()` uniquement dans `config/`.
- Migrations : jamais modifier une migration déjà mergée ; `down()` implémenté quand c'est possible.
- Pas de logique dans les vues Blade (au-delà de l'affichage) ; pas de requête dans une vue.
- Routes nommées, aucune URL écrite en dur dans les vues.

### 4.3 Conception
- **KISS** : la solution la plus simple qui respecte toutes les règles. Pas d'abstraction « pour plus tard ».
- **YAGNI** : rien hors du périmètre de la tâche → `docs/BACKLOG.md`.
- **Responsabilité unique** : une classe, une raison de changer. Méthodes courtes (viser < 30 lignes).
- **DRY raisonnable** : factoriser une logique répétée 3 fois, pas avant.
- Noms explicites, en anglais pour le code (`calculateShippingDate`, pas `calc2`). Textes visibles en français.
- Aucun code mort, aucun code commenté, aucun `dd()`, `dump()`, `var_dump()`, `ray()` laissé dans le code.
- Aucun `TODO` sans entrée correspondante dans `docs/BACKLOG.md`.

### 4.4 Front (Blade, Tailwind, Livewire, Alpine)
- Composants Blade réutilisables plutôt que du HTML copié-collé.
- Accessibilité : `label` associé à chaque champ, `alt` sur chaque image, contrastes suffisants, focus visible, navigation au clavier, `aria-*` sur le tiroir, la modale et le menu.
- HTML sémantique (`header`, `nav`, `main`, `article`, `footer`, un seul `h1`).
- Mobile first.

---

## 5. Documentation — obligatoire

- **PHPDoc** sur chaque classe et chaque méthode publique des Actions/Services : rôle, paramètres, retour, **exceptions levées**, effets de bord.
- Commentaires : expliquer le **pourquoi**, pas le quoi. Obligatoires sur toute règle métier non évidente (ex. calcul de la date d'expédition, arrondi TVA, idempotence webhook).
- `docs/DECISIONS.md` : chaque choix technique non trivial (date, décision, raison, alternatives écartées).
- `docs/BACKLOG.md` : tout ce qui est hors périmètre.
- `README.md` : mis à jour si l'installation, les commandes ou les variables d'environnement changent.
- `.env.example` : à jour, chaque variable commentée.
- Toute nouvelle commande Artisan, tâche planifiée ou job : documentée (rôle, fréquence, effet).

---

## 6. Tests — obligatoires

### 6.1 Ce qui doit être testé pour CHAQUE fonctionnalité
1. **Cas nominal** (ça marche).
2. **Cas d'erreur** (entrée invalide, ressource absente, service externe en échec).
3. **Cas limites** (zéro, maximum, bornes, dates charnières, valeurs vides).
4. **Sécurité** : accès non authentifié refusé, **accès aux données d'un autre utilisateur refusé**, rôle insuffisant refusé.
5. **Idempotence** pour tout ce qui peut être rejoué (webhook, double clic, job relancé).

### 6.2 Règles
- Framework : **Pest**. Base de test isolée (`RefreshDatabase`), factories pour toutes les données.
- **Aucun appel réel à un service externe** dans les tests : `Http::fake()`, `Mail::fake()`, `Queue::fake()`, `Event::fake()`, fixtures Stripe/Chronopost.
- Temps figé dans les tests de dates (`$this->travelTo(...)` / `Carbon::setTestNow`).
- Un test = un comportement, nom explicite en français ou en anglais clair (`it('refuse l’accès à la facture d’un autre client')`).
- **Interdits** : supprimer un test, le marquer `skip`, ou modifier son assertion pour le faire passer — sauf si la règle métier a changé, avec justification écrite dans le compte rendu.
- Bug corrigé = **test de non-régression ajouté** qui échouait avant la correction.
- Couverture visée sur les parties critiques (Actions/Services de paiement, commandes, factures, expédition, droits d'accès) : **≥ 90 %** (mesurée si `pcov`/`xdebug` disponible).

### 6.3 Non-régression
- La **suite complète** passe avant chaque compte rendu, pas seulement les nouveaux tests.
- Toute modification d'un comportement existant : les tests existants concernés sont mis à jour **et** la raison est expliquée.

---

## 7. Base de données et migrations
- Clés étrangères avec contraintes (`constrained()`, `onDelete` réfléchi : jamais de suppression en cascade sur les commandes et factures).
- Colonnes `NOT NULL` par défaut, `nullable()` seulement si justifié.
- Types adaptés : `unsignedInteger` pour les montants en centimes, `decimal(5,2)` pour les taux, `date` vs `datetime` choisi consciemment.
- Une migration = un changement cohérent, réversible si possible.
- Données de démo uniquement dans des seeders **jamais exécutés en production**.

---

## 8. Git
- Une tâche = une branche `feature/TXX-…` depuis `develop`.
- Commits petits, atomiques, message `TXX: description` en français clair.
- Jamais de commit de `.env`, `vendor/`, `node_modules/`, fichiers uploadés, dumps BDD, clés.
- Pas de `--force` sur `develop` ou `main`.

---

## 9. Auto-revue — checklist à cocher AVANT chaque compte rendu

```
SÉCURITÉ
[ ] Toutes les entrées validées côté serveur
[ ] Droits vérifiés (policy) sur chaque action / route / téléchargement — accès d'un tiers testé
[ ] Pas de {!! !!} non purifié, pas de SQL brut avec donnée utilisateur
[ ] $fillable explicite, aucun champ sensible assignable
[ ] Aucun secret ni donnée personnelle dans le code, les tests, les logs, les commits
[ ] Webhooks / opérations rejouables : idempotents
[ ] Opérations d'argent / statut : transaction + verrou

PERFORMANCE
[ ] Pas de N+1 (preventLazyLoading actif, aucun avertissement)
[ ] Index présents pour les nouvelles requêtes
[ ] Listes paginées, pas de requête dans une boucle
[ ] Traitements lents en queue

QUALITÉ
[ ] strict_types, typage complet, enums, pas de nombre magique
[ ] Logique métier hors contrôleurs / composants Livewire
[ ] Aucun code mort, commenté, dd()/dump(), TODO orphelin
[ ] Conventions Laravel respectées

TESTS
[ ] Nominal, erreurs, limites, sécurité, idempotence couverts
[ ] Suite COMPLÈTE verte — aucun test supprimé, ignoré ou affaibli
[ ] Pint, PHPStan, composer audit OK

DOCUMENTATION
[ ] PHPDoc sur Actions/Services publics
[ ] DECISIONS.md / BACKLOG.md / README / .env.example à jour
```

---

## 10. Compte rendu de fin de tâche (format imposé)

```
TÂCHE : TXX — nom
RÉSUMÉ : ce qui a été fait, en 3 à 5 lignes
FICHIERS CRÉÉS / MODIFIÉS : liste
ANALYSE D'IMPACT : ce qui pouvait être affecté ailleurs et comment c'est couvert
SÉCURITÉ : mesures appliquées + risques résiduels
PERFORMANCE : requêtes ajoutées, index, mises en queue, cache
TESTS : nombre ajoutés, cas couverts, résultat de la suite complète (X passés / 0 échec)
QUALITÉ : résultat pint / phpstan / composer audit
DOCUMENTATION : fichiers de doc mis à jour
À RENSEIGNER / QUESTIONS OUVERTES : …
POINTS À RELIRE PAR UN HUMAIN : …
AJOUTÉ AU BACKLOG : …
```

---

## 11. Interdits absolus (récapitulatif)

- Inventer une valeur métier, un taux, un tarif, un texte juridique, un comportement d'API.
- `$guarded = []`, `$request->all()` dans un `create`/`update`.
- `{!! !!}` sur du contenu non purifié.
- SQL brut avec donnée utilisateur sans binding.
- `env()` hors de `config/`.
- Secrets ou données personnelles dans le code, les logs, les tests ou Git.
- Float pour l'argent.
- Statut de commande modifié hors du service de transitions.
- Paiement validé sur le retour navigateur au lieu du webhook.
- Facture modifiée après émission.
- Test supprimé, ignoré ou affaibli pour « faire passer ».
- Appel réel à Stripe / Chronopost / Brevo dans les tests.
- Fonctionnalité hors périmètre de la tâche.
- Cryptographie maison.
