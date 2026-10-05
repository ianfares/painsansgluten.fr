# T00 — Initialisation du projet
**Dépend de** : —  **Branche** : `feature/T00-init`  **Réf.** : PLAN §2, §4, CLAUDE.md

## Objectif
Créer le squelette Laravel prêt pour le développement.

## À faire
- Installer Laravel (dernière stable) ; noter la version dans `docs/DECISIONS.md`.
- Configurer MariaDB, `APP_LOCALE=fr`, `APP_TIMEZONE=Europe/Paris`, `LOG_CHANNEL=daily`.
- Installer Filament (panel `/admin`, guard `admin`, table `admins` distincte des `users`).
- Installer : Pest, Pint, Larastan (niveau 5), Tailwind + Vite, Livewire.
- Créer `docs/DECISIONS.md`, `docs/BACKLOG.md`, `README.md` (installation locale, commandes).
- `.env.example` complet avec valeurs factices : DB, Stripe, Chronopost, Brevo, GTM, GA4, ADMIN_PATH.
- Lang FR (validation, pagination, auth).
- Commande `php artisan make:admin` (création d'un admin en CLI).

## Hors périmètre
Toute fonctionnalité métier.

## Critères d'acceptation
- `php artisan test`, `pint --test`, `phpstan` passent.
- `/admin` affiche la page de connexion Filament.
- `.env` absent de Git, `.env.example` présent.
