# T27-L9 — Tableau « À produire » + export PDF (modèle : Sonnet 5.5)

Branche : `feature/T27-L9-a-produire`. Dépend de L7b. Lire `tasks/T27-README.md`.

## À faire
- Nouvelle page Filament « À produire » (groupe Commandes, aussi lien/widget compact sur le tableau de bord principal).
- Commandes concernées : statut **payé** et **en préparation** (à produire), pas encore expédiées/prêtes.
- Filtres : **date d'expédition prévue du … au …** (défaut : aujourd'hui → +7 jours), **catégorie** (multi), **produit** (multi, recherche par nom), **mode de livraison** (L7b).
- Deux vues sur la même page :
  1. **Total à produire par produit** (produit, catégorie, quantité totale), trié par catégorie puis nom ;
  2. **Détail par commande** (date d'expédition prévue, n° commande, client, mode de livraison, produit, quantité), triable par chaque colonne.
- **Export PDF** (dompdf) des deux vues avec les filtres appliqués rappelés en en-tête ; aucun prix.
- Logique dans un service `App\Services\Reporting\ProductionPlan` testable (pas de requête dans la page). Requêtes sans N+1.

## Tests
Filtres (dates, catégorie, produit, mode), exclusion des statuts non concernés, totaux, export PDF réservé admin, `LivewireRoundTripTest` : ajouter la page.
