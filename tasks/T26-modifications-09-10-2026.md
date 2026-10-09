# T26 — Modifications et évolutions du 09/10/2026

**Contexte** : retours sur le site **déjà en cours de réalisation**. Ce document décrit des **modifications de l'existant** (Lot A) et une **nouvelle fonctionnalité** (Lot B : espace professionnels).
**Règles** : `CLAUDE.md` + `QUALITE.md` s'appliquent intégralement (analyse d'impact, tests, suite complète verte, compte rendu §10).
**Branches** : `feature/T26A-retours-09-10` (Lot A), `feature/T26B-espace-pro` (Lot B).

> ⚠️ **Avant toute modification** : lire le code existant concerné (vues fiche produit, layout, composants boutons/icônes, paramètres, frais de port, tarteaucitron) et lister les fichiers impactés. Ne rien supposer sur l'implémentation actuelle.
> ⚠️ Les points marqués **`À TRANCHER`** ne doivent **pas** être implémentés avant réponse écrite de Ian. Les points marqués **`À CONFIRMER`** sont implémentés avec la valeur indiquée, signalée dans le compte rendu.

---

# LOT A — Corrections et contenus (prêt à exécuter)

## A1. Charte couleur officielle

Charte utilisée sur tous les supports de la marque (flyers, cartes de visite, Instagram, Facebook) :

| Rôle | Couleur | Usage |
|---|---|---|
| Fond | `#FEFCF2` (beige) | Fond général du site |
| Primaire | `#5D6E41` (vert — « Mon Sans Gluten ») | Boutons, icônes, liens importants, titres d'accent |
| Accent | `#B47C38` (marron ocre — « by Angélique ») | Survol des boutons, badges (compteur panier), détails |

**À faire**
- Définir ces 3 couleurs comme **variables uniques** (thème Tailwind + CSS custom properties) : `--color-bg`, `--color-primary`, `--color-accent`. Remplacer toute couleur codée en dur équivalente.
- Mettre à jour `PLAN.md` §5.1 avec cette charte.

⚠️ **Accessibilité** : texte blanc sur `#B47C38` = contraste ≈ 3,6:1 → **insuffisant pour du texte normal** (seuil WCAG AA 4,5:1), acceptable pour du texte **gras ≥ 14 pt** (≈ 18,7 px). → Texte des boutons en **gras**, taille ≥ 16 px, et contrôler le contraste réel. Texte blanc sur `#5D6E41` ≈ 5,6:1 → conforme. Signaler toute zone non conforme dans le compte rendu.

## A2. Tous les boutons

- **Tous les boutons du site** (Ajouter au panier, Passer commande, Payer, Valider, formulaires, espace client…) : fond `#5D6E41`, texte blanc gras ; **au survol et au focus clavier** : fond `#B47C38`.
- Transition douce (≈ 150 ms). Focus visible conservé (contour) pour la navigation clavier.
- Bouton désactivé : style distinct (opacité réduite, curseur `not-allowed`).
- Implémentation via **un composant Blade bouton unique** (et une classe utilitaire unique) — aucune couleur de bouton redéfinie ailleurs.
- Le back-office Filament **n'est pas concerné** (sauf demande ultérieure).

## A3. Icônes « Mon compte » et « Mon panier » (en-tête)

Actuel : icônes multicolores dans un rond vert foncé (voir capture fournie par Ian).
Attendu :
- Icônes **simples, monochromes, en vert `#5D6E41`** (style trait / outline cohérent, ex. silhouette utilisateur et sac/panier), **SVG inline**, pas d'emoji, pas d'image bitmap.
- Plus de rond de fond multicolore. Libellés « MON COMPTE » / « MON PANIER » conservés sous les icônes.
- Pastille du nombre d'articles du panier : fond `#B47C38`, texte blanc.
- Survol : icône et libellé en `#B47C38`.
- `aria-label` explicites (« Mon compte », « Mon panier, 1 article »).

## A4. Fiche produit — accordéons

