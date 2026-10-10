# T27-L1 — Retouches visuelles (modèle : Haiku 5.5)

Branche : `feature/T27-L1-retouches-visuelles`. Lire d'abord `tasks/T27-README.md` (règles communes).

## À faire
1. **Accueil, bannière** — `resources/views/catalog/home.blade.php` ~l.13-15 : supprimer le badge `<span>` « 🌾 100 % sans gluten · fait main à Avranches ».
2. **En-tête** — `resources/views/components/site/header.blade.php` ~l.37 : supprimer le `<span>` « 100 % sans gluten » sous le logo. Le nom de la boutique reste.
3. **Étiquette de la bannière** — `home.blade.php` ~l.71-73 : l'étiquette « 📦 Expédié frais, à retirer en relais » devient un texte réglable :
   - nouveau réglage `homepage.banner_badge_text` (`?string`) dans `app/Settings/HomepageSettings.php` ;
   - nouvelle migration de settings dans `database/settings/` (nom daté `2026_10_10_...`) qui l'ajoute avec la valeur **« Création à venir »** ;
   - champ `TextInput::make('banner_badge_text')->label('Étiquette sur l\'image')->helperText('Laisser vide pour masquer l\'étiquette.')->maxLength(60)` dans la section « Bannière d'accueil » de `app/Filament/Pages/ManageHomepageSettings.php` ;
   - la vue affiche `{{ $homepage->banner_badge_text }}` seulement s'il est rempli (pas d'emoji imposé).
4. **Menu du haut** — `header.blade.php` : retirer de la nav desktop (~l.97-101) **et** du menu mobile (~l.125-129) les liens des pages menu (Notre histoire, Où nous trouver), FAQ et Contact. Ils restent dans le pied de page (ne pas toucher au footer). Retirer `menuPages` et `MENU_PAGE_SLUGS` de `app/View/Composers/HeaderComposer.php` devenus inutiles.
5. **Boutons (contraste ocre)** — `resources/css/app.css`, `@layer components` : le blanc sur ocre fait 3,6:1, ce qui n'est valable que pour du « grand texte » WCAG (≥ 18,66 px en gras). Passer `font-size` de `.btn` (et `.auth-card button`) à `1.1875rem` (19 px), gras conservé. Vérifier `.btn-sm` et `.btn-outline` : toute variante qui prend le fond ocre au survol doit aussi avoir ≥ 1.1875rem en gras (réduire le padding de `.btn-sm` si besoin pour garder un bouton compact). Mettre à jour le commentaire au-dessus de `.btn`.
6. `npm run build` pour vérifier que le CSS compile (le build va dans `public/build` partagé, c'est voulu).

## Tests
- Adapter les tests existants qui vérifiaient « 100 % sans gluten », « Expédié frais », ou les liens FAQ/Contact/pages dans l'en-tête (`tests/Feature/Site/`, `tests/Feature/T26/`). Le test « le lien Contact de l'en-tête pointe vers la vraie page de contact » de `PublicLayoutTest` doit désormais vérifier le lien Contact **du pied de page**.
- Nouveaux tests : l'étiquette réglable s'affiche, vide = masquée ; l'en-tête ne contient plus FAQ/Contact/Notre histoire mais le pied de page oui.
