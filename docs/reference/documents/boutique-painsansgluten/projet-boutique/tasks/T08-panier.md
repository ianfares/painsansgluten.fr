# T08 — Panier
**Dépend de** : T07  **Branche** : `feature/T08-cart`  **Réf.** : PLAN §7

## À faire
- Service `CartService` (logique hors Livewire) : add, update, remove, totals, merge.
- Panier BDD par session (invité) ou utilisateur ; fusion à la connexion.
- Tiroir latéral (Livewire) + page `/panier`.
- Totaux : sous-total TTC, frais de port (via T10 dès que dispo, sinon « calculés à l'étape suivante »), total, date d'expédition prévue (via T09).
- Quantité max par ligne (paramètre).
- Retrait automatique des produits devenus indisponibles / non expédiables, avec message.
- Tâche planifiée : purge des paniers invités > 30 jours.

## Tests
PLAN §25 « Panier ».

## Critères d'acceptation
- Fonctionnel mobile et desktop, sans rechargement de page.
