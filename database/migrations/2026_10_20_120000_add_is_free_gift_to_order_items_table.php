<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ligne « pâtisserie offerte » (20/10/2026) : ligne à 0 €, sans produit du
 * catalogue, repérée par ce drapeau (affichage « Offert », exclue de Stripe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_free_gift')->default(false)->after('product_reference');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('is_free_gift'));
    }
};
