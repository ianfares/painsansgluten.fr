# T05 — Catalogue dans le back-office
**Dépend de** : T02, T04  **Branche** : `feature/T05-catalog-admin`  **Réf.** : PLAN §6

## À faire
- Ressource Filament **Catégories** (tri par glisser-déposer).
- Ressource Filament **Produits** avec onglets : Général, Descriptions, Composition, Nutrition, Conditionnement, Conseils, Images, SEO (PLAN §6.2).
- Allergènes : 2 groupes de 14 cases (contient / traces).
- Slug auto depuis le nom, éditable, unique.
- Images via medialibrary : principale + galerie ordonnable, alt obligatoire, conversions WebP (miniature, liste, fiche, zoom).
- Publication bloquée si champs obligatoires vides (brouillon autorisé).
- Action **Dupliquer** ; bascule rapide disponible/indisponible dans la liste ; filtres catégorie / dispo / publié.
- Suppression interdite si le produit est dans une commande (désactivation à la place).
- Prix saisi en euros TTC dans le formulaire, stocké en centimes.

## Tests
- Création, duplication, publication bloquée, suppression refusée si commandé, conversion d'image générée.

## Critères d'acceptation
- La cliente peut créer une fiche complète équivalente au « Mie'miam » actuel en moins de 5 minutes.
