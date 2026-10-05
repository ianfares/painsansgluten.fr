# T18 — Espace client
**Dépend de** : T03, T12, T16  **Branche** : `feature/T18-customer-account`  **Réf.** : PLAN §13

## À faire
- `/mon-compte` : tableau de bord, commandes (+ détail, suivi, date d'expédition), factures/avoirs, informations, adresse de facturation, mot de passe, demande de suppression, déconnexion.
- Changement d'email → revérification.
- Rattachement des commandes invité (même email) **après vérification de l'email**.
- Demande de suppression → `deletion_requested_at` + email admin.

## Tests
Accès à la commande / facture d'un autre client refusé ; rattachement uniquement si email vérifié ; changement de mot de passe avec ancien mot de passe requis.
