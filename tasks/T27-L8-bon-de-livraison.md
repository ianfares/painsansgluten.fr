# T27-L8 — Bon de livraison PDF + QR code (modèle : Sonnet 5.5)

Branche : `feature/T27-L8-bon-de-livraison`. Dépend de L7b (mode de livraison). Lire `tasks/T27-README.md`.

## À faire
- Action admin « Bon de livraison (PDF) » sur la fiche commande (`OrderResource` / `ViewOrder`) et en action de masse sur la liste (plusieurs BL en un PDF, un par page), disponible pour les statuts payés et suivants (pas en attente de paiement, annulée, échec).
- PDF dompdf (`resources/views/pdf/delivery-note.blade.php`, même charte que `pdf/invoice.blade.php`), A4, **sans aucun prix** :
  - en-tête boutique ; « Bon de livraison » ; n° de commande ; date de commande ;
  - client : nom, prénom, téléphone, email ;
  - livraison : mode + point relais / commerçant / labo (nom + adresse) ;
  - tableau produits : nom, quantité (et poids net si présent) ; total articles ;
  - **en bas : QR code généré automatiquement** reprenant toutes les informations du BL en texte (n° commande, date, client, adresse de livraison, lignes « quantité × produit »). Bibliothèque : `bacon/bacon-qr-code` (déjà présente via Fortify) — l'ajouter en dépendance **directe** dans `composer.json` (même contrainte que la version installée), justification dans la réponse (pas de nouveau paquet). Rendu SVG ou PNG base64 embarqué (dompdf). Vérifier la lisibilité (taille ≥ 3 cm, correction d'erreur M) ; si le texte est trop long pour un QR lisible, tronquer la liste des produits avec « … (voir BL) » — le n° de commande et l'adresse doivent toujours y être.
- Données personnelles : PDF généré à la volée, non stocké, réservé aux admins.

## Tests
Réservé admin ; pas de prix dans le PDF ; contient n° commande, client, produits/quantités ; QR décodable (générer puis vérifier le contenu texte encodé via le service, pas via image) ; action masquée pour une commande non payée ; action de masse.
