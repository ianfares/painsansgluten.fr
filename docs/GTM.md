# Google Tag Manager et Google Analytics 4 : guide pour Ian

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

## 4. Événements e-commerce (plus tard)

Les événements `view_item`, `add_to_cart`, `purchase`… arriveront dans la couche de données (`dataLayer`) à une étape ultérieure de la tâche T22. Il faudra alors créer, pour chacun, un déclencheur « Événement personnalisé » et une balise « Événement Google Analytics 4 » rattachée à `G-XF6EHRRV48`. Ce guide sera complété à ce moment-là.

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
