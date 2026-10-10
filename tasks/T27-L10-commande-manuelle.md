# T27-L10 — Commande manuelle dans l'admin (modèle : Opus 5.5)

Branche : `feature/T27-L10-commande-manuelle`. Dépend de L5, L6, L7b. Lire `tasks/T27-README.md`. **Partie critique (commande, paiement, factures).**

## Règles métier (validées)
- Bouton « Nouvelle commande » dans `OrderResource` : choix du **client existant** (recherche nom/email ; création client = L5), produits + quantités, mode de livraison (Chronopost avec saisie manuelle du point relais comme aujourd'hui / commerçant / labo selon droits du client), moyen de paiement prévu (carte ou virement).
- Calcul **identique au tunnel** : réutiliser `CreateOrderAction` et le service de prix (remise du compte L6, frais de port normaux, date d'expédition). Aucun montant saisi à la main.
- Commande créée en **attente de paiement** → le client reçoit l'**email de confirmation** avec :
  - carte : lien de paiement Stripe Checkout (réutiliser la relance de paiement existante, session dédiée, webhook = seule preuve de paiement) ;
  - virement : instructions habituelles (mail existant `BankTransferInstructionsMail`).
- Action admin **« Valider sans paiement (avoir) »** sur une commande en attente : confirmation obligatoire + champ obligatoire « Référence / motif de l'avoir » ; la commande passe **payée** via `OrderStateMachine` (jamais en écrivant `status`), un enregistrement `Payment` de méthode `credit_note_settlement` (nouvelle valeur d'enum `PaymentMethod`, libellé « Réglé par avoir ») est créé avec la référence, la facture est émise normalement et porte la mention « Réglé par avoir : {référence} ». Historique de statut avec l'admin auteur. **Mention à faire valider par le comptable du client.**
- Commande manuelle marquée `created_by_admin_id` (traçabilité).

## Tests
Création (calcul identique au tunnel, remise appliquée, livraison non remisée), email de confirmation carte (lien) / virement, paiement Stripe via webhook, validation par avoir (statut, Payment, facture + mention, motif obligatoire, refus si déjà payée), réservé admin, `LivewireRoundTripTest` : ajouter `/admin/orders/create`.
