# T22 — GTM, GA4, tarteaucitron, Consent Mode v2
**Dépend de** : T13, T14  **Branche** : `feature/T22-analytics`  **Réf.** : PLAN §18

## À faire
- tarteaucitron.js auto-hébergé, FR, bouton permanent dans le pied de page.
- Consent Mode v2 : `default denied` **avant** GTM ; `update granted` via tarteaucitron.
- GTM (ID en `.env`), aucun tag GA4 en dur.
- `dataLayer` : `view_item`, `view_item_list`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`.
- `purchase` : confirmation Stripe, commande `paid` en BDD, drapeau `ga_purchase_sent` (une seule fois). Pas de `purchase` pour les virements (V2).
- Documentation `docs/GTM.md` : variables, déclencheurs et balises à créer dans GTM.

## Tests / recette
- Aucun cookie `_ga` avant consentement (vérification manuelle DevTools, consignée dans CHECKLIST).
- Rechargement de la confirmation = pas de second `purchase`.
