# Revue de sécurité (T23)

Livrable de la tâche T23. Le détail de l'audit complet du 2026-10-09 (constats, gravité, corrections, tests) est dans `docs/AUDIT-SECURITE-2026-10-09.md`.

## Points vérifiés

| Point | État |
|---|---|
| Vraie IP des visiteurs derrière Cloudflare | ✅ Apache `mod_remoteip` (`RemoteIPHeader CF-Connecting-IP`, `RemoteIPTrustedProxy` = plages officielles Cloudflare). Laravel lit directement la bonne IP ; pas de « trusted proxies » Laravel. Vérifié : IP réelle dans la table `sessions`, en-tête falsifié depuis une IP non Cloudflare ignoré. |
| En-têtes de sécurité | ✅ middleware `SecurityHeaders` (toutes réponses) : `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS (1 an, hors local, HTTPS seulement), `X-Powered-By` retiré. |
| CSP | ⚠️ en **report-only** (ne bloque rien) : autorise Livewire/Alpine (`unsafe-eval`), Turnstile, GTM/GA4, polices Google, retour vers Stripe Checkout. À durcir après T22 (GTM) en lisant les rapports de la console. |
| Cookie de session | ✅ `SESSION_SECURE_COOKIE=true` en préprod (à reporter en prod), `HttpOnly` et `SameSite=Lax` par défaut. |
| Policies, IDOR | ✅ audit 2026-10-09 : aucun accès aux commandes/factures d'un autre client. |
| Validations | ✅ tunnel revalidé côté serveur ; formulaires publics avec Turnstile ; contact limité à 5/min. |
| Contenu riche | ✅ HTML Purifier à l'enregistrement (descriptions, ingrédients, FAQ), saisie admin uniquement. |
| Uploads | ✅ PNG/JPEG/WebP (+ ICO favicon), taille max, renommage par la médiathèque. |
| Données personnelles dans les logs | ✅ aucune (email retiré du seul log concerné). |
| `APP_DEBUG` | ✅ `false` en préprod ; `.env.example` reste en valeurs locales. |
| Dépendances | ✅ `composer audit` : aucune faille ; `npm audit` : 0 vulnérabilité. |

## Risques résiduels

- **Accès direct au serveur sans passer par Cloudflare** : l'IP du serveur reçoit des requêtes directes (robots qui scannent les IP), qui contournent les règles Cloudflare (pays, WAF). Recommandation : pare-feu n'acceptant le web (80/443) que depuis les plages Cloudflare (voir journal du 2026-10-09).
- **Règles Cloudflare et webhook Stripe** : tout défi (pays, bot) appliqué à `POST /webhooks/stripe` bloque la confirmation des paiements. Une règle d'exception doit exister pour ce chemin.
- CSP en report-only (voir ci-dessus).
- Points acceptés pour la V1 : voir la section « Accepté » du rapport d'audit.