- Tous les blocs d'information de la fiche produit deviennent des **accordéons fermés par défaut**, **sauf « Ingrédients » ouvert par défaut**.
- Implémentation **native `<details>` / `<summary>`** (accessible, sans JavaScript, contenu présent dans le HTML donc indexable par Google). Style aux couleurs de la charte, chevron indiquant l'état.
- Ordre des blocs : Description → **Ingrédients (ouvert)** → Allergènes → Valeurs nutritionnelles → Conditionnement → Conseils de dégustation → Conservation → **Expédition et livraison** (A5).
- Un bloc sans contenu n'est pas affiché (règle existante conservée).

## A5. Fiche produit — bloc « Expédition et livraison »

Ajouter le bloc **après « Conservation »**, en accordéon fermé.
Texte par défaut, **à reprendre mot pour mot**, éditable dans le back-office (paramètre « Texte du bloc expédition », déjà prévu au PLAN §16.3) :

> Livraison en point relais Chronopost disponible pour les produits portant la mention « Livraison possible », en France métropolitaine (hors Corse). Les frais de livraison sont calculés et indiqués lors de la commande.
>
> Afin de préserver au mieux la fraîcheur et la qualité de nos produits, nous vous recommandons de retirer votre colis le jour même de sa mise à disposition au point relais.
>
> Les produits étant alimentaires et périssables, Mon Sans Gluten by Angélique ne peut garantir leurs conditions de conservation en cas de retrait tardif du colis.

**Conséquence** : le texte fait référence à une mention « **Livraison possible** ». → Afficher un **badge « Livraison possible »** (couleur primaire) sur la fiche produit et sur les cartes produits des listes, pour tout produit dont `is_shippable = true`. Les produits non expédiables gardent le comportement prévu (visibles, non commandables, message paramétrable).
→ Mettre à jour `PLAN.md` §5.3 et §6.3 : le principe des produits non expédiables est **validé**.

## A6. Pied de page — « Gérer mes préférences » (cookies) ne fonctionne pas

