# T06 — Design système et layout public
**Dépend de** : T00  **Branche** : `feature/T06-design`  **Réf.** : PLAN §5.1

## Objectif
Reproduire le **rendu visuel** de https://painsansgluten.fr sans réutiliser le code Shopify.

## À faire
- Relever sur le site public : couleurs, polices, tailles, espacements, rayons, ombres → variables uniques (thème Tailwind + CSS custom properties).
- Polices : auto-hébergées (pas de Google Fonts en CDN : RGPD + perf).
- Layout : bandeau d'annonce, en-tête (logo centré, menu, méga-menu catégories avec visuels, icônes compte/panier avec compteur), pied de page (liens pages, Facebook, gestion cookies, copyright).
- Composants Blade : bouton, carte produit, badge (épuisé / indisponible), fil d'Ariane, alertes, champs de formulaire, tiroir latéral, modale.
- Menu mobile.
- Page 404.

## ⚠️ Avertissements
- Aucun code Liquid/CSS/JS du thème Shopify.
- Accessibilité de base : contrastes, focus visibles, labels, `alt`.

## Critères d'acceptation
- Captures desktop + mobile comparées côte à côte avec le site actuel, validées par Ian.
