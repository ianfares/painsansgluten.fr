# T27 — Retours client du 10/10/2026 : découpage en lots

Source : compte rendu de Ian du 10/10/2026 (copié dans la conversation), validé point par point.
Une fiche par lot : `tasks/T27-Lx-*.md`. Chaque fiche se suffit à elle-même.

| Lot | Fiche | Modèle | Dépend de |
|---|---|---|---|
| L1 | Retouches visuelles | Haiku 5.5 | — |
| L2 | Fiche produit (TTC, prix/kg, accordéon description) | Haiku 5.5 | — |
| L3 | Formulaire pro allégé + purge 3 mois | Sonnet 5.5 | — |
| L4 | Suppression du système de redirections | Haiku 5.5 | — |
| L5 | Comptes clients (particulier/pro, désactivation, création manuelle) | Sonnet 5.5 + relecture Opus | — |
| L6 | Remise client en % | Opus 5.5 | L5 |
| L7a | Points de retrait commerçants (admin + géocodage) | Sonnet 5.5 | — |
| L7b | Modes de retrait dans la commande (commerçant ≤ 50 km, labo pro) | Opus 5.5 | L5, L7a |
| L8 | Bon de livraison PDF + QR code | Sonnet 5.5 | L7b |
| L9 | Tableau « À produire » + export PDF | Sonnet 5.5 | L7b |
| L10 | Commande manuelle dans l'admin | Opus 5.5 | L5, L6, L7b |

## Décisions validées par Ian (10/10/2026)

- Pages « Notre histoire » → « Mon histoire » : renommage du **titre** dans Admin → Pages (aucun code, URL inchangée).
- Prix de livraison : montants exacts conservés (pas d'arrondi).
- Contraste ocre : texte des boutons légèrement grossi (L1).
- Plan du site : gabarit maison validé (la librairie spatie/laravel-sitemap n'a jamais été installée) — à noter dans DECISIONS.
- Demandes pro refusées : conservées **3 mois** puis supprimées (L3).
- Système de redirections Shopify : **supprimé** (pas de SEO sur l'ancien site) (L4).
- Remise % : sur les **produits seulement** (pas la livraison), tout type de compte, prix barré + remisé **partout quand le client est connecté**, s'applique aussi aux commandes manuelles.
- Compte pro à l'inscription : créé immédiatement mais **« pro en attente »** ; il commande au prix public sans remise ni retrait labo ; email au client quand l'admin valide. Le formulaire `/professionnels` reste (simple prise de contact).
- Retrait au labo : **réservé aux pros**, activé **compte par compte** dans l'admin ; adresse = adresse boutique des réglages + texte d'instructions modifiable ; gratuit ; même date de préparation que Chronopost.
- Retrait commerçant : gratuit ; seuls les commerçants à **≤ 50 km à vol d'oiseau de l'adresse saisie par le client** sont proposés ; géocodage Base Adresse Nationale ; adresse introuvable → option masquée, Chronopost seul (ne jamais bloquer une commande).
- Retraits : statuts « Prête au retrait » (email au client avec adresse + horaires) puis « Retirée ».
- Désactivation de compte : connexion bloquée, aucune donnée effacée, clôt la demande de suppression en attente ; bouton « Réactiver ».
- Client créé à la main : reçoit un email pour choisir son mot de passe (jamais de mot de passe fixé par l'admin).
- Commande manuelle : frais de port calculés normalement ; le client reçoit l'email de confirmation avec lien de paiement carte ou infos de virement ; bouton « Valider sans paiement (avoir) » avec confirmation + référence/motif de l'avoir, mentionnée sur la facture.
- Tableau « À produire » : filtre sur la **date d'expédition prévue** ; total par produit + détail par commande ; export PDF.
- Prix au kilo : **calculé automatiquement** (prix TTC ÷ poids net), lecture seule dans l'admin.
- À faire valider par le comptable du client : présentation de la remise sur facture, mention « Réglé par avoir ».

## Règles communes à tous les lots (à respecter à la lettre)

1. Lire `CLAUDE.md` et `QUALITE.md` (au moins §1 à §10) avant de coder. Interface en français, code en anglais, argent en centimes.
2. **Ne lancer aucun sous-agent.**
3. Branche `feature/T27-Lx-nom` créée depuis `develop`. Ne toucher qu'aux fichiers du périmètre. Ne jamais modifier une migration existante : en créer une nouvelle.
4. Travail dans un worktree git : avant toute commande PHP, depuis la racine du worktree :
   `ln -s /home/ia/projets/boulangerie-gluten/vendor vendor; ln -s /home/ia/projets/boulangerie-gluten/node_modules node_modules; cp /home/ia/projets/boulangerie-gluten/.env .env; mkdir -p public && ln -s /home/ia/projets/boulangerie-gluten/public/build public/build`
   (ne jamais committer ces liens ni `.env` — ils sont dans `.gitignore`, vérifier avec `git status`).
5. Tests : **toujours** `flock /tmp/claude-1000/boulangerie-testdb.lock php artisan test --compact` (base de test partagée, jamais `--parallel`). Puis `./vendor/bin/pint --test` et `./vendor/bin/phpstan analyse --memory-limit=1G`. Les trois doivent passer.
6. Ne pas toucher à `docs/JOURNAL.md` ni `docs/DECISIONS.md` (le pilote les met à jour) : mettre le compte rendu (format `QUALITE.md` §10) dans la réponse finale.
7. Commit(s) `T27-Lx: description`. **Ne pas merger, ne pas pousser, ne pas déployer.**
8. En cas de doute métier : ne rien inventer, s'arrêter et le signaler dans la réponse.
