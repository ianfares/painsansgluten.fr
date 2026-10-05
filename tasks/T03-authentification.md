# T03 — Authentification clients et administrateurs
**Dépend de** : T02  **Branche** : `feature/T03-auth`  **Réf.** : PLAN §13, §20

> ⚠️ **Décision client du 2026-10-06** : le 2FA admin est **reporté en V2**, retiré du périmètre V1. Voir `docs/DECISIONS.md` et `docs/BACKLOG.md`. Ne pas l'implémenter dans cette tâche.

## À faire
- Clients (guard `web`) via starter kit / Fortify officiel : inscription, connexion, déconnexion, mot de passe oublié, réinitialisation, **vérification d'email**, changement de mot de passe.
- Vues Blade aux couleurs du site (finalisées en T06/T18).
- ~~Admins (guard `admin`, Filament) : **2FA obligatoire**~~ → reporté en V2 (voir note ci-dessus). Connexion admin simple (email + mot de passe) pour la V1.
- Rate limiting : connexion client et admin, mot de passe oublié.
- Chemin admin configurable via `.env` (`ADMIN_PATH`, défaut `admin`).

## ⚠️ Avertissements
- Aucun mécanisme cryptographique maison.
- Un client ne peut jamais accéder au BO ; un admin n'est pas un client.

## Tests
- Inscription, vérification email, connexion, reset, rate limiting, client refusé sur `/admin`.

## Critères d'acceptation
- Parcours complet fonctionnel en local (mailer `log`).
