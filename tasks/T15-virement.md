# T15 — Paiement par virement
**Dépend de** : T12, T13  **Branche** : `feature/T15-bank-transfer`  **Réf.** : PLAN §11

## À faire
- Confirmation + email : montant, titulaire, IBAN, BIC, **référence = n° de commande**, échéance, avertissement (fabrication après réception, virement instantané conseillé).
- Action BO « Valider le virement reçu » : modale avec montant et référence attendus + avertissement de vérification → `paid`, recalcul de la date d'expédition, facture, email.
- Tâche planifiée horaire : relance à mi-délai (une seule fois) ; annulation à échéance (si option active) + email.
- Filtre BO « Virements en attente ».

## Tests
Création ; validation ; recalcul de date ; relance unique ; annulation à échéance ; option désactivée = pas d'annulation ; validation impossible si déjà `paid`/`cancelled`.
