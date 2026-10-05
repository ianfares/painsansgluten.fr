# T11 — Sélection du point relais Chronopost
**Dépend de** : T01, T06  **Branche** : `feature/T11-relay-picker`  **Réf.** : PLAN §8.1

## À faire
- Composant Livewire : saisie code postal (+ ville), appel `RelayPointClient`, liste des relais (nom, adresse, distance, horaires), sélection.
- Refus des codes postaux Corse (20xxx) et hors métropole avec message clair.
- Cache des résultats par code postal (1 h).
- WS indisponible : message « Service de points relais momentanément indisponible, réessayez dans quelques instants » + log.

## Tests
CP valide (fixture), Corse refusée, CP vide/invalide, WS en timeout.

## Critères d'acceptation
- Utilisable confortablement sur mobile.