**Diagnostic à faire avant de corriger** (consigner la cause dans le compte rendu) :
- Le lien appelle-t-il bien l'ouverture du panneau tarteaucitron (`tarteaucitron.userInterface.openPanel()`) ?
- Le lien est-il un `<a href="#">` sans gestionnaire, ou un gestionnaire `onclick` **bloqué par la CSP** (les gestionnaires inline sont bloqués si la CSP n'autorise pas `unsafe-inline`) ?
- tarteaucitron est-il chargé et initialisé au moment du clic (ordre de chargement, `defer`) ?
- Erreur JavaScript dans la console ?

**Correction attendue**
- Bouton `<button type="button">` (pas un lien), gestionnaire attaché en JavaScript (`addEventListener`), **sans handler inline**.
- Si tarteaucitron n'est pas encore prêt, attendre son initialisation avant d'ouvrir le panneau.
- Test manuel : clic → panneau ouvert ; modification du choix → cookies `_ga` posés/supprimés en conséquence.
- Ajouter ce test à `CHECKLIST-PROD.md` §2.

## A7. Frais de port — grille Chrono Relais 13

**Données fournies** : grille **Chrono Relais 13**, « tarification et suppléments inclus », montants **HT**, par poids **jusqu'à** X kg (borne haute incluse) :

| Jusqu'à (kg) | HT | Jusqu'à (kg) | HT |
|---|---|---|---|
| 0,5 | 8,34 € | 10 | 12,03 € |
| 1 | 8,34 € | 11 | 12,63 € |
| 1,5 | 8,75 € | 12 | 13,22 € |
| 2 | 8,75 € | 13 | 13,82 € |
| 3 | 9,16 € | 14 | 14,42 € |
| 4 | 9,57 € | 15 | 15,01 € |
| 5 | 9,98 € | 16 | 15,61 € |
| 6 | 10,39 € | 17 | 16,20 € |
| 7 | 10,80 € | 18 | 16,80 € |
| 8 | 11,21 € | 19 | 17,40 € |
| 9 | 11,62 € | 20 | 17,99 € |

**À faire**
- Back-office « Frais de port » : la saisie se fait **en HT** (champ `price_ht`, centimes). Le prix TTC facturé au client = HT × (1 + taux de TVA du port, paramètre existant). Afficher dans la liste BO les colonnes HT et TTC calculé.
- Migration : ajouter/renommer la colonne en conséquence (nouvelle migration, ne pas modifier l'ancienne), adapter `ShippingCostCalculator` et ses tests.
- **Seeder dédié** `ChronoRelais13RatesSeeder` avec les 22 tranches ci-dessus (tranche n : poids > borne précédente et ≤ borne n ; première tranche : 0 < poids ≤ 0,5 kg).
- Poids > 20 kg → commande impossible (comportement existant « hors grille »).
- Libellé du transporteur affiché au client : « Chronopost — Point relais (Chrono Relais 13) » (éditable).

`À CONFIRMER` par Ian :
- **Taux de TVA appliqué au port** : 20 % par défaut dans le paramètre (à faire valider par le comptable : la TVA du transport peut suivre celle des produits livrés).
- **Refacturation à prix coûtant** : la grille est utilisée telle quelle comme prix client HT (aucune marge ni arrondi ajoutés).

Tests : chaque borne (0,5 / 0,51 / 1 / 1,01 / 20 / 20,01 kg), calcul TTC, poids hors grille.

## A8. Coordonnées, adresse, téléphone, marchés

- **Adresse du laboratoire** : 71 bis rue du Commandant Bindel, 50300 Avranches.
- ⚠️ **Pas d'accueil du public** : l'adresse est affichée (pied de page, page Contact, mentions) **avec la mention « Laboratoire — pas d'accueil du public »**. Aucune mention de type « boutique », « venez nous voir », aucun horaire d'ouverture.
- **Pas de téléphone** pour l'instant : paramètre téléphone **vide** ⇒ **aucun téléphone affiché nulle part** (pied de page, contact, factures, emails, schema.org). L'affichage doit s'activer automatiquement si le paramètre est renseigné plus tard.
- **Pas d'horaires de marchés** pour l'instant : créer une page de contenu **« Où nous trouver »** (module Pages existant), **non publiée** par défaut, et **non affichée dans le menu tant qu'elle n'est pas publiée**. La cliente la remplira (marchés, partenaires).
- **schema.org** : utiliser `Organization` (nom, logo, adresse, URL, lien Facebook), **pas** de `LocalBusiness`/`Bakery` avec horaires d'ouverture (le lieu ne reçoit pas de public). Mettre à jour `PLAN.md` §17.
- Le téléphone **client** reste obligatoire dans le tunnel (SMS Chronopost) — ne pas confondre avec le téléphone de la boulangerie.

## A9. Page « Notre histoire » et slogan

**Slogan officiel** : « Le gluten s'efface, le goût reste. »
- Afficher le slogan sur l'accueil (sous la bannière ou dans le bloc de présentation — éditable en BO).

**Nouvelle page « Notre histoire »** (module Pages, slug `notre-histoire`, publiée, ajoutée au menu principal et au pied de page).
Contenu initial = texte rédigé par Angélique (publication Facebook du 19/09), **repris tel quel**, à mettre en forme en paragraphes (aucune reformulation, aucun ajout inventé) :

> Bienvenue chez Mon Sans Gluten by Angélique !
>
> Pour ceux qui ne me connaissent pas encore, je suis Angélique, boulangère de métier depuis plusieurs années.
>
> Étant devenue allergique au blé, j'ai dû revoir complètement mon alimentation. Et quand on est boulangère et qu'on aime le bon pain, ce n'est pas toujours évident !
>
> C'est comme ça qu'est née l'envie de créer mon propre laboratoire sans gluten, pour continuer à faire ce que j'aime et proposer de bons produits à ceux qui, comme moi, doivent se passer de gluten.
>
> Pains, viennoiseries, biscuits sucrés et salés, gâteaux, tartelettes… Je vous prépare plein de bonnes choses !
>
> Vous pourrez retrouver mes produits sur les marchés, les commander sur mon site internet et également les découvrir chez des professionnels partenaires.
>
> « Le gluten s'efface, le goût reste. »

(Les phrases du post liées à la période de pré-ouverture — « ouverture prévue courant octobre 2026 », « en attendant, je vous partagerai ici… », « merci à tous de me suivre… » — sont **volontairement retirées** car datées. La page reste éditable par la cliente.)

- Title SEO proposé : « Notre histoire — Angélique, boulangère sans gluten à Avranches » ; meta description à partir du 1er et 4e paragraphe (éditable).
- Emplacement pour une photo (upload BO), optionnel.

## Tests Lot A
- Composant bouton : rendu des classes attendues ; aucune autre définition de couleur de bouton (recherche dans les vues).
- Fiche produit : `<details open>` uniquement sur Ingrédients ; bloc Expédition présent après Conservation ; badge « Livraison possible » si expédiable, absent sinon.
- Téléphone vide ⇒ absent du HTML (pied de page, contact) et du JSON-LD.
- Page « Où nous trouver » non publiée ⇒ 404 + absente du menu et du sitemap.
- Frais de port : tests A7.
- Suite complète verte.

## Critères d'acceptation Lot A
- Captures desktop + mobile : en-tête (icônes), fiche produit (accordéons), boutons (normal / survol / focus), pied de page.
- « Gérer mes préférences » ouvre le panneau sur Chrome, Firefox, Safari mobile.

---

# LOT B — Espace professionnels (nouvelle fonctionnalité)

> ⚠️ **Hors périmètre de la V1 initiale.** Ian a demandé d'être prévenu des dépassements : ce lot est significatif (comptes, prix différenciés, facturation B2B). **Découpage recommandé** : B1 (formulaire de demande) rapidement ; B2 à B4 en **V2**, après réponse aux points `À TRANCHER`. **Ne rien implémenter de B2 à B4 avant validation écrite de Ian.**

## Besoin exprimé
- Des professionnels (exemples : boulangeries traditionnelles, restaurants, burgers, pizzerias — à terme, Angélique proposera aussi de la **pâte à pizza crue sans gluten**) peuvent **demander l'ouverture d'un compte pro** via un formulaire (SIRET, etc.) en décrivant leur besoin.
- Une fois leur compte validé, ils se connectent et voient un **tarif remisé**, différent selon leur **catégorie de client**.

## B1. Formulaire « Demande de compte professionnel » (peut être livré tôt)

Page publique `/professionnels` : texte de présentation (éditable BO) + formulaire.

| Champ | Règle |
|---|---|
| Raison sociale | obligatoire |
| SIRET | obligatoire, 14 chiffres, contrôle de format (clé de Luhn) |
| N° TVA intracommunautaire | optionnel, contrôle de format FR |
| Type d'activité | liste : Boulangerie / Pâtisserie, Restaurant, Burger, Pizzeria, Épicerie / commerce, Autre (préciser) — liste éditable en BO |
| Nom, prénom du contact | obligatoires |
| Fonction | optionnel |
| Email professionnel | obligatoire |
| Téléphone | obligatoire |
| Adresse de l'établissement | obligatoire (adresse, CP, ville) |
| Produits qui vous intéressent | cases à cocher : catégories + « Pâte à pizza crue » + « Autre » |
| Volumes / fréquence estimés | texte libre |
| Description du besoin | texte libre, obligatoire, 2000 caractères max |
| Consentement | case obligatoire non cochée : traitement des données pour étudier la demande (lien politique de confidentialité) |

- Anti-spam : champ piège (honeypot) + rate limiting (ex. 3 demandes / heure / IP). Pas de captcha tiers en V1.
- Stockage : table `pro_account_requests` (statut : `pending`, `approved`, `rejected`, + date, commentaire admin).
- Emails : accusé de réception au demandeur ; notification à l'admin.
- Back-office : ressource « Demandes pro » (liste, filtres par statut, détail, actions Approuver / Refuser avec commentaire).
- **En B1 seul**, « Approuver » = changement de statut + email au demandeur (« nous revenons vers vous ») ; la création du compte pro arrive en B2.
- ⚠️ Données personnelles : durée de conservation des demandes refusées `À CONFIRMER` (proposition : 12 mois), à reporter dans la politique de confidentialité.

## B2. Catégories de clients et comptes pro (V2 — `À TRANCHER`)

Proposition de conception (la plus simple) :
- Table `customer_groups` : nom (ex. « Particulier », « Boulangerie », « Restauration »…), remise en % sur le prix public, actif.
- Chaque `user` a un `customer_group_id` (par défaut : Particulier, 0 %).
- Approbation d'une demande B1 → création du compte (ou rattachement si l'email existe déjà), affectation d'une catégorie, **email d'invitation** pour définir le mot de passe (lien signé, expiration 72 h).
- Un compte pro **ne peut jamais** s'auto-attribuer une catégorie : seul l'admin l'affecte.

## B3. Prix différenciés (V2 — `À TRANCHER`)

- Le prix d'un produit pour un client = prix public − remise de sa catégorie (arrondi au centime, règle documentée).
- Calcul **exclusivement côté serveur** (panier, tunnel, Stripe, facture) à partir de la catégorie de l'utilisateur connecté.
- Prix remisé visible **uniquement après connexion** d'un compte pro validé.
- ⚠️ Cache : aucune page avec prix pro ne doit être mise en cache (Cloudflare / cache applicatif) et servie à un autre visiteur. Pages concernées en `Cache-Control: private` pour les utilisateurs connectés.
- ⚠️ Changement de catégorie pendant qu'un panier est ouvert : recalcul à l'affichage suivant.

## B4. Facturation et commandes pro (V2 — `À TRANCHER`)
- Factures pro : raison sociale, SIRET, TVA intracommunautaire, adresse de l'établissement du client.
- ⚠️ **Facturation électronique B2B** : la réforme française impose progressivement la facture électronique entre entreprises (réception puis émission selon la taille de l'entreprise, calendrier 2026-2027). Les ventes aux professionnels sont concernées, contrairement aux ventes aux particuliers. **Obligations et calendrier exacts à valider avec l'expert-comptable avant d'ouvrir les ventes pro en ligne** (plateforme agréée nécessaire ?).

## Points `À TRANCHER` (Lot B) — réponses de Ian attendues
1. Découpage : B1 maintenant, B2-B4 en V2 ?
2. Remise : **un % par catégorie** (simple) ou **prix spécifiques par produit et par catégorie** (plus fin, plus lourd) ?
3. Affichage des prix pour les pros : **HT** ou TTC ?
4. Livraison des pros : Chronopost relais comme les particuliers, **livraison à l'adresse** de l'établissement, ou livraison/retrait organisé par Angélique ?
5. Paiement des pros : Stripe/virement à la commande comme les particuliers, ou **paiement différé** (facture à 30 jours) ?
6. Minimum de commande pour les pros ?
7. Les pros commandent-ils les **mêmes produits** que les particuliers, ou un catalogue dédié (formats pro, pâte à pizza crue) ?
8. Faut-il masquer certains produits aux particuliers (produits réservés aux pros) ?

---

# Mises à jour de documentation attendues
- `PLAN.md` : §5.1 (charte), §5.3 et §6.3 (badge « Livraison possible », accordéons, bloc expédition), §8.3 (grille HT Chrono Relais 13), §16 (pages Notre histoire, Où nous trouver, Professionnels), §17 (schema.org `Organization`), §3.3 (Lot B en V2), §27 (mettre à jour la liste À RENSEIGNER).
- `CHECKLIST-PROD.md` : test « Gérer mes préférences », vérification téléphone absent, badge « Livraison possible ».
- `docs/DECISIONS.md` : accordéons `<details>`, saisie des frais de port en HT.
- `docs/BACKLOG.md` : B2, B3, B4.
