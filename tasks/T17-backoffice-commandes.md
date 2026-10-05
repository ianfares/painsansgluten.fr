# T17 — Back-office : commandes, expéditions, clients, tableau de bord
**Dépend de** : T12, T14, T15, T16  **Branche** : `feature/T17-admin-orders`  **Réf.** : PLAN §14

## À faire
- Ressource **Commandes** : liste (n°, date, client, total, paiement, statut, date d'expédition prévue), recherche, filtres, détail complet (lignes, relais, client, paiement, historique, facture), actions de transition (PLAN §14) avec confirmations.
- Action « Expédier » : n° de suivi obligatoire → `shipped`.
- Vue **Expéditions** : commandes `paid`/`preparing` triées par date d'expédition, mise en évidence aujourd'hui / en retard.
- Ressource **Clients** : recherche, détail, commandes, adresse ; jamais de mot de passe.
- **Tableau de bord** : CA TTC du mois, nb de commandes du mois, virements en attente, commandes à expédier aujourd'hui / en retard, alertes de configuration incomplète.
- Ressource **Administrateurs**.

## Tests
Actions visibles uniquement selon le statut ; expédition sans n° refusée ; remboursement appelle Stripe (mock).
