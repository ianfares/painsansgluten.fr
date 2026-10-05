# T20 — Pages, FAQ, redirections
**Dépend de** : T02, T06  **Branche** : `feature/T20-content`  **Réf.** : PLAN §16, §23

## À faire
- Ressource Filament **Pages** + route publique par slug (pages légales, contact).
- Ressource **FAQ** + page `/faq` (groupes, accordéon, JSON-LD `FAQPage`).
- Ressource **Redirections** + middleware 301 (règle générique `/products/{slug}` → `/produit/{slug}`, query string ignorée).
- Contenu riche purifié avant affichage.

## ⚠️ Avertissements
- Aucun texte juridique rédigé par l'IA : placeholders uniquement.

## Tests
Page publiée/non publiée ; chaque redirection Shopify de PLAN §23 renvoie 301 vers la bonne cible.
