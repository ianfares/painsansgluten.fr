# Audit de sécurité — 2026-10-09

Audit demandé par Ian : revue complète du code + tests d'intrusion légers.
Méthode : 3 revues indépendantes (accès et droits ; argent, commandes et paiement ; entrées, sorties et configuration), en lecture du code et requêtes sur le serveur local ; vérification de chaque constat important par une relecture du code ; tests externes de la préprod (protection par mot de passe, fichiers exposés) ; recherche de secrets dans tout l'historique Git.
Chaque faille corrigée a un test de non-régression : `tests/Feature/Security/AuditFixesTest.php`.

**Aucune faille critique.** Pas d'injection SQL, pas de XSS exploitable par un visiteur, pas d'accès aux commandes ou factures d'un autre client, montants toujours recalculés par le serveur, webhook Stripe signé et idempotent, aucun secret dans Git.

## Corrigé

| # | Gravité | Constat | Correction |
|---|---|---|---|
| 1 | Haute | **Double paiement non détecté** : chaque clic sur « payer » ouvrait une nouvelle session Stripe sans fermer la précédente ; un 2e paiement (ou un paiement sur une commande annulée) était encaissé en silence. | Les sessions ouvertes sont expirées chez Stripe avant d'en ouvrir une nouvelle ; si l'une a déjà été payée, aucune nouvelle session. Tout paiement reçu sur une commande déjà payée/annulée → anomalie + email admin « à rembourser ». |
| 2 | Haute | **Tunnel contournable** : l'action « payer » ne revalidait que les CGV ; un appel direct permettait une commande avec email/téléphone invalides, champs vides ou relais en Corse. | Revalidation complète dans `pay()` ; étape du tunnel verrouillée (`#[Locked]`). |
| 3 | Moyenne | **Erreur 500** sur le panier et le tunnel si un produit du panier est supprimé. | Ligne retirée du panier sans erreur. |
| 4 | Moyenne | **HT/TVA faux** : port taxé au taux moyen des produits ; port absent du récapitulatif TVA de la facture. | HT = HT des lignes + HT du port à son propre taux ; port ajouté au récapitulatif par taux. ⚠️ À faire valider par le comptable. |
| 5 | Moyenne | **Double commande** possible (double clic, deux onglets) : panier non verrouillé. | Verrou sur le panier ; la seconde validation affiche un message. |
| 6 | Moyenne | **Événement Stripe perdu** : toute erreur d'unicité dans le traitement répondait 200 (Stripe ne renvoyait jamais). | Seul l'identifiant d'événement est testé en doublon (`insertOrIgnore`) ; toute autre erreur annule et Stripe réessaie. |
| 7 | Moyenne | **Remboursement** : pas de vérification d'état avant l'appel Stripe ; erreur brute dans l'admin en cas de double clic. | État relu avant tout appel ; message clair dans l'admin. |
| 8 | Moyenne | **Injection de formules** dans l'export comptable CSV (nom client commençant par `=`). | Cellule neutralisée par une apostrophe. |
| 9 | Basse | **Envoi d'emails en masse** à des tiers via des commandes répétées. | 3 commandes max / 10 min par adresse email. |
| 10 | Basse | **Donnée personnelle dans les logs** (email client) quand la livraison est mal configurée. | Email retiré du log. |
| 11 | Basse | **SVG accepté** pour logo/favicon/images (code possible dans un SVG, admin uniquement), pas de taille max. | PNG/JPEG/WebP (+ ICO pour le favicon) uniquement, 1 à 10 Mo max. |
| 12 | Basse | **Doublon de facture** possible en base (aucune contrainte). | Index unique (commande, type). |
| 13 | Basse | **Annulation automatique des virements** interrompue par une seule commande en erreur. | Chaque commande traitée indépendamment. |
| 14 | Basse (préprod) | Exception « logo » de la protection par mot de passe détournable avec `/storage/branding/../../…`. | Règle restreinte aux noms d'image simples (`public/.htaccess` du serveur). |
| 15 | Basse (préprod) | Sauvegardes de base créées lisibles par tous (dossier protégé). | Fichiers en 600. |

## Reste à faire — tâche T23 (à valider par Ian)

| Gravité | Constat |
|---|---|
| Moyenne | **Proxies de confiance Cloudflare non configurés** : le site voit l'IP de Cloudflare, pas celle du visiteur. Les limites par IP (formulaire de contact : 5/min) s'appliquent donc à tous les visiteurs à la fois. Indispensable maintenant que la préprod est derrière Cloudflare. |
| Moyenne | **Aucun en-tête de sécurité** (HSTS, X-Frame-Options, nosniff, Referrer-Policy, CSP) ; `X-Powered-By: PHP` exposé. |
| Moyenne | **Cookie de session sans l'option Secure** (`SESSION_SECURE_COOKIE` non défini). |
| Basse | Pas de limite de débit sur l'inscription et le mot de passe oublié (protégés par Turnstile), ni sur le panier. |

## Accepté / à décider (pas de correction prévue en V1)

- Inscription : un message indique si l'email a déjà un compte (comportement standard, confort client).
- Changement de mot de passe : les autres sessions ne sont pas déconnectées (Fortify par défaut).
- Mot de passe client : 8 caractères minimum (recommandations actuelles respectées ; 12 pour les admins).
- Un seul niveau d'admin : tout admin peut tout faire, y compris gérer les autres admins (2FA et rôles reportés en V2).
- Redirections héritées (301) : la cible n'est pas contrôlée (saisie par un admin uniquement).
- Contenu riche (descriptions, FAQ, pages) affiché en HTML : nettoyé par HTML Purifier (configuration par défaut), saisi par un admin uniquement.
