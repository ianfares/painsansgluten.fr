# T25 — Déploiement préproduction / production
**Dépend de** : T24  **Réf.** : PLAN §4, §24  **Infra gérée par Ian**

## À faire (Claude Code)
- `docs/DEPLOY.md` : procédure de déploiement pas à pas :
  ```
  git pull origin main
  composer install --no-dev --optimize-autoloader
  npm ci && npm run build
  php artisan migrate --force
  php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
  php artisan storage:link   # premier déploiement
  php artisan queue:restart
  ```
- Exemple de config Supervisor (`queue:work --tries=3 --timeout=90`) et ligne cron du scheduler.
- Exemple de bloc Nginx (root `public/`, PHP-FPM, taille d'upload, assets en cache long).
- Script de retour arrière (checkout du tag précédent + `migrate:rollback` si migration réversible — ⚠️ jamais automatique sur des données de prod sans sauvegarde).

## Côté Ian
Serveur, DNS, Cloudflare, certificats, sauvegardes, supervision, webhook Stripe (URL + exclusion WAF), Brevo (SPF/DKIM/DMARC), Search Console.
