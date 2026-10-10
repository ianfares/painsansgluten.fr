# T27-L2 — Fiche produit : TTC, poids net, prix au kilo, description en accordéon (modèle : Haiku 5.5)

Branche : `feature/T27-L2-fiche-produit`. Lire d'abord `tasks/T27-README.md`.

Fichier principal : `resources/views/catalog/product.blade.php` (~l.95-170). Champs existants du modèle `Product` : `price_ttc` (centimes), `net_weight_g` (grammes, nullable), `short_description`, `description` (HTML).

## À faire
1. **Prix** : juste sous le prix (~l.103), afficher « TTC » (petit texte, ex. `text-sm text-ink-muted`), ou dans la même ligne « 4,50 € TTC » — garder le prix bien visible.
2. **Poids net + prix au kilo**, juste en dessous : `Poids net : 500 g - 9,00 €/kg`.
   - Prix au kilo **calculé** : `intdiv(price_ttc * 1000 + intdiv(net_weight_g, 2), net_weight_g)` centimes (arrondi au centime), jamais saisi. Mettre la logique dans le modèle : `Product::pricePerKgTtc(): ?int` (null si pas de poids net ou poids = 0).
   - Format des poids : < 1000 g → « 500 g », ≥ 1000 g → « 1,2 kg » (créer un petit helper/méthode `Product::formattedNetWeight(): ?string`).
   - Si pas de poids net : rien n'est affiché (ni poids ni prix/kg).
   - Supprimer la ligne « Poids net : … g » en double dans l'accordéon (~l.160) si elle n'apporte plus rien (garder le conditionnement s'il existe).
3. **Admin** (`app/Filament/Resources/ProductResource.php`) : à côté du champ poids net, un `Placeholder` « Prix au kilo (calculé) » qui affiche la valeur ou « — ». Aucun nouveau champ en base.
4. **Descriptions** : la description courte reste visible en haut ; la description longue n'est plus un accordéon séparé plus bas : elle se déplie **juste sous la description courte** via une petite flèche (chevron) « Lire la suite », fermée par défaut. Utiliser `<details>/<summary>` (accessible, sans JS) comme le composant `resources/views/components/ui/accordion.blade.php`, avec un style discret (pas de grosse bordure). S'il n'y a pas de description courte mais une longue : afficher la longue directement. Supprimer l'accordéon « Description » existant (~l.118-122).
5. Le reste de la page (galerie, ingrédients, allergènes, expédition) ne bouge pas.

## Tests
- Unitaires : `pricePerKgTtc()` (450 c / 500 g → 900 c ; arrondi ; null sans poids) et `formattedNetWeight()`.
- Feature : la fiche affiche « TTC », « Poids net : 500 g - 9,00 €/kg », la description longue dans un `<details>` sous la courte ; sans poids net, pas de « Poids net ».
- Adapter les tests existants qui cherchaient l'accordéon « Description » (`tests/Feature/T26/`, `tests/Feature/Catalog/`).
