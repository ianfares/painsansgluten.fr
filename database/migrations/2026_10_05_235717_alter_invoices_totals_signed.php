<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `invoices.total_ht/total_vat/total_ttc` ont été créées en `unsignedInteger`
 * (T02), avant que T16 n'introduise les avoirs à montants **négatifs**
 * (PLAN.md §12). Migration séparée plutôt que de modifier
 * `create_invoices_table`, déjà mergée dans `develop` (CLAUDE.md §3.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE invoices MODIFY total_ht INT NOT NULL');
        DB::statement('ALTER TABLE invoices MODIFY total_vat INT NOT NULL');
        DB::statement('ALTER TABLE invoices MODIFY total_ttc INT NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE invoices MODIFY total_ht INT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE invoices MODIFY total_vat INT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE invoices MODIFY total_ttc INT UNSIGNED NOT NULL');
    }
};
