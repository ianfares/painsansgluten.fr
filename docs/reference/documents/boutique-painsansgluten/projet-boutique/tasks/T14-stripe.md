# T14 — Paiement Stripe Checkout et webhooks
**Dépend de** : T12, T13  **Branche** : `feature/T14-stripe`  **Réf.** : PLAN §10  **CRITIQUE — revue humaine obligatoire**

## À faire
- `StripeCheckoutService::createSession(Order)` : lignes produits + port depuis la commande, EUR, `locale fr`, `client_reference_id`, metadata, `expires_at` 30 min, success/cancel URLs.
- Reprise du paiement pour une commande `pending_payment` / `payment_failed`.
- Route `POST /webhooks/stripe` : hors CSRF, vérification de signature, table `stripe_events` (UNIQUE), traitement transactionnel + verrou.
- Événements : PLAN §10 (tableau).
- Contrôle `amount_total` / devise vs commande → sinon `payment_anomaly` + log + email admin.
- Au `paid` : date d'expédition figée (T09), facture (T16) et emails (T19) en queue.
- Confirmation : poll Livewire tant que la commande n'est pas `paid` (max 30 s).
- Remboursement total depuis le BO : `refunds.create` avec clé d'idempotence.

## ⚠️ Avertissements
- Le `success_url` ne change **jamais** le statut.
- Répondre 200 rapidement ; tout le lourd en queue.
- Clés test en local/préprod ; clés live uniquement en prod.

## Tests
PLAN §25 « Stripe » (fixtures d'événements signés).

## Critères d'acceptation
- Paiement test réel via `stripe listen` en local : commande `paid`, facture et emails générés une seule fois.
