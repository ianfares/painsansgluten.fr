# T27-L4 — Suppression du système de redirections (modèle : Haiku 5.5)

Branche : `feature/T27-L4-suppression-redirections`. Lire d'abord `tasks/T27-README.md`.

Contexte : les 12 redirections venaient de l'ancien Shopify, qui n'avait pas de référencement. Ian demande de retirer complètement le système (menu admin compris).

## À faire
1. Supprimer : `app/Filament/Resources/RedirectResource.php` et son dossier `RedirectResource/`, `app/Models/Redirect.php`, `app/Http/Middleware/HandleLegacyRedirects.php`, `database/seeders/RedirectSeeder.php`, la factory éventuelle (`database/factories/RedirectFactory.php`).
2. Retirer l'enregistrement du middleware dans `bootstrap/app.php` et l'appel au seeder dans `database/seeders/DatabaseSeeder.php`.
3. **Nouvelle migration** `drop_redirects_table` (ne pas supprimer l'ancienne migration de création) : `Schema::dropIfExists('redirects')`, `down()` qui recrée la table à l'identique (copier le schéma de `2026_10_05_223152_create_redirects_table.php`).
4. Chercher toute autre référence (`grep -rn -i "redirect" app config routes resources database tests` — attention à ne PAS toucher aux redirections Laravel normales `redirect()`, `->redirect`, `RedirectResponse`, `assertRedirect`).
5. Tests : supprimer ceux qui testaient les redirections (`tests/Feature/Content/ContentPagesTest.php`), mettre à jour `tests/Feature/DatabaseSchemaTest.php`. Ajouter un test : `/admin/redirects` → 404 pour un admin connecté, et la table `redirects` n'existe plus.
6. `PLAN.md` : là où le système de redirections est décrit, ajouter « Supprimé le 10/10/2026 (T27-L4) : l'ancien site n'avait pas de référencement. »
