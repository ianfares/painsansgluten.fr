# T27-L3 — Formulaire pro allégé + purge 3 mois (modèle : Sonnet 5.5)

Branche : `feature/T27-L3-formulaire-pro`. Lire d'abord `tasks/T27-README.md`. Partie données personnelles : tests obligatoires et résumé des risques.

Fichiers : `resources/views/content/pro-request.blade.php`, `app/Http/Requests/Content/StoreProAccountRequest.php`, `app/Http/Controllers/Content/ProAccountRequestController.php`, `app/Models/ProAccountRequest.php`, `app/Filament/Resources/ProAccountRequestResource.php`, mails `app/Mail/*Pro*` et leurs vues, `tests/Feature/Content/*Pro*`.

## À faire
1. **Retirer du formulaire** (vue + règles + contrôleur) :
   - « Produits qui vous intéressent » (cases Pains, Viennoiseries…, `products_of_interest`, `ProAccountRequest::productOptions()`) ;
   - « Volumes / fréquence estimés » (`volumes`) ;
   - « N° de TVA intracommunautaire » (`vat_number`, règle `FrenchVatNumber` — ne pas supprimer la classe de règle si elle sert ailleurs).
2. **Obligatoires uniquement** : nom du contact (`contact_last_name`), prénom du contact (`contact_first_name`), téléphone, email. **Tout le reste devient facultatif** : raison sociale, SIRET (si rempli → toujours validé par la règle `Siret`), type d'activité (si rempli → doit être dans la liste ; « Autre » sans précision reste accepté), poste, adresse, code postal (si rempli → 5 chiffres), ville, description.
   - **Restent obligatoires car légaux / anti-spam** : la case de consentement RGPD et Turnstile. Ne pas y toucher.
   - Mettre à jour les astérisques / mentions « obligatoire » dans la vue.
3. **Migration** (nouvelle, ne pas modifier celle du 09/10) : rendre nullable `company_name`, `siret`, `activity_type`, `address_line1`, `postal_code`, `city`, `description` ; **supprimer** les colonnes `vat_number`, `products_of_interest`, `volumes` (vérifier qu'aucune donnée réelle n'est perdue : 0 demande en préprod au 10/10/2026). `down()` réaliste.
4. **Admin + emails** : retirer ces champs de `ProAccountRequestResource` (fiche, table, filtres) et des emails ; afficher « — » pour les champs vides.
5. **Purge 3 mois** : commande `pro-requests:purge-rejected` qui supprime les demandes au statut **refusé** dont `processed_at` < maintenant − 3 mois ; planifiée `daily()` dans `routes/console.php`. Constante `RETENTION_MONTHS = 3` sur le modèle. Ne jamais toucher aux autres statuts. Ne logger que le nombre supprimé (aucune donnée perso dans les logs).
6. Si une page publique/mention (politique de confidentialité, aide du formulaire) cite une durée de 12 mois dans le **code** (pas dans les pages en base), la passer à 3 mois.

## Tests
- Envoi valide avec seulement nom, prénom, téléphone, email, consentement → demande créée.
- Manque l'un des quatre → erreur. SIRET invalide rempli → erreur. Champs retirés absents de la page.
- Purge : refusée de 3 mois + 1 jour supprimée ; refusée de 2 mois gardée ; en attente/acceptée anciennes gardées ; commande planifiée.
- Adapter les tests existants (TVA, produits, volumes).
