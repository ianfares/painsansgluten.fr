# Mon Sans Gluten by Angélique — Boutique en ligne

Boutique en ligne sur mesure pour une boulangerie artisanale 100 % sans gluten (Avranches, Manche). Stack : Laravel 13, Filament 3, Livewire, Tailwind CSS, MariaDB.

**Avant toute intervention, lire dans l'ordre** : `CLAUDE.md`, `QUALITE.md`, `PLAN.md`, `docs/JOURNAL.md`, puis la fiche de tâche concernée dans `tasks/`.

## Prérequis

- PHP ^8.3 avec extensions : `pdo_mysql`, `mbstring`, `xml`, `curl`, `fileinfo`, `ctype`, `openssl`, `tokenizer`
- Composer 2.x
- Node 20+ / npm
- MariaDB 10.11+ (pas PostgreSQL, pas SQLite en production)

## Installation locale

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Créer deux bases MariaDB (dev + test) et un utilisateur dédié :

```sql
CREATE DATABASE boulangerie_gluten CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE boulangerie_gluten_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'boulangerie_gluten'@'localhost' IDENTIFIED BY 'un_mot_de_passe_local';
GRANT ALL PRIVILEGES ON boulangerie_gluten.* TO 'boulangerie_gluten'@'localhost';
GRANT ALL PRIVILEGES ON boulangerie_gluten_test.* TO 'boulangerie_gluten'@'localhost';
FLUSH PRIVILEGES;
```

Renseigner `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` dans `.env` (voir `.env.example` pour la liste complète des variables, y compris les placeholders Chronopost/Stripe/Brevo/GTM/GA4 à compléter au fil des tâches).

```bash
php artisan migrate
npm run build
php artisan make:admin   # crée un compte back-office (guard "admin", table admins)
php artisan serve
```

Back-office : `http://localhost:8000/{ADMIN_PATH}` (`admin` par défaut, voir `.env`).

## Commandes utiles

```bash
php artisan test                # suite Pest complète
./vendor/bin/pint               # style de code (--test pour vérifier sans corriger)
./vendor/bin/phpstan analyse    # analyse statique (niveau 5, voir phpstan.neon)
composer audit                  # vulnérabilités des dépendances PHP
npm audit                       # vulnérabilités des dépendances front
npm run dev                     # Vite en mode développement
npm run build                   # build des assets pour la préproduction/production
```

Ces quatre vérifications (`test`, `pint --test`, `phpstan analyse`, `composer audit`) doivent passer avant de clore toute tâche (CLAUDE.md §3, QUALITE.md §1 étape 5).

## Structure du dépôt

- `app/`, `routes/`, `resources/`, `database/`, `config/`, `tests/` — application Laravel standard.
- `tasks/T00…T25` — une fiche par tâche, dans l'ordre d'exécution prévu.
- `docs/DECISIONS.md` — choix techniques justifiés.
- `docs/BACKLOG.md` — idées hors périmètre V1.
- `docs/JOURNAL.md` — historique des sessions (toute IA qui intervient y ajoute une entrée).
- `docs/reference/` — matériel de référence (audit du site existant, archive brute du cadrage client).
- `CHECKLIST-PROD.md` — recette avant ouverture des ventes.

## Branches

`main` (prod) ← `develop` (intégration) ← `feature/TXX-nom` (une tâche = une branche).
