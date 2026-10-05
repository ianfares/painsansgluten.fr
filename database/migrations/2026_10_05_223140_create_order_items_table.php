<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ligne de commande : snapshot du produit au moment de l'achat (nom,
     * référence, prix, TVA, poids) — jamais recalculé depuis le produit
     * vivant, qui peut changer ou être supprimé ensuite.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('product_name');
            $table->string('product_reference');
            $table->unsignedInteger('unit_price_ttc');
            $table->decimal('vat_rate', 5, 2);
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('weight_g');
            $table->unsignedInteger('line_total_ttc');
            $table->unsignedInteger('line_total_ht');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
