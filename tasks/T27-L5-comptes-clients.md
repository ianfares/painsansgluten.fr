# T27-L5 — Comptes clients : particulier/pro, désactivation, création manuelle (modèle : Sonnet 5.5, relecture Opus)

Branche : `feature/T27-L5-comptes-clients`. Lire d'abord `tasks/T27-README.md`. **Partie critique (auth + données personnelles)** : tests obligatoires + résumé des risques.

Existant : `app/Models/User.php` (`first_name`, `last_name`, `phone`, `email`, `deletion_requested_at`), inscription Fortify `app/Actions/Fortify/CreateNewUser.php` + `resources/views/auth/register.blade.php`, connexion `app/Providers/FortifyServiceProvider.php`, admin `app/Filament/Resources/UserResource.php` (lecture seule aujourd'hui), espace client `app/Http/Controllers/Compte/AccountController.php`, demande pro B1 (`ProAccountRequest`, inchangée).

## 1. Deux types de compte à l'inscription (`/inscription`)
- Choix en haut du formulaire : « Particulier » / « Professionnel » (radio, particulier par défaut ; Alpine pour afficher les champs pro).
- Particulier, obligatoires : nom, prénom, téléphone, email (+ mot de passe). C'est déjà le cas.
- Pro, obligatoires en plus : **raison sociale** et **SIRET** (règle existante `App\Rules\Siret`, SIRET normalisé sans espaces).
- Migration `users` : `account_type` (string 20, défaut `individual` ; valeurs `individual`/`pro` via un enum backed `AccountType`), `company_name` nullable, `siret` (14) nullable, `pro_status` nullable (`pending`/`approved`, enum `ProStatus`), `pro_approved_at` nullable, `lab_pickup_allowed` bool défaut false, `deactivated_at` nullable. (La colonne de remise viendra en L6 : ne pas la créer ici.)
- Inscription pro → `account_type=pro`, `pro_status=pending`. Email à l'admin (adresse `shop.admin_notification_email`) « Nouveau compte pro à valider ».
- Tant que `pro_status !== approved` : le compte se comporte comme un particulier. Méthode `User::isApprovedPro(): bool`.
- Espace client « Mes informations » : afficher le type de compte et, pour un pro, raison sociale / SIRET / statut (« en attente de validation » ou « validé »). Raison sociale et SIRET non modifiables par le client une fois validé (sinon re-validation).

## 2. Admin `UserResource`
- La ressource devient éditable (création + édition) **sauf** email et mot de passe en édition : changer l'email d'un client = hors périmètre (il le fait lui-même avec re-vérification).
- Colonnes / filtres : type de compte, statut pro, désactivé, demande de suppression.
- Actions sur la fiche :
  - « Valider le compte pro » (si pending) → `pro_status=approved`, `pro_approved_at`, email au client « Votre compte professionnel est validé ».
  - case « Retrait au laboratoire autorisé » (`lab_pickup_allowed`), modifiable seulement pour un pro validé (sert en L7b).
  - **« Désactiver le compte »** (confirmation obligatoire) → `deactivated_at=now()`, `deletion_requested_at=null` (la demande est traitée), sessions du client supprimées (table `sessions` où `user_id` = id). Aucune donnée effacée : commandes et factures restent.
  - **« Réactiver le compte »** → `deactivated_at=null`.
- **Création manuelle** (« Nouveau client ») : type, nom, prénom, téléphone, email (unique), raison sociale + SIRET si pro (pro créé par l'admin = directement `approved`). **Aucun champ mot de passe** : on enregistre un mot de passe aléatoire inutilisable (`Hash::make(Str::random(64))`), `email_verified_at=now()` (l'admin a la relation client), puis on envoie le lien de réinitialisation Fortify (`Password::broker()->sendResetLink`) avec un email dédié « Votre compte a été créé — choisissez votre mot de passe » (Mailable en queue, texte en français).

## 3. Compte désactivé
- Connexion refusée : dans `Fortify::authenticateUsing` (ou équivalent existant), si `deactivated_at` non null → échec avec message « Ce compte est désactivé. Contactez-nous via le formulaire de contact. » (même délai/limitation que les autres échecs).
- Middleware sur l'espace client : un client déjà connecté puis désactivé est déconnecté à la requête suivante.
- Réinitialisation de mot de passe : ne débloque pas un compte désactivé.
- Le compte désactivé ne peut pas passer commande connecté (déconnecté par le middleware) ; il peut commander en invité comme n'importe qui (pas de blocage par email : hors périmètre, à noter dans la réponse).

## Tests (Pest)
Inscription particulier / pro (champs obligatoires, SIRET invalide) ; pro en attente ≠ pro validé ; validation admin + email ; désactivation (login refusé, session coupée, demande de suppression effacée, commandes intactes) ; réactivation ; création manuelle (email de choix du mot de passe envoyé, pas de mot de passe saisi, email unique) ; un client ne peut pas accéder à l'admin ; `LivewireRoundTripTest` : ajouter `/admin/users` et `/admin/users/create`.
