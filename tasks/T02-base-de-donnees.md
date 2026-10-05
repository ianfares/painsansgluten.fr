# T02 — Modèle de données, migrations, seeders
**Dépend de** : T00  **Branche** : `feature/T02-database`  **Réf.** : PLAN §22, §6, §9, §12

## Objectif
Créer toutes les tables V1, les modèles Eloquent, les enums et les relations.

## À faire
- Migrations selon PLAN §22 (affiner si nécessaire, documenter les écarts dans DECISIONS).
- Index : slugs, `reference`, `orders.number`, `orders.token`, `orders.status`, `orders.planned_ship_date`, `stripe_events.event_id` (UNIQUE), `invoices.number` (UNIQUE).
- Enums PHP : `OrderStatus`, `PaymentMethod`, `InvoiceType`, `Allergen` (14 valeurs + libellés FR).
- Modèles + relations + casts (montants int, json, dates).
- Factories pour tous les modèles (tests).
- Seeders : 4 catégories ; 6 pages légales/contact avec placeholder « Page en cours de rédaction » ; redirections Shopify (PLAN §23) ; taux TVA disponibles (liste paramétrable, valeurs **à confirmer**).
- Seeder de démo séparé (`DemoSeeder`, jamais lancé en prod) : produits fictifs pour le développement.

## ⚠️ Avertissements
- Montants en centimes `unsignedInteger`/`integer`, jamais `float`.
- Snapshots de commande et facture : colonnes dédiées / JSON, pas de dépendance aux données vivantes.

## Tests
- Migrations up/down OK ; factories valides ; relations testées.

## Critères d'acceptation
- `php artisan migrate:fresh --seed` sans erreur.
