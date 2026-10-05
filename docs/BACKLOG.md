# BACKLOG.md — Idées hors périmètre V1

> Convention (CLAUDE.md §3.11) : toute idée hors V1 se note ici, ne s'implémente pas tant que la V1 n'est pas livrée.

## V1.1 / V2 (déjà identifiées dans PLAN.md §3)
- Blog (V1.1)
- Choix date d'expédition par client, codes promo, newsletter, recherche produits, import/export CSV, rapprochement Stripe, rapports détaillés, remboursement partiel, rôles admin différenciés, couleurs/polices modifiables en BO, étiquettes Chronopost par API, plafonds production, filtres allergènes, FAQ contextuelle, GA4 purchase pour virements (V2)

## Ajouts discutés pendant le cadrage V1

### QR code de traçabilité produit
- **Proposé le** : 2026-10-05, par le client, pendant la revue des maquettes.
- **Idée** : QR code imprimé sur l'étiquette de chaque produit, renvoyant vers une page publique (`/tracabilite/{token}`) affichant allergènes, composition, date de fabrication, origine des matières premières.
- **Décision** : hors V1, reporté en V1.1/V2 pour ne pas alourdir le planning déjà tendu (10 jours réalistes sur une cible d'1 semaine).
- **Piste technique** (à affiner le moment venu) : package Laravel `simplesoftwareio/simple-qrcode`, génération à la publication du produit, page de traçabilité publique distincte de la fiche produit boutique (pas d'allergènes dupliqués : réutiliser `products.allergens_*` et `products.composition`).
