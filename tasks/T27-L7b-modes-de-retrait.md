# T27-L7b — Modes de retrait dans la commande (modèle : Opus 5.5)

Branche : `feature/T27-L7b-modes-de-retrait`. Dépend de L5 et L7a. Lire `tasks/T27-README.md`. **Partie critique (commande, frais de port, statuts).**

## Règles métier (validées)
Trois modes de livraison, choisis à l'étape livraison du tunnel (`app/Livewire/CheckoutWizard.php`) :
1. **Chronopost Relais** (existant, payant, inchangé).
2. **Retrait chez un commerçant partenaire** : gratuit. Liste = points actifs géocodés à ≤ `pickup_max_distance_km` (50) **de l'adresse saisie par le client** (adresse de facturation du tunnel), à vol d'oiseau, triés par distance (distance affichée). Adresse client non géocodable (ou BAN injoignable) → option masquée, Chronopost seul, aucune erreur bloquante.
3. **Retrait au laboratoire** : gratuit, proposé **uniquement** si client connecté `isApprovedPro()` ET `lab_pickup_allowed`. Adresse = adresse boutique (`ShopSettings`) + `shipping.lab_pickup_instructions`.
- Date de préparation/retrait = même calcul que la date d'expédition Chronopost.
- Le serveur revalide tout à la création de commande (distance recalculée, droit labo revérifié, frais = 0 uniquement pour ces modes).
- Produits « non expédiables » : vérifier la règle existante ; en retrait (commerçant/labo) ils deviennent commandables ? → **Ne pas décider : garder la règle actuelle et le signaler.**

## Données
- `orders.delivery_method` (enum `DeliveryMethod` : `chronopost_relay`, `merchant_pickup`, `lab_pickup` ; migration avec défaut `chronopost_relay` pour l'existant). Colonnes `relay_*` existantes rendues nullables ou réutilisées pour le snapshot du point (nom, adresse, horaires) — choisir la voie la plus simple et documenter.
- Statuts : ajouter `ReadyForPickup` (« Prête au retrait ») et `PickedUp` (« Retirée ») dans `app/Enums/OrderStatus.php` + transitions dans `app/Services/Orders/OrderStateMachine.php` : `paid|preparing → ready_for_pickup → picked_up` (uniquement pour les modes retrait ; `shipped` uniquement pour Chronopost). Remboursement/annulation : mêmes règles que les statuts équivalents.
- Admin `OrderResource` : mode de livraison affiché + filtre ; actions « Prête au retrait » (email au client : adresse, horaires, instructions) et « Retirée ».
- Emails, page de confirmation, espace client, facture : afficher le mode et le point de retrait. Stripe : ligne de livraison absente ou à 0 € pour les retraits.

## Tests
Les 3 modes (proposés / masqués selon distance, connexion, droit labo), géocodage en échec → Chronopost seul, falsification (point hors rayon ou labo non autorisé envoyé par le navigateur → refus), frais 0, transitions de statut et emails, non-régression Chronopost (tests existants verts).
