# T16 — Factures et avoirs PDF
**Dépend de** : T12, T04  **Branche** : `feature/T16-invoices`  **Réf.** : PLAN §12  **CRITIQUE — revue humaine + validation comptable**

## À faire
- Listener `OrderPaid` → `IssueInvoiceAction` : numéro via `invoice_sequences` sous verrou, snapshot JSON, PDF dompdf en stockage privé.
- `OrderRefunded` → `IssueCreditNoteAction` (avoir lié à la facture).
- Gabarit PDF : PLAN §12 (vendeur, client, lignes HT, TVA par taux, totaux, port, paiement, mentions).
- Téléchargement : client propriétaire (policy), invité via URL signée 30 j, admin.
- Ressource Filament Factures & avoirs + **export comptable CSV** par période (PLAN §14).
- Règle d'arrondi documentée dans DECISIONS.

## ⚠️ Avertissements
- Aucune édition de facture possible. Régénération du PDF uniquement depuis le snapshot.
- Mentions, TVA, format des numéros : `À VALIDER` par le comptable — utiliser les paramètres T04, ne rien inventer.

## Tests
Séquence sans trou (y compris 2 paiements simultanés simulés), snapshot inchangé après modification du produit/client, avoir, accès refusé à un tiers, export CSV correct.
