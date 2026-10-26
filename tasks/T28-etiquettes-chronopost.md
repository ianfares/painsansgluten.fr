# T28 — Étiquettes Chronopost depuis le back-office
**Dépend de** : T11  **Branche** : `feature/T28-chronopost-labels`  **Réf.** : PLAN §8.2, docs/DECISIONS.md (26/10/2026)

## Contexte
Chronopost ne fournit les identifiants de prod qu'après validation des requêtes **et des étiquettes de test**. Décision de Ian (26/10/2026) : l'étiquetage par API passe en V1.

## Fait
- `shippingMultiParcelV4` : un colis par commande, Chrono Relais 13H (code 86 réglé en BO), **service 0** (expédition jeudi, livraison vendredi), **PDF A4**, **poids seul** (carton variable), **sans assurance** ad valorem.
- Destinataire = relais (nom, adresse) + nom du client en `recipientName2`, portable et email du client, `idRelais`. Expéditeur = Paramètres > Boutique.
- `cancelListSkybill` : annulation (PDF conservé, nouvelle étiquette possible).
- BO (liste + fiche commande) : « Générer l'étiquette Chronopost », « Étiquette Chronopost (PDF) », « Annuler l'étiquette ». Le statut ne change pas ; « Expédier » est pré-rempli avec le n° de suivi.
- Refus : commande non payée / expédiée, retrait, relais saisi à la main (`MANUEL-…`), Chronopost désactivé, coordonnées boutique incomplètes.

## Tests
Création, données envoyées, idempotence (double clic), refus (statuts, retrait, relais manuel, Chronopost désactivé, boutique incomplète), refus et panne Chronopost, annulation (OK, refusée après expédition, refus Chronopost), actions BO.

## Critères d'acceptation
- Étiquette réelle générée avec le compte de test (fait le 26/10/2026 : LT XS496898265FR, puis annulée).
- Requêtes + étiquettes de test envoyées à Chronopost pour validation → identifiants de prod.
