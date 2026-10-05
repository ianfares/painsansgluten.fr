# CHECKLIST-PROD.md — Recette et mise en production

> À dérouler en **préprod** (Stripe en mode test), puis les sections 3 à 5 avant l'ouverture des ventes.

## 1. Recette fonctionnelle

### Parcours client
- [ ] Accueil, boutique, 4 catégories, fiche produit : affichage desktop + mobile
- [ ] Produit indisponible : pas de bouton ajouter
- [ ] Produit non expédiable : visible, message, non commandable
- [ ] Panier : ajout, quantité, suppression, tiroir + page, date d'expédition affichée
- [ ] Frais de port : chaque tranche, franco
- [ ] Tunnel invité complet
- [ ] Tunnel avec création de compte
- [ ] Tunnel connecté
- [ ] Code postal Corse refusé
- [ ] Relais : recherche, sélection, WS indisponible (message propre)
- [ ] CGV non cochée → blocage
- [ ] Stripe : paiement réussi (carte `4242…`)
- [ ] Stripe : paiement refusé, 3D Secure, abandon (retour sans payer → reprise possible)
- [ ] Stripe : session expirée → commande annulée
- [ ] Confirmation : « paiement en cours » puis confirmé
- [ ] Virement : instructions + avertissement affichés et reçus par email
- [ ] Email de confirmation reçu, facture PDF ouverte et correcte
- [ ] Espace client : commandes, suivi, factures, informations, mot de passe, demande de suppression
- [ ] Commande invité rattachée au compte créé ensuite (après vérification de l'email)
- [ ] Mot de passe oublié

### Parcours administrateur
- [ ] Connexion admin (2FA reporté en V2, décision client 2026-10-06 — voir docs/DECISIONS.md)
- [ ] Créer un produit complet, le dupliquer, le publier, le désactiver
- [ ] Publication refusée si champs obligatoires vides
- [ ] Valider un virement (modale de vérification) → date recalculée, facture, email
- [ ] Relance puis annulation automatique d'un virement (délai court en préprod)
- [ ] Passer en préparation → expédier (n° de suivi obligatoire) → email client avec lien de suivi
- [ ] Marquer livrée
- [ ] Rembourser (Stripe test) → avoir + email
- [ ] Vue Expéditions : tri par date, retards visibles
- [ ] Export comptable CSV ouvert dans Excel sans problème d'accents
- [ ] Pages, FAQ, accueil, bandeau modifiés depuis le BO et visibles en front

### Robustesse paiement
- [ ] Webhook rejoué 2 fois (`stripe events resend`) → un seul traitement
- [ ] Webhook avec signature invalide → 400
- [ ] Montant incohérent (test) → anomalie + email admin
- [ ] Double-clic sur « Payer » → une seule commande

## 2. Technique
- [ ] `php artisan test` vert, `pint --test`, `phpstan` OK
- [ ] `composer audit` / `npm audit` sans faille critique
- [ ] Lighthouse mobile : Performance ≥ 85, Accessibilité ≥ 90, SEO ≥ 95 (accueil, catégorie, fiche)
- [ ] Aucun cookie `_ga` avant consentement (DevTools → Application → Cookies)
- [ ] GTM Preview : événements e-commerce présents, `purchase` unique
- [ ] Sitemap valide, robots préprod = `Disallow`, `X-Robots-Tag: noindex` en préprod
- [ ] 301 Shopify : tester chaque ligne de PLAN §23 (`curl -sI https://…/collections/all`)
- [ ] Accès à la commande / facture d'un autre client → refusé
- [ ] Aucune donnée personnelle dans `storage/logs`
- [ ] `APP_DEBUG=false`, `APP_ENV=production`

## 3. Configuration avant ouverture des ventes
- [ ] Paramètres d'expédition renseignés (jours, heure limite, délai, jours fermés)
- [ ] Grille de frais de port + TVA du port renseignées
- [ ] Taux de TVA produits **validés par l'expert-comptable**
- [ ] Mentions vendeur facture + formats de numérotation **validés par le comptable**
- [ ] IBAN / BIC / titulaire virement
- [ ] Délai d'annulation virement ajusté
- [ ] **CGV, mentions légales, confidentialité, remboursement, livraison remplies** (cliente / juriste)
- [ ] Produits saisis, photos, alt, SEO
- [ ] Coordonnées boulangerie (pied de page + schema.org)

## 4. Infrastructure (Ian)
- [ ] Nginx + PHP-FPM + MariaDB + Supervisor (queue) + cron scheduler actifs
- [ ] Cloudflare : proxy, SSL Full (strict), cache assets, **`/webhooks/stripe` exclu du WAF/challenge**
- [ ] Webhook Stripe **live** créé, secret en `.env`, événements cochés (PLAN §10)
- [ ] Clés Stripe **live** en prod uniquement
- [ ] Brevo : domaine validé, SPF / DKIM / DMARC OK, email test reçu en boîte de réception (pas en spam)
- [ ] Identifiants Chronopost prod
- [ ] Sauvegardes : BDD + `storage/app` (images + **factures PDF**) + `.env`, hors serveur
- [ ] **Restauration testée**
- [ ] Supervision : URL publique, `/up`, queue, disque, logs
- [ ] Search Console : propriété validée, sitemap soumis

## 5. Jour J — bascule
- [ ] Sauvegarde complète juste avant
- [ ] TTL DNS abaissé la veille
- [ ] Bascule DNS vers le VPS
- [ ] Commande réelle de bout en bout (petit montant, remboursée ensuite)
- [ ] Vérification des 301 en prod
- [ ] Suivi des 404 et des erreurs pendant 7 jours, Search Console pendant 30 jours
- [ ] Shopify conservé jusqu'à stabilisation, puis résiliation
