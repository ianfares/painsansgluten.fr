# CLAUDE.md — Règles permanentes du projet « Mon Sans Gluten by Angélique »

> Ce fichier est lu par Claude Code à chaque session. Il prime sur toute habitude par défaut.
> Document de référence fonctionnel : `PLAN.md`. Tâches : dossier `tasks/`. Recette : `CHECKLIST-PROD.md`.

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
```
TÂCHE : TXX — nom
FAIT : ...
FICHIERS : ...
TESTS : résultat php artisan test / pint / phpstan
À RENSEIGNER / QUESTIONS OUVERTES : ...
RISQUES / POINTS À RELIRE PAR UN HUMAIN : ...
AJOUTÉ AU BACKLOG : ...
```

## 7. Journal de session — OBLIGATOIRE (plusieurs IA travaillent sur ce projet)

Plusieurs IA (plusieurs comptes Claude Code + ChatGPT) interviennent tour à tour sur ce dépôt, en séquentiel. Pour que chaque session reprenne le contexte sans rien redemander à l'humain :

1. **En DÉBUT de session** : lire `docs/JOURNAL.md` en entier (pas seulement ce fichier et PLAN.md) pour savoir où en est le projet, qui a fait quoi, et quelles questions sont restées ouvertes.
2. **En FIN de tâche** : ajouter une entrée dans `docs/JOURNAL.md` (ne jamais réécrire/supprimer les entrées précédentes), en plus du compte rendu §6 donné à l'humain et du commit Git. Format de l'entrée dans `docs/JOURNAL.md` :
```
## [AAAA-MM-JJ HH:MM] — [IA : ex. Claude Sonnet 5 / ChatGPT] — TXX — [titre]
STATUT : terminée / bloquée / partielle
FAIT : ...
FICHIERS : ...
TESTS : ...
À RENSEIGNER / QUESTIONS OUVERTES : ...
RISQUES / À RELIRE PAR UN HUMAIN : ...
AJOUTÉ AU BACKLOG : ...
PROCHAINE TÂCHE SUGGÉRÉE : ...
```
3. Ne jamais traiter une tâche comme commencée si une entrée du journal dit déjà qu'elle est en cours ailleurs — vérifier avant de commencer.
