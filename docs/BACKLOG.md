# BACKLOG.md — Idées hors périmètre V1

> Convention (CLAUDE.md §3.11) : toute idée hors V1 se note ici, ne s'implémente pas tant que la V1 n'est pas livrée.

## V1.1 / V2 (déjà identifiées dans PLAN.md §3)
- Blog (V1.1)
- Choix date d'expédition par client, codes promo, newsletter, recherche produits, import/export CSV, rapprochement Stripe, rapports détaillés, remboursement partiel, rôles admin différenciés, couleurs/polices modifiables en BO, étiquettes Chronopost par API, plafonds production, filtres allergènes, FAQ contextuelle, GA4 purchase pour virements (V2)
- **2FA (double authentification) pour les comptes administrateurs back-office** — retiré du périmètre V1 par décision client le 2026-10-06 (initialement "obligatoire" dans PLAN.md/QUALITE.md). Voir `docs/DECISIONS.md`. En V1 : connexion admin simple (email + mot de passe), guard séparé, rate limiting.

## Ajouts discutés pendant le cadrage V1

### QR code de traçabilité produit
- **Proposé le** : 2026-10-05, par le client, pendant la revue des maquettes.
- **Idée** : QR code imprimé sur l'étiquette de chaque produit, renvoyant vers une page publique (`/tracabilite/{token}`) affichant allergènes, composition, date de fabrication, origine des matières premières.
- **Décision** : hors V1, reporté en V1.1/V2 pour ne pas alourdir le planning déjà tendu (10 jours réalistes sur une cible d'1 semaine).
- **Piste technique** (à affiner le moment venu) : package Laravel `simplesoftwareio/simple-qrcode`, génération à la publication du produit, page de traçabilité publique distincte de la fiche produit boutique (pas d'allergènes dupliqués : réutiliser `products.allergens_*` et `products.composition`).

## Lot B « Espace professionnels » : B2 à B4 (V2) — T26

- **Décision** : B1 (formulaire de demande) livré en T26. B2 à B4 reportés en V2 ; ne rien implémenter avant validation écrite de Ian (voir `tasks/T26-modifications-09-10-2026.md`, Lot B).
- **B2 — Catégories de clients et comptes pro** : table `customer_groups` (nom, remise en %, actif), `users.customer_group_id` (défaut Particulier, 0 %) ; l'approbation d'une demande crée le compte (ou rattache l'email existant), affecte une catégorie et envoie un email d'invitation (lien signé, 72 h) ; seul l'admin affecte la catégorie.
- **B3 — Prix différenciés** : prix pro = prix public − remise de la catégorie (arrondi au centime, règle à documenter), calcul exclusivement serveur (panier, tunnel, Stripe, facture), visible seulement après connexion d'un compte pro validé ; aucune page avec prix pro en cache partagé (`Cache-Control: private`) ; recalcul si la catégorie change pendant un panier ouvert.
- **B4 — Facturation et commandes pro** : factures avec raison sociale, SIRET, TVA intracommunautaire, adresse de l'établissement ; facturation électronique B2B (réforme française, calendrier 2026-2027) à valider avec l'expert-comptable avant d'ouvrir les ventes pro en ligne.
- **Purge des demandes pro refusées** (conservation proposée : 12 mois, `À CONFIRMER`) : commande planifiée non implémentée en B1.
- **Points `À TRANCHER` (réponses de Ian attendues)** :
  1. Découpage : B1 maintenant, B2-B4 en V2 ?
  2. Remise : un % par catégorie (simple) ou prix spécifiques par produit et par catégorie (plus fin, plus lourd) ?
  3. Affichage des prix pour les pros : HT ou TTC ?
  4. Livraison des pros : Chronopost relais comme les particuliers, livraison à l'adresse de l'établissement, ou livraison/retrait organisé par Angélique ?
  5. Paiement des pros : Stripe/virement à la commande comme les particuliers, ou paiement différé (facture à 30 jours) ?
  6. Minimum de commande pour les pros ?
  7. Les pros commandent-ils les mêmes produits que les particuliers, ou un catalogue dédié (formats pro, pâte à pizza crue) ?
  8. Faut-il masquer certains produits aux particuliers (produits réservés aux pros) ?
