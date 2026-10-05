# T23 — Durcissement sécurité
**Dépend de** : toutes les tâches fonctionnelles  **Branche** : `feature/T23-security`  **Réf.** : PLAN §20

## À faire
- Trusted proxies Cloudflare.
- En-têtes de sécurité (middleware) ; CSP en `report-only` (GTM, Stripe, tarteaucitron).
- Revue des policies (commandes, factures, adresses).
- Revue des validations (Form Requests / Livewire) et de la purification du contenu riche.
- Revue uploads (MIME, taille, renommage).
- Vérification : aucune donnée personnelle dans les logs, `APP_DEBUG=false` hors local, `.env.example` à jour.
- `composer audit`, `npm audit`.

## Livrable
`docs/SECURITY-REVIEW.md` : points vérifiés, corrections, risques résiduels.
