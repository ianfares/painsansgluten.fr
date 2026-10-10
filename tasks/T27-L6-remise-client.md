# T27-L6 — Remise client en % (modèle : Opus 5.5)

Branche : `feature/T27-L6-remise-client`. Dépend de L5. Lire `tasks/T27-README.md`. **Partie critique (prix, TVA, paiement, factures).**

## Règles métier (validées)
- Champ `discount_percent` sur `users` (entier, défaut 0, validation admin `between:0,90`). Réglé dans la fiche client admin (L5). Tout type de compte. Pour un pro en attente, la remise ne s'applique pas tant que le compte n'est pas validé.
- S'applique **aux produits uniquement**, jamais aux frais de port.
- **Affichage partout quand le client est connecté** : catalogue, fiches, panier, tunnel → prix public barré + prix remisé + mention « Votre remise : -10 % ». Visiteur non connecté : prix public. ⚠ Cache de pages publiques éventuel : vérifier qu'aucune page avec prix remisé n'est mise en cache partagé.
- Le serveur recalcule tout (CLAUDE.md §3.8) : la remise vient **toujours** du compte en base, jamais du navigateur.
- Calcul en centimes, par ligne : `line_discount = round(line_total_ttc × pct / 100)` (arrondi au centime le plus proche, demi vers le haut), `line_total_ttc_net = line_total_ttc − line_discount` ; HT et TVA recalculés **sur le montant remisé** avec la logique existante. Snapshot sur `order_items` (prix unitaire public, % appliqué, remise de la ligne) et sur `orders` (`discount_percent`, `discount_total_ttc`) : une commande garde sa remise même si le % change ensuite.
- Stripe Checkout : les line items sont envoyés au prix remisé (pas de coupon Stripe) ; `amount_total` doit toujours égaler le total commande (contrôle existant).
- Facture/avoir PDF : prix unitaire public, ligne « Remise client -10 % » par ligne ou en sous-total, totaux HT/TVA/TTC après remise. Avoir = miroir. **Format à faire valider par le comptable du client** (noter dans DECISIONS).
- Emails de confirmation : montrer la remise.
- Commande manuelle (L10) : même calcul via le même service.

## Implémentation attendue
- Un seul service de prix (ex. `App\Services\Pricing\CustomerPricing` ou extension de `CartService`) utilisé par le panier, le tunnel, `CreateOrderAction`, Stripe, les vues. Pas de calcul dans les vues ni Livewire.
- Toutes les vues prix passent par un composant Blade `<x-ui.price :product :user>`.

## Tests
Calcul (arrondis, 0 %, > 90 % refusé, cumul plusieurs lignes), livraison non remisée, pro en attente sans remise, catalogue connecté/non connecté, Stripe line items = total, facture avec remise, snapshot figé après changement du %, falsification (paramètre `discount` envoyé par le navigateur ignoré).
