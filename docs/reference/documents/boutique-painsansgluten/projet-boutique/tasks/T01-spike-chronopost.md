# T01 — Spike : validation des identifiants et du WS points relais Chronopost
**Dépend de** : T00  **Branche** : `feature/T01-chronopost-spike`  **Réf.** : PLAN §8.1

## Objectif
Prouver **dès J1** que les identifiants du contrat fonctionnent et que la recherche de points relais renvoie des données exploitables.

## À faire
- Identifier dans la documentation Chronopost (fournie avec le contrat / espace développeur) le **web service officiel de recherche de points relais** et son format (SOAP probable). **Ne pas deviner** les noms de méthodes : s'appuyer sur la doc/WSDL.
- Créer `app/Services/Chronopost/RelayPointClient.php` : méthode `search(string $postalCode, ?string $city): array<RelayPoint>` (DTO : id, nom, adresse, CP, ville, distance, horaires).
- Timeout 5 s, gestion d'erreurs, log sans données personnelles.
- Commande `php artisan chronopost:test-relays {cp}` qui affiche les résultats.

## ⚠️ Avertissements
- Si les identifiants ou le WS posent problème : **s'arrêter et remonter l'info à Ian immédiatement** (bloquant pour tout le tunnel).
- Identifiants uniquement dans `.env`.

## Tests
- Test unitaire avec réponse WS simulée (fixture) : parsing correct, réponse vide, timeout, erreur.

## Critères d'acceptation
- `php artisan chronopost:test-relays 50300` renvoie une liste réelle de relais (avec de vrais identifiants en local).
- Compte rendu : méthode WS utilisée, champs disponibles, limites constatées.
