# T04 — Paramètres boutique et apparence
**Dépend de** : T02, T03  **Branche** : `feature/T04-settings`  **Réf.** : PLAN §8, §11, §12, §15, §16.3

## À faire
`spatie/laravel-settings` + pages Filament « Paramètres » :
- **Boutique** : nom, coordonnées, lien Facebook, emails expéditeur / réponse / notification admin.
- **Facturation** : raison sociale, forme, capital, adresse, SIRET, RCS, TVA intra, mentions de pied de facture, formats de numérotation.
- **Virement** : titulaire, IBAN, BIC, banque, délai d'annulation (défaut 5), annulation auto on/off.
- **Expédition** : jours d'expédition, heure limite, délai de fabrication, URL de suivi (modèle avec `{tracking}`), code produit Chronopost, quantité max par ligne, textes (bloc expédition des fiches, message non expédiable, message retrait relais).
- **Frais de port** : CRUD `shipping_rates` + franco (montant, actif) + taux TVA du port.
- **Jours fermés** : CRUD `closed_dates`.
- **Accueil / Apparence** : logo, favicon, bandeau d'annonce (texte, actif), bannière (image, titre, sous-titre, bouton), produits mis en avant, texte de présentation.
- **SEO global** : title et description par défaut, image Open Graph par défaut.

## ⚠️ Avertissements
- Champs non renseignés affichés en alerte sur le tableau de bord BO (« Configuration incomplète : … »).
- Aucune valeur métier par défaut inventée (sauf délai virement = 5, quantité max = 20, explicitement validés).
- IBAN : validation de format.

## Critères d'acceptation
- Tous les paramètres modifiables et relus correctement par le code (helper/service unique).
