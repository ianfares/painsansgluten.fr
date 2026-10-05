# PLAN.md — Boutique « Mon Sans Gluten by Angélique » — Document de référence V1

> Version 1.0 — 05/10/2026 — Rédigé pour exécution par Claude Code.
> Les éléments marqués **`À RENSEIGNER`** sont des valeurs métier non connues à ce jour : ils deviennent des **paramètres back-office** ou des **placeholders**, jamais des valeurs inventées.
> Les encadrés **⚠️** sont des avertissements à respecter impérativement.
>
> **Amendement du 2026-10-06 (décision client)** : toutes les mentions « 2FA obligatoire » ci-dessous pour le back-office sont **reportées en V2**. Ne pas implémenter de 2FA en V1. Voir `docs/DECISIONS.md` et `docs/BACKLOG.md`.

---

## 0. Sommaire
1. Objectif et règle fondamentale
2. Décisions validées
3. Périmètre : V1 / V1.1 / V2
4. Architecture et stack
5. Front-office
6. Catalogue et fiche produit
7. Panier
8. Livraison : Chronopost Relais, frais de port, date d'expédition
9. Commandes : tunnel et statuts
10. Paiement Stripe
11. Paiement par virement
12. Facturation et avoirs
13. Espace client
14. Back-office
15. Emails transactionnels
16. Contenus : pages, FAQ, accueil
17. SEO
18. Analytics et consentement
19. RGPD et légal
20. Sécurité
21. Performance
22. Modèle de données
23. Migration depuis Shopify
24. Sauvegardes et exploitation
25. Tests
26. Ordre d'exécution et planning
27. Liste « À RENSEIGNER »
28. Récapitulatif des avertissements

---

## 1. Objectif et règle fondamentale

Remplacer le site Shopify https://painsansgluten.fr par une boutique sur mesure, **simple, fiable, maintenable**, hébergée sur un VPS Linux (Infomaniak Cloud, sans Plesk), derrière Cloudflare.

> **Règle fondamentale** : permettre à un client de commander simplement, et à la boulangerie de recevoir, fabriquer, facturer et expédier cette commande de manière fiable. Tout ce qui n'est pas nécessaire à cet objectif est reporté.

**Critère de fin de V1** — ce parcours fonctionne de bout en bout, sur mobile et desktop, en Stripe ET en virement :
```
Catalogue → Fiche produit → Panier → Coordonnées → Choix relais Chronopost → Récap (date d'expédition, frais, CGV)
→ Paiement (Stripe | virement) → Commande payée → Back-office : préparation → expédition + n° de suivi
→ Email client → Facture PDF (email + espace client) → Livraison
```

---

## 2. Décisions validées

| Sujet | Décision |
|---|---|
| Stack | Laravel + Filament + MariaDB (Ian = exploitation PHP-FPM/Nginx) |
| Hébergement | VPS Linux Infomaniak Cloud, sans Plesk. Infra, DNS, domaine, bascule : **gérés par Ian**, hors périmètre Claude Code |
| CDN / WAF | Cloudflare devant le site |
| Code | Dépôt Git privé (compte perso de Ian) — URL `À RENSEIGNER` |
| Design | Reproduction du rendu visuel de painsansgluten.fr, **recodé** (aucun code du thème Shopify) |
| Catégories | 4 : Pains, Viennoiseries, Pâtisseries, Biscuits |
| Produits | ~70, saisis **un par un par la cliente** dans le back-office |
| Disponibilité | Interrupteur disponible / indisponible. **Pas de gestion de stock en quantité** |
| Production | Quotidienne, fabrication à la commande. **Pas de plafond** de commandes |
| Transporteur | **Chronopost Relais uniquement** (contrat + identifiants existants). Pas de retrait boutique |
| Marchés | La cliente vend aussi sur les marchés : **hors périmètre** de la boutique |
| Date d'expédition | **Calculée automatiquement** (V1). Choix par le client → V2 |
| Paramètres expédition | Jours d'expédition, heure limite, délai de fabrication, jours fermés : **paramètres BO**, valeurs `À RENSEIGNER` |
| Frais de port | Payés par le client. Grille par tranches de poids + franco optionnel, **valeurs `À RENSEIGNER`** |
| Commande | **Invité autorisé** + compte au choix |
| Paiement | Stripe Checkout hébergé (compte Stripe existant) + virement |
| Virement | Validation manuelle en BO ; délai d'annulation auto **paramétrable** (défaut 5 j, désactivable) ; relance à mi-délai |
| TVA | Société assujettie. Taux **par produit**, à valider par l'expert-comptable |
| Emails | Brevo |
| Cookies | tarteaucitron.js + Consent Mode v2 |
| URLs | En français + redirections 301 depuis les URLs Shopify |
| Admins | Un profil (pas de rôles), plusieurs comptes possibles (cliente + Ian), **2FA obligatoire** |
| Codes promo, newsletter, recherche | V2 |
| Blog | V1.1 (juste après mise en ligne) |
| FAQ | V1 (simple) |

---

## 3. Périmètre

### 3.1 V1 (cette semaine — préproduction fonctionnelle)
- Front-office au design de painsansgluten.fr : accueil, 4 catégories, page boutique (tous produits), fiche produit, « Vous aimerez aussi » automatique.
- Panier (tiroir latéral + page panier).
- Tunnel : invité ou compte, coordonnées, choix du relais Chronopost, récapitulatif avec date d'expédition prévue, acceptation CGV.
- Paiement Stripe Checkout + webhooks idempotents ; virement avec instructions et validation manuelle.
- Commandes avec statuts et transitions contrôlées.
- Factures PDF numérotées + avoirs sur remboursement (remboursement **total** uniquement).
- Espace client : tableau de bord, commandes (+ suivi), factures/avoirs, informations, adresse de facturation, mot de passe, demande de suppression de compte.
- Back-office Filament : tableau de bord minimal, produits (onglets, duplication, images), catégories, commandes, expéditions, clients, factures/avoirs + **export comptable CSV**, pages, FAQ, apparence/accueil, paramètres, redirections, administrateurs.
- Emails transactionnels via Brevo.
- Pages légales **créées vides** (placeholder), éditables en BO.
- SEO technique, sitemap, robots, schema.org, 301 Shopify.
- GTM/GA4 + tarteaucitron + Consent Mode v2.
- Sécurité de base, 2FA admin.

