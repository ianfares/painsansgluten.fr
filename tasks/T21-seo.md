# T21 — SEO technique
**Dépend de** : T07, T20  **Branche** : `feature/T21-seo`  **Réf.** : PLAN §17

## À faire
- Composant meta unique : title, description (repli auto), canonical, OG, Twitter.
- JSON-LD : `Product`/`Offer`, `BreadcrumbList`, `Organization` + `Bakery`, `FAQPage`.
- `sitemap.xml` (spatie) : génération quotidienne + à la sauvegarde d'un produit/page.
- `robots.txt` dynamique selon l'environnement ; `X-Robots-Tag: noindex` hors prod ; `noindex` sur panier, commande, compte.
- Audit : un seul H1 par page, `alt`, dimensions d'images.

## Tests
Sitemap valide et sans produits non publiés ; robots préprod = Disallow ; JSON-LD produit valide (structure).

## Critères d'acceptation
- Test des résultats enrichis Google et Lighthouse SEO ≥ 95 sur accueil, catégorie, fiche.
