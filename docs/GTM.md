# Google Tag Manager et Google Analytics 4 : guide pour Ian

> **État au 2026-10-12** : balise `GA4 - Configuration` (Balise Google `G-XF6EHRRV48`, déclencheur Initialization - All Pages, « aucun consentement supplémentaire ») créée et **publiée** (version « GA4 + consentement »), testée en Aperçu sur la préprod (option `GTM_ENABLED_OUTSIDE_PRODUCTION`, remise à `false` après le test). Reste : filtre « Trafic interne » GA4 à passer en Actif, événements e-commerce (§4), test final en prod (§5).
>
> **Mode Aperçu** : GTM n'est chargé qu'après « Tout accepter » ; accepter d'abord, puis relancer l'Aperçu. Désactiver tout bloqueur de pub (il bloque googletagmanager.com et Tag Assistant ne se connecte pas).

Ce que fait déjà le site (rien à coder) :

- Le site charge **Google Tag Manager (GTM)** uniquement en **production**, et **seulement si le visiteur accepte** les cookies.
- Avant tout choix, le site déclare à Google : « stockage analytique **refusé** » (Consent Mode v2). Si le visiteur accepte, le site passe `analytics_storage` à `granted` ; s'il refuse ou change d'avis, à `denied`.
- Le site ne contient **aucun code GA4** : tout se règle dans GTM, décrit ci-dessous.
- Identifiants : conteneur GTM `GTM-KFK55VB8`, mesure GA4 `G-XF6EHRRV48` (flux `painsansgluten.fr`).

## 1. Mettre l'identifiant GTM en production

Dans le fichier `.env` du serveur de **production** uniquement :

```
GTM_ID=GTM-KFK55VB8
```

Puis `php artisan config:clear` (ou `config:cache`). En local et en préprod, ne rien mettre : rien n'est chargé et le bouton « Gérer mes préférences » est masqué.

## 2. Créer la balise Google Analytics 4 dans GTM

1. Ouvrir https://tagmanager.google.com, conteneur **GTM-KFK55VB8**.
2. Aller dans **Balises > Nouvelle**.
3. Nom : `GA4 - Configuration`.
4. Type de balise : **Balise Google** (« Google tag »).
5. **ID de la balise** : `G-XF6EHRRV48`.
6. **Déclenchement** : choisir **Initialization - All Pages** (« Initialisation - Toutes les pages »). C'est le déclencheur recommandé par Google pour cette balise.
7. Enregistrer.

## 3. Activer les contrôles de consentement

Dans GTM : **Administration > Paramètres du conteneur** et cocher **Activer les paramètres de consentement** (« Enable consent overview »).

Pour la balise `GA4 - Configuration` : **Paramètres avancés > Paramètres de consentement** :

- La balise Google gère le consentement toute seule (elle lit `analytics_storage`). Choisir **Aucun consentement supplémentaire requis** est correct pour cette balise.
- Pour toute autre balise ajoutée plus tard (publicité, pixel…), choisir **Exiger un consentement supplémentaire** et cocher le type voulu (`ad_storage` pour la publicité).

## 4. Événements e-commerce

Le site pousse dans le `dataLayer`, au format GA4 standard (objet `ecommerce`, montants en euros TTC, remise client déduite, `item_id` = référence produit) :

| Événement | Quand |
|---|---|
| `view_item_list` | page boutique / catégorie |
| `view_item` | fiche produit |
| `add_to_cart` / `remove_from_cart` | bouton « Ajouter », changement de quantité, suppression |
| `view_cart` | page `/panier` |
| `begin_checkout` | ouverture du tunnel |
| `add_shipping_info` | validation du mode de livraison (`shipping_tier`) |
| `add_payment_info` | clic « Payer » (`payment_type`) |
| `purchase` | page de confirmation, **carte uniquement**, quand la commande est payée en BDD, **une seule fois** (`ga_purchase_sent`) ; pas de `purchase` pour les virements (V1) |

Dans GTM, une seule balise suffit pour tous :

1. **Déclencheurs > Nouveau** : type **Événement personnalisé**, nom de l'événement `view_item_list|view_item|add_to_cart|remove_from_cart|view_cart|begin_checkout|add_shipping_info|add_payment_info|purchase`, cocher **Utiliser la correspondance d'expressions régulières**. Nom : `E-commerce - tous`.
2. **Balises > Nouvelle** : type **Google Analytics : Événement GA4**, ID de mesure `G-XF6EHRRV48`, nom de l'événement `{{Event}}`, **Plus de paramètres > Envoyer des données d'e-commerce** coché, source **Data Layer**. Déclencheur : `E-commerce - tous`. Nom : `GA4 - E-commerce`.
3. Dans GA4 : **Administration > Événements clés** : marquer `purchase` comme événement clé (souvent déjà fait automatiquement).

## 5. Tester avant de publier

1. Dans GTM, cliquer sur **Aperçu** (Preview), saisir l'adresse du site de production.
2. Sur le site, **ne rien accepter** : dans l'aperçu, la balise `GA4 - Configuration` ne doit **pas** se déclencher, et aucun cookie `_ga` n'apparaît (DevTools > Application > Cookies).
3. Cliquer sur **Tout accepter** : GTM se charge, la balise se déclenche, les cookies `_ga` apparaissent.
4. Dans le pied de page, **Gérer mes préférences** > refuser : le consentement repasse à `denied`.
5. Dans GA4 > **Rapports > Temps réel**, vérifier qu'une visite apparaît après acceptation.
6. Si tout est bon : **Envoyer** puis **Publier** la version du conteneur.

## 6. Dépannage rapide

| Symptôme | Cause probable |
|---|---|
| Pas de bandeau sur le site | `APP_ENV` n'est pas `production`, ou `GTM_ID` absent / mal écrit dans `.env` (cache de config non vidé) |
| Bandeau visible mais rien dans GA4 | Balise non publiée dans GTM, ou visiteur n'a pas accepté |
| Cookies `_ga` avant tout choix | Une balise GA4 en dur est apparue quelque part : prévenir le développeur (c'est interdit) |
| Bouton « Gérer mes préférences » absent | Normal hors production |