### 3.2 V1.1 (juste après la mise en ligne)
- Blog (articles, catégories d'articles, SEO, schema `Article`, liens vers produits).

### 3.3 V2 (2 à 3 semaines)
- Choix de la date d'expédition par le client (calendrier).
- Codes promo.
- Newsletter (formulaire → liste Brevo).
- Recherche produits.
- Import/export CSV produits.
- Rapprochement des versements Stripe (payouts, frais, lettrage).
- Rapports chiffrés détaillés (CA par période, par moyen de paiement).
- Remboursement partiel.
- Rôles administrateurs différenciés.
- Couleurs/polices modifiables depuis le BO.
- Génération d'étiquettes Chronopost via API.
- Plafonds de production (global / par produit).
- Filtres allergènes (« sans œuf », « sans lactose »).
- FAQ contextuelle sur les fiches produits.
- Événement GA4 `purchase` pour les virements (Measurement Protocol côté serveur).

> Toute demande hors V1 pendant le développement → `docs/BACKLOG.md`.

---

## 4. Architecture et stack

```
Client ─► Cloudflare ─► Nginx ─► PHP-FPM (Laravel)
                                   ├── Front-office (Blade + Livewire)
                                   ├── Espace client (/mon-compte)
                                   ├── Back-office (Filament, /admin)
                                   ├── Queue worker (Supervisor) ─► Brevo
                                   └── Scheduler (cron) ─► tâches planifiées
                                   │
                                MariaDB          Stockage local (images, PDF)
                                   │
              Stripe (Checkout + webhooks)   Chronopost (WS points relais)
```

- **Monolithe Laravel** unique. Pas de microservice, pas de Redis, pas de moteur de recherche externe en V1.
- Deux guards d'authentification distincts : `web` (clients, table `users`) et `admin` (table `admins`, Filament).
- Stockage fichiers : disque local `storage/app` (images publiques via `public`, factures PDF en **privé**, servies via route authentifiée ou URL signée).
- Tâches planifiées (scheduler Laravel) : expiration/relance des virements, nettoyage paniers, génération sitemap.

**Prérequis serveur** (mis en place par Ian) : Nginx, PHP-FPM 8.3+ (extensions : bcmath, intl, gd ou imagick, mbstring, pdo_mysql, zip, exif), MariaDB, Composer, Node (build des assets uniquement), Supervisor (`queue:work`), cron `* * * * * php artisan schedule:run`.

---

## 5. Front-office

### 5.1 Design
Reproduire le **rendu visuel** de https://painsansgluten.fr :
- bandeau d'annonce en haut (texte éditable en BO) ;
- logo centré, menu : Accueil / Boutique sans gluten (méga-menu avec visuels des catégories) / Contact ; icônes compte et panier ;
- grilles produits avec image, nom, prix, bouton « Ajouter » direct ;
- panier en **tiroir latéral** ;
- fiche produit : galerie (image principale + miniatures), nom, prix, sélecteur de quantité, bouton ajouter, puis blocs de contenu ;
- pied de page : liens pages légales, Facebook, copyright.

⚠️ **Aucune réutilisation du code du thème Shopify** (Liquid, CSS, JS). Couleurs, typographies et espacements sont relevés sur le site public et définis comme **variables** (thème Tailwind / CSS custom properties) dans un seul fichier. Les assets HD (logo, photos, bannière) sont fournis par Ian.

Mobile first. Le tiroir panier, le menu et le tunnel doivent être irréprochables sur mobile.

### 5.2 Pages publiques et URLs
| Page | URL |
|---|---|
| Accueil | `/` |
| Boutique (tous produits) | `/boutique` |
| Catégorie | `/pains-sans-gluten`, `/viennoiseries-sans-gluten`, `/patisseries-sans-gluten`, `/biscuits-sans-gluten` (slugs éditables) |
| Fiche produit | `/produit/{slug}` |
| Panier | `/panier` |
| Tunnel | `/commande` (étapes) |
| Confirmation | `/commande/confirmation/{token}` |
| FAQ | `/faq` |
| Contact | `/contact` |
| Pages légales | `/mentions-legales`, `/cgv`, `/politique-de-confidentialite`, `/politique-de-remboursement`, `/livraison` |
| Espace client | `/mon-compte/...` |
| Back-office | `/admin` |

Contact : V1 = page de contenu éditable (coordonnées). Formulaire de contact : **non** en V1 (anti-spam, RGPD) → BACKLOG.

### 5.3 Produits non expédiables
Champ `is_shippable` (défaut : oui). Un produit non expédiable reste **visible** mais **non commandable**, avec un message éditable en BO (par défaut : « Disponible uniquement sur nos marchés »). **`À VALIDER` par Ian / la cliente.**

---

## 6. Catalogue et fiche produit

### 6.1 Catégories
Champs : nom, slug, description (texte d'introduction SEO), image, position, active, seo_title, seo_description.
Seed initial : Pains, Viennoiseries, Pâtisseries, Biscuits.

### 6.2 Produit — champs
| Groupe (onglet BO) | Champs |
|---|---|
| Général | nom*, slug* (auto, éditable), référence* (unique), catégorie*, prix TTC* (centimes), taux TVA* (liste paramétrable), disponible (bool), expédiable (bool), mis en avant (bool), position |
| Descriptions | description courte*, description longue (éditeur riche) |
| Composition | ingrédients* (éditeur riche, allergènes en **gras**), allergènes « contient » (14 cases), allergènes « traces possibles » (14 cases), mention atelier (texte, ex. « Fabriqué dans un atelier qui utilise… ») |
| Nutrition (pour 100 g) | énergie kcal, énergie kJ, matières grasses, dont AGS, glucides, dont sucres, fibres, protéines, sel (décimaux) |
| Conditionnement | poids net en grammes* (affiché), poids d'expédition en grammes* (calcul frais de port), conditionnement (texte), vendu à l'unité / par lot (texte) |
| Conseils | conseils de dégustation, conservation, durée de conservation indicative |
| Images | image principale*, galerie (ordre glissable), texte alternatif* par image |
| SEO | seo_title, seo_description (compteurs de caractères) |

\* = obligatoire **pour publier** (le produit peut être enregistré en brouillon sans).

**Les 14 allergènes réglementaires** : gluten, crustacés, œufs, poissons, arachides, soja, lait, fruits à coque, céleri, moutarde, graines de sésame, anhydride sulfureux et sulfites, lupin, mollusques.

⚠️ Champs obligatoires bloquants à la publication : pas de fiche avec poids « XXX g » ou sans ingrédients.
⚠️ Le texte « sans gluten » engage la cliente (seuil réglementaire 20 mg/kg) : le site affiche, il ne certifie pas.

### 6.3 Affichage fiche produit
Ordre des blocs, repris du site actuel : description → ingrédients → allergènes (contient / traces / mention atelier) → valeurs nutritionnelles (tableau) → conditionnement → conseils de dégustation → conservation → bloc expédition (texte global éditable en BO, identique pour tous les produits) → « Vous aimerez aussi » (4 produits disponibles de la même catégorie, aléatoires ou par position).

Un bloc vide n'est pas affiché.

### 6.4 Back-office produits
- Action **« Dupliquer »** (copie tout sauf slug/référence, suffixés `-copie`, statut indisponible).
- Bascule rapide disponible/indisponible depuis la liste.
- Images : conversion WebP + tailles (miniature, liste, fiche, zoom).
- **Suppression** : interdite si le produit figure dans une commande → désactivation uniquement.

---

## 7. Panier

- Panier stocké en BDD (`carts` / `cart_items`), identifié par session (invité) ou `user_id` (connecté). Fusion à la connexion.
- Ajout depuis la grille ou la fiche ; modification quantité ; suppression ; recalcul immédiat (Livewire).
- Affiche : lignes, sous-total TTC, frais de port (estimés si relais non encore choisi : afficher « calculés à l'étape suivante » ou estimation selon poids — **la grille ne dépend que du poids**, donc calcul possible dès le panier), total TTC, **date d'expédition prévue**.
- Quantité max par ligne : paramètre BO (défaut 20) pour éviter les erreurs de saisie.
- Produit devenu indisponible ou non expédiable → retiré au prochain affichage avec message.
- Nettoyage planifié des paniers invités > 30 jours.

---

## 8. Livraison

### 8.1 Chronopost Relais
- Service : Chronopost Relais (code produit Chronopost du contrat : `À RENSEIGNER`).
- Zone : France métropolitaine **hors Corse** (contrôle du code postal : refuser 20xxx).
- **Sélection du relais obligatoire** dans le tunnel :
  - saisie code postal (+ ville optionnelle) → appel au **web service officiel Chronopost de recherche de points relais** (identifiant à confirmer dans la documentation fournie avec le contrat) ;
  - liste des relais : nom, adresse, distance, horaires ; sélection d'un relais ;
  - le relais choisi (ID Chronopost, nom, adresse complète) est **copié dans la commande** (snapshot).
- Carte : **non** en V1 (liste uniquement) → BACKLOG.
- Identifiants (numéro de compte, mot de passe WS) dans `.env`.
- ⚠️ **Tâche T01 = test des identifiants et du WS relais dès le jour 1.** Si le WS ne répond pas comme prévu, on le sait avant d'avoir construit le tunnel.
- ⚠️ Cache des résultats de recherche relais (par code postal, 1 h) pour limiter les appels.
- ⚠️ Timeout court (5 s) + message d'erreur clair + log si le WS est indisponible : le client ne doit pas rester bloqué sur un écran vide.

### 8.2 Expédition (V1 : manuelle)
- La cliente crée le colis sur son outil Chronopost habituel, puis saisit le **numéro de suivi** dans la commande (BO) → statut « Expédiée » → email au client avec lien de suivi.
- URL de suivi : modèle paramétrable en BO (`À RENSEIGNER`, ex. `https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT={tracking}` — **à vérifier**).
- Génération d'étiquettes par API → V2.

### 8.3 Frais de port
- Table `shipping_rates` : tranches de **poids d'expédition total** du panier (min g, max g, prix TTC centimes).
- Paramètres : franco à partir de X € TTC (optionnel, désactivable), taux de TVA appliqué aux frais de port (`À RENSEIGNER` — ⚠️ à valider par le comptable).
- Valeurs : **`À RENSEIGNER`** (fournies par Ian). Tant que la grille est vide, **le tunnel est bloqué** avec un message admin (pas de livraison gratuite par accident).
- Poids hors grille → commande impossible + message « contactez-nous ».

### 8.4 Calcul de la date d'expédition
Service unique `ShippingDateCalculator`, entièrement testé (tests unitaires sur de nombreux cas).

Paramètres BO :
| Paramètre | Description | Valeur |
|---|---|---|
| `shipping_weekdays` | Jours où les colis partent (cases lun→dim) | `À RENSEIGNER` |
| `order_cutoff_time` | Heure limite de commande (HH:MM) | `À RENSEIGNER` |
| `production_lead_days` | Délai de fabrication en jours ouvrés de production (0 = expédié le jour même si avant l'heure limite) | `À RENSEIGNER` |
| `closed_dates` | Table de jours/périodes fermés (congés, fériés) avec libellé | à saisir par la cliente |

Algorithme (référence = instant où **le paiement est confirmé**, en `Europe/Paris`) :
1. `d` = date du jour ; si heure > heure limite → `d` = lendemain.
2. Ajouter `production_lead_days` jours (en sautant les jours fermés).
3. Avancer `d` jusqu'au premier jour qui est un jour d'expédition **et** non fermé.
4. Retourner `d`.

Affichage :
- Panier et récapitulatif : « Expédition prévue le **mardi 13 octobre**, disponible en relais le lendemain » (estimation calculée à l'instant présent).
- Stripe : date figée sur la commande au **webhook de paiement** (`planned_ship_date`).
- Virement : date **recalculée à la validation** du virement par l'admin ; email « paiement reçu » avec la date définitive.

⚠️ Message permanent sur la fiche produit, le panier et l'email d'expédition : **retirer le colis le jour même de sa mise à disposition** (produit frais). Texte éditable en BO.
⚠️ Tant que les paramètres d'expédition ne sont pas renseignés → tunnel bloqué (même logique que les frais de port).

---

## 9. Commandes

### 9.1 Tunnel
```
1. Panier
2. Coordonnées : « Commander en invité » ou « Se connecter / Créer un compte »
   → email*, prénom*, nom*, téléphone mobile* (obligatoire : SMS Chronopost), adresse de facturation*
3. Livraison : recherche et choix du relais Chronopost
4. Récapitulatif : produits, sous-total, frais de port, total TTC, date d'expédition prévue,
   case CGV obligatoire (non pré-cochée) + rappel exception de rétractation (denrées périssables)
   choix du paiement : Carte (Stripe) | Virement
5a. Stripe → page Stripe → retour /commande/confirmation/{token}
5b. Virement → /commande/confirmation/{token} avec instructions de virement
```
- La commande est créée en BDD **au clic sur « Payer »** (statut `pending_payment`), avec **snapshot** complet : lignes (nom, référence, prix TTC unitaire, taux TVA, poids), relais, adresses, frais de port, totaux.
- `token` de confirmation : aléatoire, non devinable (pas l'ID).
- Numéro de commande lisible : séquentiel (format `À VALIDER`, proposition `C2026-00001`), distinct de l'ID et du numéro de facture.

### 9.2 Statuts

| Code (enum) | Libellé FR |
|---|---|
| `pending_payment` | En attente de paiement |
| `paid` | Paiement accepté |
| `preparing` | En préparation |
| `shipped` | Expédiée |
| `delivered` | Livrée |
| `cancelled` | Annulée |
| `refunded` | Remboursée |
| `payment_failed` | Paiement échoué |

Transitions autorisées (toute autre transition est refusée par le service, avec exception + log) :
```
pending_payment → paid            (webhook Stripe | validation virement admin)
pending_payment → payment_failed  (Stripe : échec)
pending_payment → cancelled       (Stripe : session expirée | virement : délai dépassé | admin)
payment_failed  → paid            (nouvelle tentative réussie)
payment_failed  → cancelled
paid            → preparing       (admin)
paid            → refunded        (admin : remboursement total + avoir)
preparing       → shipped         (admin : n° de suivi obligatoire)
preparing       → refunded        (admin)
shipped         → delivered       (admin, manuel en V1)
shipped         → refunded        (admin, cas litige)
delivered       → refunded        (admin, cas litige)
```
- Chaque transition est historisée (`order_status_histories` : de, vers, auteur [system/admin id], date, commentaire).
- Chaque transition déclenche ses effets (email, facture, avoir) **une seule fois**.

### 9.3 Reprise de paiement
Si le client revient du Stripe sans payer (annulation), il peut relancer le paiement de la **même** commande tant qu'elle est `pending_payment` ou `payment_failed` (nouvelle Checkout Session). Il peut aussi basculer sur virement.

---

## 10. Paiement Stripe

- **Stripe Checkout hébergé**, mode `payment`, devise EUR, `locale: fr`.
- Lignes envoyées à Stripe : produits + frais de port, montants issus de la commande en BDD.
- `client_reference_id` = ID commande ; `metadata.order_id`, `metadata.order_number`.
- `expires_at` : 30 min (paramètre).
- Moyens : carte (Apple Pay / Google Pay inclus via Checkout si activés dans le dashboard Stripe).

Webhooks traités :
| Événement | Effet |
|---|---|
| `checkout.session.completed` (payment_status = paid) | vérifier montant + devise → `paid`, date d'expédition figée, facture, email |
| `checkout.session.async_payment_succeeded` | idem (si un moyen asynchrone est activé un jour) |
| `checkout.session.async_payment_failed` | `payment_failed` |
| `checkout.session.expired` | `cancelled` si toujours `pending_payment` |
| `charge.refunded` | si remboursement fait depuis le dashboard Stripe : `refunded` + avoir (si pas déjà fait depuis le BO) |

⚠️ Idempotence : table `stripe_events` (`event_id` UNIQUE). Traitement dans une transaction, avec verrou sur la commande (`lockForUpdate`).
⚠️ Commande déjà `paid` + nouvel événement de paiement → ignoré, log info.
⚠️ Montant différent → statut inchangé, drapeau `payment_anomaly`, log error, email admin.
⚠️ Le webhook répond 200 vite ; envoi des emails et génération PDF en **queue**.
⚠️ Page de confirmation : si le webhook n'est pas encore arrivé, afficher « Paiement en cours de validation » et rafraîchir (poll Livewire 2 s, max 30 s), puis message « vous recevrez un email ».

Remboursement depuis le BO : action « Rembourser » (montant total uniquement en V1) → appel API Stripe `refunds.create` avec clé d'idempotence → `refunded` + avoir + email.

---

## 11. Paiement par virement

- Paramètres BO : titulaire, IBAN, BIC, nom de la banque (`À RENSEIGNER`).
- À la commande : statut `pending_payment`, `payment_method = bank_transfer`.
- Page de confirmation + email : montant, titulaire, IBAN, BIC, **référence obligatoire = numéro de commande**, échéance.
- ⚠️ Avertissement client (page + email) : « Votre commande sera fabriquée et expédiée après réception de votre virement. Privilégiez le virement instantané pour ne pas retarder votre commande. »
- BO : action **« Valider le virement reçu »** → modale de confirmation affichant montant et référence attendus (⚠️ avertissement admin : vérifier sur le relevé que **référence et montant** correspondent) → `paid` + date d'expédition recalculée + facture + email.
- Paramètre `bank_transfer_cancel_days` (défaut **5**, `À AJUSTER`) + interrupteur activé/désactivé.
- Tâche planifiée (horaire) : relance email à mi-délai ; annulation + email à échéance.
- Filtre BO « Virements en attente » + compteur sur le tableau de bord.

---

## 12. Facturation et avoirs

- Facture générée **au passage à `paid`** (pas avant).
- Numérotation **séquentielle continue sans trou**, attribuée dans une transaction avec verrou (table `invoice_sequences`). Format `À VALIDER` (proposition `F2026-00001`). Avoirs : séquence distincte (proposition `A2026-00001`).
- La facture stocke un **snapshot** complet (vendeur, client, lignes, totaux) en BDD + PDF dans le stockage **privé**.
- PDF : régénérable à l'identique depuis le snapshot (jamais depuis les données vivantes du produit ou du client).
- Lignes : désignation, référence, quantité, PU HT, taux TVA, total HT ; récapitulatif par taux de TVA (base HT, TVA) ; total HT, total TVA, total TTC ; frais de port en ligne dédiée ; moyen et date de paiement.
- Mentions vendeur (paramètres BO, `À RENSEIGNER`) : raison sociale, forme juridique, capital, adresse, SIRET, RCS, n° TVA intracommunautaire, + mentions libres (pied de facture).
- Calcul : prix saisis TTC → HT ligne = arrondi(TTC / (1 + taux)). Règle d'arrondi documentée dans `docs/DECISIONS.md`.

⚠️ **Facture émise = immuable.** Aucune action « modifier » dans le BO.
⚠️ Remboursement → **avoir** (montant négatif, référence à la facture d'origine).
⚠️ Mentions obligatoires, taux de TVA (produits et frais de port), règle d'arrondi et impact éventuel de la réforme facture électronique / e-reporting sur la vente B2C en ligne : **à valider par l'expert-comptable avant la mise en production.** Claude Code ne tranche pas ces points.

Accès : client (espace client ou lien signé dans l'email pour les invités, validité 30 jours, régénérable), admin (BO).

---

## 13. Espace client (`/mon-compte`)

```
Tableau de bord     dernières commandes, statut, raccourcis
Mes commandes       liste + détail (lignes, relais, statut, historique, n° suivi + lien, date d'expédition)
Mes factures        factures et avoirs, téléchargement PDF
Mes informations    prénom, nom, email (changement = revérification), téléphone
Mon adresse         adresse de facturation (une seule en V1) — le relais se choisit à chaque commande
Mot de passe        modification (mot de passe actuel requis)
Mes données         demande de suppression de compte (envoie une demande à l'admin, traitement manuel)
Déconnexion
```
- Inscription, connexion, mot de passe oublié, **vérification d'email** : via le starter kit / Fortify officiel Laravel (pas de mécanisme maison).
- Après une commande invité : proposition « Créer mon compte » pré-remplie.
- Rattachement des commandes invité au compte : **par email, après vérification de l'email**.
- Rate limiting connexion et mot de passe oublié.

---

## 14. Back-office (Filament, `/admin`)

```
Tableau de bord   CA TTC du mois, nb de commandes du mois, virements en attente, commandes à expédier (aujourd'hui / en retard)
Commandes         liste, recherche (n°, nom, email), filtres (statut, paiement, date d'expédition), détail, actions
Expéditions       vue des commandes paid/preparing triées par date d'expédition prévue
Produits          onglets (§6.2), dupliquer, bascule dispo, images
Catégories
Clients           recherche, détail, commandes, adresse (jamais de mot de passe visible)
Factures & avoirs liste, PDF, export comptable CSV
Contenus          Pages, FAQ, Accueil/Apparence, Redirections
Paramètres        Boutique, Facturation, Virement, Expédition, Frais de port, Jours fermés, Emails, SEO global
Administrateurs   comptes admin (2FA obligatoire)
```

Actions sur une commande (visibles selon le statut, via le service de transitions) : valider le virement, passer en préparation, expédier (saisie n° suivi obligatoire), marquer livrée, annuler, rembourser (confirmation forte), renvoyer l'email de confirmation, télécharger facture.

**Export comptable** : période (du/au) → CSV (séparateur `;`, UTF-8 BOM pour Excel) des factures et avoirs : numéro, date, type, n° commande, client, moyen de paiement, HT par taux, TVA par taux, total HT, total TVA, total TTC. Format définitif `À VALIDER` avec le comptable.

---

## 15. Emails transactionnels (Brevo, en queue)

| Déclencheur | Email |
|---|---|
| Inscription | Bienvenue + vérification email |
| Mot de passe oublié | Lien de réinitialisation |
| Stripe payé | Confirmation de commande + date d'expédition + lien facture |
| Virement : commande créée | Commande enregistrée + instructions de virement + avertissement |
| Virement : mi-délai | Relance |
| Virement : validé | Paiement reçu + date d'expédition + lien facture |
| Virement : délai dépassé | Commande annulée |
| Expédiée | N° de suivi + lien + **rappel retrait le jour même** |
| Remboursée | Confirmation + avoir |
| Admin | Nouvelle commande payée ; nouveau virement en attente ; anomalie de paiement ; demande de suppression de compte |

- Expéditeur, email de réponse, email de notification admin : paramètres (`À RENSEIGNER`).
- Templates Markdown Laravel aux couleurs de la marque, en français.
- ⚠️ SPF, DKIM, DMARC du domaine configurés pour Brevo (Ian) **avant** les tests d'envoi réels.
- ⚠️ En local / préprod : mailer `log` ou boîte de test, jamais d'envoi à de vrais clients.

---

## 16. Contenus

### 16.1 Pages
Module « Pages » : titre, slug, contenu (éditeur riche), seo_title, seo_description, publiée.
Seed : Mentions légales, CGV, Politique de confidentialité, Politique de remboursement, Livraison, Contact — **contenu placeholder « Page en cours de rédaction »**. La cliente les remplit.
⚠️ **Ne pas ouvrir les ventes tant que CGV et mentions légales ne sont pas remplies.** Claude Code n'écrit aucun texte juridique.

### 16.2 FAQ
`faq_items` : question, réponse (éditeur riche), catégorie de FAQ (texte libre ou petite table), position, publiée. Page `/faq` avec données structurées `FAQPage`.
Note : Google n'affiche plus les résultats enrichis FAQ pour la plupart des sites ; le balisage reste utile pour la structure et les moteurs IA.

### 16.3 Accueil / Apparence (paramètres)
Logo (+ favicon), bandeau d'annonce (texte + actif), bannière d'accueil (image, titre, sous-titre, texte et lien du bouton), produits mis en avant (sélection), texte de présentation, lien Facebook, coordonnées du pied de page, texte du bloc expédition des fiches, message produits non expédiables, message retrait relais.
Couleurs et polices : **figées dans le code** (variables) en V1.

### 16.4 Redirections
Table `redirects` (source, cible, code 301, actif) + middleware ; CRUD simple dans le BO. Seed : voir §23.

---

## 17. SEO

- Chaque produit, catégorie, page : `title`, `meta description` (repli automatique si vide), `canonical`, Open Graph, Twitter card.
- Un seul `H1` par page, hiérarchie Hn cohérente, HTML sémantique.
- `sitemap.xml` (accueil, boutique, catégories, produits disponibles, pages, FAQ) régénéré quotidiennement et à la modification.
- `robots.txt` : prod = autorisé + sitemap ; préprod = `Disallow: /` + `X-Robots-Tag: noindex`.
- Exclure de l'indexation : `/panier`, `/commande*`, `/mon-compte*`, `/admin*`.
- Données structurées JSON-LD : `Product` (+ `Offer` : prix, devise, disponibilité), `BreadcrumbList`, `Organization` + `Bakery` (adresse Avranches — coordonnées `À RENSEIGNER`) sur l'accueil, `FAQPage`.
- Fil d'Ariane visible sur catégories et fiches.
- Page 404 personnalisée avec liens vers les catégories.
- Images : `alt` obligatoire, `width`/`height` explicites, lazy loading hors image principale, WebP.
- Slugs produits : **reprendre ceux de Shopify** (déjà descriptifs : `pain-de-mie-sans-gluten`…).
- ⚠️ Contexte : forte homonymie avec une marque québécoise (« Cuisine L'Angélique ») sur le pain sans gluten. Stratégie de contenu (V1.1) centrée sur « pain sans gluten artisanal + Normandie / Avranches / livraison », pas sur le nom seul.

---

## 18. Analytics et consentement

- **tarteaucitron.js** auto-hébergé (pas de CDN tiers), en français, bouton de gestion permanent dans le pied de page.
- **Consent Mode v2** : snippet `gtag('consent','default',{ad_storage:'denied',analytics_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'})` **avant** GTM ; mise à jour `granted` via tarteaucitron.
- **GTM** (ID `À RENSEIGNER`) → GA4 (ID `À RENSEIGNER`). Aucun tag GA4 en dur.
- `dataLayer` e-commerce (format GA4) : `view_item`, `view_item_list`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`.
- `purchase` : uniquement sur la page de confirmation quand la commande est **`paid` en BDD** (Stripe), **une seule fois** (drapeau `ga_purchase_sent`), valeurs issues de la commande.
- ⚠️ Virements : pas de `purchase` en V1 (le paiement est confirmé plus tard, hors navigateur) → V2 via Measurement Protocol.
- ⚠️ Test de recette : aucun cookie `_ga` avant clic sur « Accepter ».
- Google Search Console : vérification via DNS (Ian).

---

## 19. RGPD et légal

- Pages légales : §16.1 (contenu fourni par la cliente).
- Case CGV obligatoire non pré-cochée au récapitulatif, horodatage de l'acceptation stocké sur la commande.
- Rappel de l'exception au droit de rétractation pour les denrées périssables (texte éditable, `À VALIDER` juridiquement).
- Données invité stockées **dans la commande** (pas de compte créé à son insu).
- Base légale : exécution du contrat + obligations comptables. Aucune utilisation marketing sans consentement explicite (newsletter V2, case non pré-cochée).
- Demande de suppression de compte : traitement manuel par l'admin. Les commandes et factures sont conservées (obligation comptable) ; les données de compte non nécessaires sont anonymisées. Durées de conservation exactes : `À VALIDER` (comptable / juriste) et à reporter dans la politique de confidentialité.
- Aucune donnée carte bancaire ne transite ni n'est stockée chez nous (Stripe Checkout hébergé).

---

## 20. Sécurité

- HTTPS partout (Cloudflare + certificat origine), cookies `Secure`, `HttpOnly`, `SameSite=Lax`.
- Mots de passe : hash natif Laravel (bcrypt/argon2) — aucun mécanisme maison.
- Admin : guard séparé, table séparée, **2FA obligatoire** (fonctionnalité native Filament ou package éprouvé), rate limiting de la connexion, chemin `/admin` (renommage possible par paramètre `.env`).
- Validation serveur de toutes les entrées (Form Requests / règles Livewire).
- ORM Eloquent / requêtes préparées uniquement (pas de SQL concaténé).
- Échappement Blade par défaut ; contenu riche du BO purifié (HTML autorisé limité) avant affichage.
- CSRF actif partout sauf route webhook Stripe (signature vérifiée).
- Uploads : images uniquement (jpeg, png, webp), taille max 10 Mo, revalidation MIME, renommage.
- Trusted proxies Cloudflare (IP réelle client).
- En-têtes : `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`. CSP : à poser en mode report-only d'abord (GTM, Stripe, tarteaucitron).
- Factures PDF hors dossier public, accès contrôlé (policy) ou URL signée.
- Policies Laravel : un client n'accède qu'à ses commandes et factures (test obligatoire : accès à la commande d'un autre client = 403/404).
- `.env` hors Git ; `APP_DEBUG=false` hors local ; logs sans données personnelles.
- Dépendances : `composer audit` et `npm audit` dans la checklist.

---

## 21. Performance

- Assets compilés Vite, minifiés, versionnés (cache long côté Cloudflare).
- Images WebP en tailles adaptées, `srcset`, lazy loading.
- Scripts tiers (GTM) chargés après consentement.
- Requêtes : eager loading (pas de N+1), index sur slugs, statuts, dates, `user_id`, `order_number`.
- Caches Laravel en prod : `config:cache`, `route:cache`, `view:cache`, `event:cache`.
- Pas de cache applicatif complexe en V1.

---

## 22. Modèle de données (indicatif, à finaliser en T02)

```
admins            id, name, email, password, 2fa…, timestamps
users             id, first_name, last_name, email, email_verified_at, phone, password, timestamps, deletion_requested_at
addresses         id, user_id, type(billing), first_name, last_name, company?, line1, line2, postal_code, city, country
categories        id, name, slug(unique), description, position, is_active, seo_title, seo_description
products          id, category_id, name, slug(unique), reference(unique), short_description, description,
                  price_ttc(int cents), vat_rate(decimal), is_available, is_shippable, is_featured, is_published, position,
                  ingredients, allergens_contains(json), allergens_traces(json), allergen_note,
                  nutrition(json: kcal,kj,fat,saturated,carbs,sugars,fiber,protein,salt),
                  net_weight_g(int), shipping_weight_g(int), packaging, tasting_tips, storage, shelf_life,
                  seo_title, seo_description, timestamps, soft deletes
media             (spatie medialibrary)
carts             id, session_id, user_id, timestamps
cart_items        id, cart_id, product_id, quantity
orders            id, number(unique), token(unique), user_id?, status(enum), payment_method(enum stripe|bank_transfer),
                  email, first_name, last_name, phone,
                  billing_* (snapshot), relay_id, relay_name, relay_address_* (snapshot),
                  subtotal_ttc, shipping_ttc, shipping_vat_rate, total_ttc, total_ht, total_vat (int cents),
                  planned_ship_date(date), shipped_at, delivered_at, tracking_number,
                  cgv_accepted_at, payment_anomaly(bool), ga_purchase_sent(bool), paid_at, cancelled_at, timestamps
order_items       id, order_id, product_id?, product_name, product_reference, unit_price_ttc, vat_rate, quantity, weight_g, line_total_ttc, line_total_ht
order_status_histories  id, order_id, from, to, actor_type, actor_id?, comment, created_at
payments          id, order_id, method, provider_ref (checkout session / payment intent), amount, status, raw(json), timestamps
stripe_events     id, event_id(unique), type, processed_at
invoices          id, order_id, type(invoice|credit_note), number(unique), issued_at, snapshot(json), totals…, pdf_path, related_invoice_id?
invoice_sequences type, year, last_number
shipping_rates    id, min_weight_g, max_weight_g, price_ttc
closed_dates      id, start_date, end_date, label
pages             id, title, slug(unique), content, is_published, seo_title, seo_description
faq_items         id, question, answer, group, position, is_published
redirects         id, source(unique), target, status_code, is_active
settings          (spatie/laravel-settings)
```
Montants en **centimes** (`int`). Taux de TVA en `decimal(5,2)`.

---

## 23. Migration depuis Shopify

1. **Assets** : logo HD, bannière, photos produits — fournis par Ian.
2. **Produits** : les 7 produits existants sont ressaisis (ou seedés) comme modèles ; les autres sont saisis par la cliente.
3. **Clients / commandes** : a priori aucun (site en pré-lancement, prix à 0 €) — `À CONFIRMER`. Si clients existants : import sans mot de passe + email de réinitialisation.
4. **Redirections 301** (seed de la table `redirects`) :

| Ancienne URL Shopify | Nouvelle URL |
|---|---|
| `/collections/all` | `/boutique` |
| `/collections/les-pains-sans-gluten` | `/pains-sans-gluten` |
| `/collections/viennoiseries-sans-gluten` | `/viennoiseries-sans-gluten` |
| `/collections/nos-patisseries-sans-gluten` | `/patisseries-sans-gluten` |
| `/products/{slug}` | `/produit/{slug}` (règle générique, query string `?variant=` ignorée) |
| `/pages/contact` | `/contact` |
| `/policies/legal-notice` | `/mentions-legales` |
| `/policies/terms-of-sale` | `/cgv` |
| `/policies/privacy-policy` | `/politique-de-confidentialite` |
| `/policies/refund-policy` | `/politique-de-remboursement` |
| `/policies/shipping-policy` | `/livraison` |
| `/cart` | `/panier` |
| `/account*` | `/mon-compte` |

5. **Bascule** (Ian) : préprod validée → DNS Cloudflare → Search Console (nouveau sitemap) → suivi des 404 pendant 30 jours → résiliation Shopify **après** stabilisation.

---

## 24. Sauvegardes et exploitation (Ian)

- Sauvegarder : dump MariaDB quotidien, `storage/app` (images **et factures PDF**), `.env` (stocké chiffré, hors serveur).
- Stockage hors du serveur de production.
- ⚠️ **Restauration testée avant ouverture des ventes.** Une sauvegarde jamais restaurée n'est pas une sauvegarde.
- Supervision : disponibilité (URL publique + `/up` Laravel), queue worker actif, cron actif, espace disque, erreurs dans `storage/logs`.
- Logs Laravel en rotation quotidienne (`LOG_CHANNEL=daily`, 14 jours).

---

## 25. Tests (Pest)

Obligatoires (feature tests sauf mention) :
- **Catalogue** : affichage catégories/fiche ; produit indisponible non ajoutable ; produit non expédiable non ajoutable ; brouillon invisible.
- **Panier** : ajout, modification, suppression, quantité invalide, produit inexistant, fusion à la connexion, retrait d'un produit devenu indisponible.
- **Date d'expédition** (unitaires) : avant/après heure limite, veille de week-end, jours fermés consécutifs, délai de fabrication > 0, aucun jour d'expédition configuré (exception), passage d'année, changement d'heure.
- **Frais de port** (unitaires) : chaque tranche, bornes, franco, poids hors grille, grille vide → blocage.
- **Tunnel** : invité, connecté, Corse refusée, CGV non cochée refusée, relais obligatoire, recalcul serveur des montants (montant falsifié ignoré).
- **Stripe** : création session ; webhook signature invalide (400) ; `completed` → `paid` + facture ; même événement 2 fois → 1 seul traitement ; montant incorrect → anomalie ; `expired` → `cancelled` ; commande déjà payée ; remboursement → avoir.
- **Virement** : création, validation admin, recalcul date, relance mi-délai, annulation auto, annulation désactivée.
- **Transitions** : chaque transition autorisée OK ; transitions interdites rejetées ; historique écrit.
- **Factures** : numérotation séquentielle sans trou (y compris en concurrence simulée), snapshot immuable, avoir, accès client autorisé/refusé, lien signé invité.
- **Espace client** : accès à la commande d'un autre client refusé ; rattachement des commandes invité uniquement après vérification d'email.
- **Admin** : accès BO refusé à un client ; 2FA exigée.
- **SEO** : sitemap valide, robots préprod, 301 Shopify.

---

## 26. Ordre d'exécution et planning

Voir `tasks/`. Ordre :

| Jour | Tâches |
|---|---|
| J1 | T00 Initialisation · **T01 Spike Chronopost** · T02 BDD |
| J2 | T03 Auth · T04 Paramètres · T05 Catalogue BO · T06 Design système |
| J3 | T07 Pages catalogue · T08 Panier · T09 Date d'expédition · T10 Frais de port |
| J4 | T11 Relais · T12 Commandes & transitions · T13 Tunnel · T14 Stripe |
| J5 | T15 Virement · T16 Factures · T17 BO commandes · T18 Espace client · T19 Emails |
| J6 | T20 Contenus · T21 SEO · T22 Analytics · T23 Sécurité |
| J7 | T24 Recette complète · T25 Déploiement préprod |

⚠️ Planning **indicatif** : la V1 « cette semaine » vise une **préproduction fonctionnelle**. L'ouverture des ventes dépend de : grille de frais de port, paramètres d'expédition, CGV/mentions légales, saisie des produits, validation comptable, recette (`CHECKLIST-PROD.md`).

---

## 27. Liste « À RENSEIGNER »

| # | Élément | Qui |
|---|---|---|
| 1 | URL du dépôt Git | Ian |
| 2 | Identifiants Chronopost (compte, mot de passe WS), code produit Relais, URL de suivi | Ian / cliente |
| 3 | Jours d'expédition, heure limite, délai de fabrication | Cliente |
| 4 | Grille de frais de port, franco, TVA sur port | Ian / comptable |
| 5 | Taux de TVA par produit | Comptable |
| 6 | Mentions légales vendeur pour factures (raison sociale, SIRET, RCS, TVA intra, capital, adresse) | Cliente |
| 7 | Format numéros de commande / facture / avoir | Comptable |
| 8 | Titulaire, IBAN, BIC du compte de virement | Cliente |
| 9 | Délai d'annulation virement (défaut 5 j) | Cliente |
| 10 | Clés Stripe (test + live), secret webhook | Ian |
| 11 | Clé / SMTP Brevo, expéditeur, email admin | Ian |
| 12 | ID GTM, ID GA4 | Ian |
| 13 | Coordonnées boulangerie (adresse, téléphone) pour pied de page et schema.org | Cliente |
| 14 | Textes légaux (CGV, mentions, confidentialité, remboursement, livraison) | Cliente / juriste |
| 15 | Message produits non expédiables (« marchés ») — validation du principe | Ian / cliente |
| 16 | Clients Shopify existants à migrer ? | Ian |
| 17 | Assets HD (logo, bannière, photos) | Ian |

---

## 28. Récapitulatif des avertissements

1. Webhook Stripe = seule preuve de paiement ; idempotence par `event_id` ; vérification du montant.
2. Route webhook exclue du CSRF **et** du challenge Cloudflare/WAF.
3. Facture émise immuable ; remboursement = avoir ; numérotation sans trou sous verrou.
4. TVA, mentions de facture, arrondis, e-reporting : validation par l'expert-comptable avant production.
5. Aucun texte juridique écrit par l'IA ; ventes fermées tant que CGV/mentions légales vides.
6. Tunnel bloqué tant que grille de frais de port et paramètres d'expédition non renseignés.
7. Produits périssables : message « retirer le colis le jour même » sur fiche, panier, email d'expédition.
8. Virement : avertissement client (fabrication après réception, virement instantané conseillé) ; avertissement admin (vérifier référence + montant).
9. Chronopost : tester identifiants et WS relais dès J1 ; timeout + cache + message d'erreur.
10. Consent Mode v2 `denied` avant GTM ; pas de tag GA4 en dur ; aucun `_ga` avant consentement.
11. `purchase` GA4 uniquement si commande `paid` en BDD, une seule fois ; pas pour les virements en V1.
12. Rattachement commandes invité → compte uniquement après vérification de l'email.
13. Trusted proxies Cloudflare pour l'IP réelle.
14. 2FA obligatoire sur le back-office.
15. Images uploadées converties WebP et redimensionnées.
16. Aucun secret dans Git ; `.env.example` à jour ; `APP_DEBUG=false` hors local.
17. Aucune donnée personnelle dans les logs.
18. Préprod `noindex` + accès protégé.
19. Sauvegardes externalisées incluant les factures PDF ; restauration testée avant ouverture.
20. Montants en centimes, fuseau `Europe/Paris`, recalcul serveur systématique.
