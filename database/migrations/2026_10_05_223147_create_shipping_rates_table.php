<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grille des frais de port par tranche de poids (PLAN.md §8.3).
     * Grille vide = tunnel de commande bloqué (CLAUDE.md : ne jamais
     * appliquer 0 € par défaut si la grille n'est pas renseignée).
     */
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('min_weight_g');
            $table->unsignedInteger('max_weight_g');
            $table->unsignedInteger('price_ttc');
            $table->timestamps();

            $table->index(['min_weight_g', 'max_weight_g']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};
