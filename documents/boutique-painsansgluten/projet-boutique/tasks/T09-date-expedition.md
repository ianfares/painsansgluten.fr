# T09 — Calcul de la date d'expédition
**Dépend de** : T04  **Branche** : `feature/T09-ship-date`  **Réf.** : PLAN §8.4

## À faire
- `app/Services/Shipping/ShippingDateCalculator.php` : `forInstant(CarbonImmutable $at): CarbonImmutable` selon l'algorithme PLAN §8.4.
- Lecture des paramètres (jours, heure limite, délai) + `closed_dates`.
- Exception explicite si configuration incomplète (aucun jour d'expédition, heure limite vide).
- Helper de formatage FR (« mardi 13 octobre »).

## ⚠️ Avertissements
- `Europe/Paris`, `CarbonImmutable`, aucun `now()` en dur dans le service (instant injecté → testable).
- Garde-fou anti-boucle infinie (ex. max 60 jours de recherche → exception).

## Tests (unitaires, nombreux)
Avant/après heure limite ; heure limite pile ; veille de week-end ; jours fermés consécutifs ; période fermée chevauchant un week-end ; délai 0, 1, 2 ; passage d'année ; jours de changement d'heure ; config vide → exception.

## Critères d'acceptation
- 100 % des cas listés couverts et verts.
