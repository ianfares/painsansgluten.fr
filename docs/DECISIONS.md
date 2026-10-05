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

## 2026-10-05 — Rangement des fichiers non applicatifs

- `audit-painsansgluten.html` (audit du site Shopify existant) et `documents/` (archive brute reçue de la cliente) déplacés dans `docs/reference/` pour ne pas mélanger matériel de référence et structure applicative Laravel (qui doit rester à la racine du dépôt — conventions `artisan`/`public/index.php`/déploiement Nginx déjà décrites dans `CHECKLIST-PROD.md` et la tâche T25).
