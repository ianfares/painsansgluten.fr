# T12 — Commandes : création, statuts et transitions
**Dépend de** : T02, T09, T10  **Branche** : `feature/T12-orders-core`  **Réf.** : PLAN §9

## À faire
- `CreateOrderAction` : à partir du panier + coordonnées + relais + moyen de paiement → commande `pending_payment` avec **snapshot** complet, numéro séquentiel, token aléatoire, totaux recalculés serveur, horodatage CGV.
- `OrderStateMachine` (ou service équivalent) : transitions PLAN §9.2, historique `order_status_histories`, événements Laravel par transition (`OrderPaid`, `OrderShipped`, `OrderCancelled`, `OrderRefunded`…).
- Effets déclenchés **une seule fois** (listeners idempotents, en queue).
- Verrouillage (`lockForUpdate`) lors des transitions.

## ⚠️ Avertissements
- Aucune écriture directe de `status` hors de ce service (vérifier par recherche dans le code).

## Tests
Toutes les transitions autorisées ; toutes les interdites rejetées ; historique ; recalcul serveur (montant falsifié ignoré) ; numéro séquentiel.
