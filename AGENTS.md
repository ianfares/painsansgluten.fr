# CLAUDE.md — Règles permanentes du projet « Mon Sans Gluten by Angélique »

> Ce fichier est lu par Claude Code à chaque session. Il prime sur toute habitude par défaut.
> Document de référence fonctionnel : `PLAN.md`. Tâches : dossier `tasks/`. Recette : `CHECKLIST-PROD.md`. Historique : `docs/JOURNAL.md`.
>
> **`QUALITE.md` est un document complémentaire OBLIGATOIRE, à lire en entier avant toute ligne de code** (sécurité OWASP, performance, tests, bonnes pratiques PHP/Laravel, checklist d'auto-revue). En cas de doute entre les deux fichiers, l'ordre de priorité est : **Sécurité > Exactitude > Non-régression > Performance > Rapidité de livraison** (défini par `QUALITE.md`). Le **format de compte rendu de fin de tâche à utiliser est celui de `QUALITE.md` §10** (plus complet que l'esquisse de ce fichier), à la fois pour l'humain et pour l'entrée dans `docs/JOURNAL.md`.

## 1. Contexte en 5 lignes
- Boutique en ligne sur mesure pour une boulangerie artisanale **100 % sans gluten** à Avranches (Manche).
- Remplace un site Shopify en pré-lancement : https://painsansgluten.fr (design à reproduire, code à **ne pas** réutiliser).
- ~70 produits, 4 catégories : Pains, Viennoiseries, Pâtisseries, Biscuits. **Fabrication à la commande**, produits **périssables**.
- Paiement **Stripe Checkout hébergé** ou **virement** (validé manuellement). Livraison **Chronopost Relais uniquement**, France métropolitaine hors Corse.
- Trois interfaces : **front-office**, **espace client**, **back-office**.

## 2. Stack imposée (ne pas en changer sans validation écrite)
| Couche | Choix |
|---|---|
| Langage | PHP 8.3+ |
| Framework | Laravel (dernière version stable — vérifier au démarrage, la noter dans `docs/DECISIONS.md`) |
| Back-office | Filament (dernière version stable compatible) |
| Front | Blade + Tailwind CSS + Livewire (panier, tunnel) + Alpine.js si besoin |
| BDD | **MariaDB** (pas PostgreSQL, pas SQLite en prod) |
| Paiement | `stripe/stripe-php` — Stripe **Checkout hébergé** (pas de Payment Element, pas de Cashier) |
| PDF | `barryvdh/laravel-dompdf` (pas de navigateur headless) |
| Images | `spatie/laravel-medialibrary` + plugin Filament, conversions WebP |
| Paramètres | `spatie/laravel-settings` + plugin Filament |
| Sitemap | `spatie/laravel-sitemap` |
| Emails | Mailer Laravel → **Brevo** (SMTP relay ou transport API) via **queue** |
| Queue | driver `database` (pas de Redis en V1) |
| Tests | Pest |
| Qualité | Laravel Pint + Larastan (niveau 5 minimum) |
| Cookies | tarteaucitron.js auto-hébergé + Google Consent Mode v2 |
| Analytics | GTM → GA4 |

Toute nouvelle dépendance Composer/NPM doit être **justifiée dans `docs/DECISIONS.md`** (pourquoi, alternative écartée). Pas de package abandonné ou à faible maintenance.

**Sécurité des dépendances — NON NÉGOCIABLE** : avant d'installer ou de monter en version une dépendance (Composer ou NPM), vérifier :
1. Qu'elle n'est pas **obsolète / non maintenue** (dernière release récente, pas de dépréciation annoncée).
2. Qu'**aucune CVE active sans correctif** n'est connue sur la version installée (`composer audit`, `npm audit`, et vérification manuelle si doute — changelog, advisories GitHub).
En cas de CVE active sans fix disponible, ou de doute sérieux sur la maintenance d'un package : **s'arrêter et demander validation à l'humain avant d'installer**, ne jamais installer "en espérant que ça passe". `composer audit` / `npm audit` doivent tourner propres (0 faille critique/haute) avant de clore toute tâche qui touche aux dépendances — résultat à consigner dans `docs/DECISIONS.md`.

## 3. Règles de travail NON NÉGOCIABLES
1. **Ne jamais inventer.** Si une information manque (valeur métier, texte juridique, taux de TVA, identifiant API, comportement Chronopost), tu **t'arrêtes et tu poses la question**, ou tu crées un paramètre/placeholder explicitement marqué `À RENSEIGNER`. Aucune clause juridique, aucun taux, aucun tarif inventé.
2. **Une tâche = une branche** `feature/TXX-nom` depuis `develop`. Tu ne touches qu'aux fichiers du périmètre de la tâche. Pas de refactor opportuniste hors périmètre.
3. **Avant de déclarer une tâche terminée** : `php artisan test`, `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse` doivent passer. Tu donnes le résultat.
4. **Migrations** : jamais modifier une migration déjà mergée dans `develop` ; en créer une nouvelle.
5. **Secrets** : jamais dans Git. Tout dans `.env`, documenté dans `.env.example` avec des valeurs factices.
6. **Argent en centimes (entiers)**. Jamais de float pour un montant. Prix saisis **TTC**.
7. **Fuseau horaire** : `Europe/Paris` partout (config app + calculs de dates).
8. **Le serveur recalcule tout** : prix, TVA, frais de port, total, date d'expédition. Ne jamais faire confiance à une valeur envoyée par le navigateur.
9. **Parties critiques** (auth, paiement, webhooks, commandes, factures, données personnelles) : tests obligatoires + résumé des risques à la fin de la tâche pour revue humaine.
10. **Langue** : interface, emails, messages d'erreur en **français**. Code, noms de tables et de variables en **anglais**.
11. **Périmètre V1 strict.** Toute idée hors V1 → noter dans `docs/BACKLOG.md`, ne pas l'implémenter.
12. Mettre à jour `docs/DECISIONS.md` à chaque choix technique non trivial.

## 4. Avertissements permanents (pièges connus)
- **Webhook Stripe = seule source de vérité du paiement.** Le retour navigateur (`success_url`) n'est jamais une preuve de paiement.
- **Idempotence webhooks** : stocker l'`event.id` Stripe en table avec contrainte UNIQUE ; un événement déjà traité est ignoré (réponse 200).
- **Vérifier le montant** : `amount_total` Stripe doit égaler le total de la commande en BDD, sinon commande en anomalie + log + email admin.
- **Route webhook** : exclue du CSRF ; signature vérifiée avec `STRIPE_WEBHOOK_SECRET`. (Côté infra : exclue du challenge Cloudflare/WAF — géré par Ian.)
- **Facture émise = immuable.** Un remboursement génère un **avoir**. Numérotation séquentielle sans trou, attribuée sous verrou transactionnel.
- **Derrière Cloudflare** : configurer les *trusted proxies* pour obtenir la vraie IP client (rate limiting, logs).
- **Consent Mode v2** : valeurs `denied` par défaut posées **avant** le chargement de GTM. Aucun tag GA4 en dur dans le code.
- **Images uploadées** : redimensionnées + converties WebP ; ne jamais servir l'original de plusieurs Mo.
- **Rattachement commandes invité → compte** : uniquement après **vérification de l'email** du compte.
- **Données personnelles** : jamais dans les logs (pas d'email, téléphone, adresse en clair dans `laravel.log`).
- **`APP_DEBUG=false`** en préprod et prod.
- **Préprod** : `noindex` (meta + `X-Robots-Tag`) + accès protégé.

## 5. Conventions
- Logique métier dans des classes `app/Services/` ou `app/Actions/` testables, pas dans les contrôleurs ni les composants Livewire.
- Statuts de commande : `enum` PHP backed + service de transitions (voir PLAN §9). Aucune modification directe de `status` hors de ce service.
- Nommage routes FR côté public (`/panier`, `/commande`, `/mon-compte`), noms de routes Laravel en anglais (`cart.show`).
- Commits : `TXX: description courte` (ex. `T13: vérification signature webhook Stripe`).
- Branches : `main` (prod) ← `develop` (intégration) ← `feature/*`, `fix/*`.

## 6. Fin de chaque tâche — format de compte rendu attendu
**Voir `QUALITE.md` §10 — c'est le format officiel, ne pas utiliser un format simplifié.** Il couvre en plus : analyse d'impact, sécurité, performance, qualité (pint/phpstan/composer audit), documentation.

## 7. Journal de session — OBLIGATOIRE (plusieurs IA travaillent sur ce projet)

Plusieurs IA (plusieurs comptes Claude Code + ChatGPT) interviennent tour à tour sur ce dépôt, en séquentiel. Pour que chaque session reprenne le contexte sans rien redemander à l'humain :

1. **En DÉBUT de session** : lire `docs/JOURNAL.md` en entier (pas seulement ce fichier et PLAN.md) pour savoir où en est le projet, qui a fait quoi, et quelles questions sont restées ouvertes.
2. **En FIN de tâche** : ajouter une entrée dans `docs/JOURNAL.md` (ne jamais réécrire/supprimer les entrées précédentes), reprenant le **compte rendu complet au format `QUALITE.md` §10** (même contenu que celui donné à l'humain), précédé d'un en-tête `## [AAAA-MM-JJ HH:MM] — [IA : ex. Claude Sonnet 5 / ChatGPT] — TXX` et suivi d'une ligne `PROCHAINE TÂCHE SUGGÉRÉE : ...`. Ne jamais utiliser un format allégé pour cette entrée.
3. Ne jamais traiter une tâche comme commencée si une entrée du journal dit déjà qu'elle est en cours ailleurs — vérifier avant de commencer.

## Workflow local d'abord : synchroniser, travailler en local, puis déployer (obligatoire)

Règle : on ne modifie jamais le serveur directement. Toute modification se fait d'abord sur la copie locale, après l'avoir resynchronisée avec le serveur, puis se déploie sur ordre explicite.

- Alias SSH : `painsansgluten`. Dossier serveur : `/var/www/painsansgluten`. Dossier local correspondant : la racine du projet.
- Vérifier la correspondance dossier local / dossier serveur au premier `rsync -n` (comparer la liste des fichiers) avant toute synchronisation réelle.

### 1. Avant chaque tâche : figer l'état local
- `git status`. S'il y a des changements non commités : `git add -A && git commit -m "avant : <tâche>"`.

### 2. Synchroniser le local depuis le serveur (lecture seule côté serveur)
Toujours simuler d'abord, montrer le résultat à l'utilisateur, puis exécuter. Ne jamais utiliser `--delete`. Lancer chaque commande telle quelle (pas dans un script), pour que la confirmation SSH s'applique.
```
rsync -avzn --itemize-changes --exclude='.git/' --exclude='.backups/' --exclude='.env*' --exclude='wp-config.php' --exclude='opencode.json' --exclude='AGENTS.md' --exclude='CLAUDE.md' --exclude='.gitignore' --exclude='node_modules/' --exclude='vendor/' --exclude='*.log' --exclude='*.bak*' --exclude='*.sql*' --exclude='storage/' painsansgluten:/var/www/painsansgluten/ ./
rsync -avz --exclude='.git/' --exclude='.backups/' --exclude='.env*' --exclude='wp-config.php' --exclude='opencode.json' --exclude='AGENTS.md' --exclude='CLAUDE.md' --exclude='.gitignore' --exclude='node_modules/' --exclude='vendor/' --exclude='*.log' --exclude='*.bak*' --exclude='*.sql*' --exclude='storage/' painsansgluten:/var/www/painsansgluten/ ./
```
- Si le serveur contient des fichiers différents du local : après le commit de l'étape 1, la synchronisation écrase le local. Un conflit entre des changements locaux non déployés et des changements serveur : s'arrêter et demander.
- Puis : `git add -A && git commit -m "sync : état du serveur du <AAAA-MM-JJ>"`. Ce commit est la référence « identique au serveur ».
- Seul le code est synchronisé. Les secrets, la base de données et les fichiers générés restent côté serveur et ne sont jamais copiés sans ordre.

- Laravel : `vendor/` et `storage/` ne sont pas synchronisés. Les dépendances se reconstruisent avec `composer install` ; le déploiement suit `CHECKLIST-PROD.md`.

### 3. Travailler en local uniquement
- Modifier la copie locale. La sauvegarde automatique (`.backups/`) et git protègent chaque fichier modifié.
- Vérifier en local (`php -l`, tests, relecture du `git diff`) avant de proposer un déploiement.

### 4. Déployer vers le serveur (ordre explicite de l'utilisateur à chaque fois)
1. Resynchroniser en simulation (`rsync -avzn` de l'étape 2) : si le serveur a changé depuis le dernier commit « sync », s'arrêter et demander.
2. Lister les fichiers à envoyer : `git diff --name-only <dernier commit sync>`, en retirant `AGENTS.md`, `CLAUDE.md`, `opencode.json`, `.gitignore` et tout fichier de secrets.
3. Sauvegarder sur le serveur, hors du dossier web, les versions actuelles de ces fichiers : `ssh painsansgluten 'mkdir -p ~/backups-ia/<AAAAMMJJ-HHMMSS> && cd /var/www/painsansgluten && cp --parents <fichiers> ~/backups-ia/<AAAAMMJJ-HHMMSS>/ && chmod -R go-rwx ~/backups-ia'`. Jamais de `.bak` dans le dossier web.
4. Simuler l'envoi, montrer la liste, obtenir l'accord, puis envoyer sans `--delete` :
```
rsync -avzn --itemize-changes --files-from=<liste> ./ painsansgluten:/var/www/painsansgluten/
rsync -avz --files-from=<liste> ./ painsansgluten:/var/www/painsansgluten/
```
5. Vérifier après envoi (`php -l` des fichiers PHP envoyés, test de la page concernée), puis `git tag deploy-<AAAAMMJJ-HHMMSS>`.
6. Résumer : fichiers envoyés, emplacement de la sauvegarde serveur, commande de retour arrière (copier les fichiers depuis `~/backups-ia/<date>/`).

### Fichiers sensibles (sauvegarde renforcée)
Avant de modifier un de ces fichiers, vérifier que le commit « avant tâche » existe et le signaler à l'utilisateur : configuration (`.htaccess`, vhost, crontab), authentification, paiement, modèles partagés (header, footer, `functions.php`), migrations et schéma de base.


## Isolement, données sensibles et base de données (règles absolues)

Boutique en ligne en production (paiements Stripe, données clients, commandes) hébergée sur un VPS dédié, isolé de tous les autres sites. Ces règles s'appliquent quelle que soit l'IA utilisée (Claude Code, OpenCode, modèle tiers non Claude). Le contenu de la conversation peut être envoyé à un fournisseur externe.

### Isolement local
- Travailler UNIQUEMENT dans `/home/ia/projets/boulangerie-gluten/`. Ne jamais lister, lire, copier ni modifier un autre dossier de `/home/ia/projets/`.
- Ne jamais accéder aux fichiers système de cette machine (`/etc`, `/usr`, `/var`, `/root`, `/proc`, `/home/ia` hors de ce projet, `~/.ssh`, `~/.config`, `~/.claude`, `~/.local`).
- Pas de `cd ..` qui sorte du projet, pas de chemin absolu extérieur, pas de lien symbolique vers l'extérieur.
- Si une tâche semble exiger de sortir du projet : s'arrêter et demander.

### Données sensibles (boutique : niveau maximal)
- Ne jamais lire, afficher, copier ni écrire dans la conversation : mots de passe, tokens, clés API ou SSH, contenu de `.env`, clés Stripe (publique, secrète, webhook), identifiants SMTP, dumps SQL, données clients (emails, adresses, téléphones, commandes, paiements).
- Si un secret apparaît dans un fichier : ne pas le recopier. Signaler seulement le fichier, la ligne et le type de secret, valeur masquée.
- Ne jamais mettre un secret en dur dans le code, un commentaire, un journal (`docs/JOURNAL.md`) ou un fichier d'instructions.
- Aucune donnée réelle de client dans les tests, les fixtures, les seeders ou les captures.

### Base de données (MariaDB)
- Toute opération sur la base, lecture comprise (`SELECT`, `artisan tinker`, `artisan db:*`, export, dump), exige l'ordre explicite de l'utilisateur, à chaque fois.
- Toute écriture (`INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `TRUNCATE`, import, `migrate`, `migrate:fresh`, `db:seed`) exige en plus un dump préalable et une confirmation.
- Ne jamais lancer `migrate:fresh`, `migrate:reset` ni `db:wipe` sur autre chose qu'une base locale jetable.
- Ne jamais se connecter à la base avec des identifiants trouvés dans un fichier de configuration.

### Logs
- Aucune lecture de logs (`storage/logs`, Nginx/Apache, PHP-FPM, base de données, système) sans ordre explicite. Se limiter au strict nécessaire (extrait ciblé) et ne pas afficher d'IP, d'emails ni de contenus de commandes.

### Paiement
- Ne jamais effectuer d'appel à l'API Stripe en mode live, ni modifier la configuration des webhooks, sans ordre explicite. Les tests se font en mode test uniquement.

## Accès SSH : garde-fous

> **Alias SSH : `painsansgluten`** (défini dans `~/.ssh/config` : clé dédiée, port non standard). Dossier du site sur le VPS : `/var/www/painsansgluten`.
> **Seul serveur autorisé pour ce projet : le VPS de la boutique, via son alias dédié uniquement.**
> Garde-fous techniques : le hook `ssh-guard` (Claude Code) bloque la lecture de `~/.ssh/`. La configuration OpenCode (globale et `opencode.json` de ce projet) refuse l'accès hors du projet et limite SSH à l'alias de ce projet. Ce sont des règles de permission, pas un bac à sable : ne pas chercher à les contourner, s'arrêter et demander.

### Portée de connexion
- Se connecter UNIQUEMENT à l'hôte défini pour ce projet, avec l'alias et la clé prévus. Aucun autre alias ni aucune autre clé.
- Ne jamais rebondir vers un autre serveur depuis cette connexion sans confirmation explicite.

### Avant toute commande distante
- Vérifier sur quel serveur on agit (`hostname`, `pwd`, `whoami`) en début de session SSH.
- Rester dans le dossier du site. Ne jamais lister, lire ni copier un fichier en dehors.

### TOUJOURS demander confirmation avant, même à distance
- Toute commande de suppression (`rm`, `DROP`, `DELETE`, `TRUNCATE`).
- Toute modification de configuration serveur (vhost, Nginx/Apache, `.htaccess`, crontab, service, worker de file d'attente).
- Tout redémarrage de service (Nginx/Apache, PHP-FPM, MariaDB, supervisor, Redis).
- Toute commande impliquant `sudo`.
- Tout déploiement, toute commande `artisan` qui écrit (`migrate`, `queue:*`, `config:cache`, `down`/`up`) et toute modification de `.env`.

### Jamais, via SSH
- Ne jamais afficher le contenu d'un fichier de credentials ou de `.env`, même en debug.
- Ne jamais copier un fichier sensible vers un autre emplacement sans confirmation explicite.
- Ne jamais désactiver un pare-feu, fail2ban ou un module de sécurité à distance.
- Ne jamais modifier un fichier système en dehors du dossier du site (`/etc`, `/usr`, `/lib`, configuration globale du serveur web ou de PHP-FPM, réseau, services systemd), ni `/etc/passwd`, `/etc/group`, `/etc/sudoers`, ni le crontab système.
- Ne jamais installer, mettre à jour ou supprimer un paquet système (`apt`) depuis une session sur ce projet.
- Ne jamais initier de connexion sortante non nécessaire (téléchargement de script distant, service tiers) sans confirmation préalable.
- Ne jamais laisser une session SSH interactive ouverte plus longtemps que nécessaire.
- Si une tâche semble nécessiter une modification système, s'arrêter et signaler que ça sort du périmètre de ce projet.

### Traçabilité
- Résumer en fin de tâche les commandes exécutées à distance qui ont modifié quelque chose.
