# T13 — Tunnel de commande
**Dépend de** : T08, T11, T12, T03  **Branche** : `feature/T13-checkout`  **Réf.** : PLAN §9.1

## À faire
- Étapes : Coordonnées (invité ou connexion/inscription) → Relais → Récapitulatif → Paiement.
- Champs : email, prénom, nom, téléphone mobile (obligatoire, format FR), adresse de facturation (pré-remplie si compte).
- Récap : lignes, sous-total, port, total, **date d'expédition prévue**, case CGV obligatoire non cochée + rappel exception rétractation (textes paramétrables), choix Carte / Virement.
- Blocage si configuration incomplète (port, expédition) : message client neutre + alerte admin.
- Bouton « Payer » → `CreateOrderAction` → Stripe (T14) ou confirmation virement (T15).
- Page `/commande/confirmation/{token}` (contenu selon moyen et statut).
- Après commande invité : bloc « Créer mon compte ».
- Événements dataLayer `begin_checkout`, `add_shipping_info`, `add_payment_info` (branchés en T22).

## Tests
Invité, connecté, CGV non cochée, relais manquant, Corse, config incomplète, double-clic sur Payer (une seule commande).
