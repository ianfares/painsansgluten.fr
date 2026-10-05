<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fiche produit complète (PLAN.md §6.2). Montants en centimes (int),
     * jamais de float. Soft delete : un produit référencé dans une commande
     * ne peut jamais être supprimé, seulement désactivé (CLAUDE.md §4.1).
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            // Général
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('reference')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->unsignedInteger('price_ttc');
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->boolean('is_available')->default(false);
            $table->boolean('is_shippable')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(false);
            $table->unsignedSmallInteger('position')->default(0);

            // Composition & allergènes (PLAN §6.2 : 14 allergènes réglementaires)
            $table->longText('ingredients')->nullable();
            $table->json('allergens_contains')->nullable();
            $table->json('allergens_traces')->nullable();
            $table->text('allergen_note')->nullable();

            // Nutrition pour 100 g (PLAN §6.2)
            $table->json('nutrition')->nullable();

            // Conditionnement
            $table->unsignedInteger('net_weight_g')->nullable();
            $table->unsignedInteger('shipping_weight_g')->nullable();
            $table->string('packaging')->nullable();
            $table->string('sale_unit')->nullable();

            // Conseils
            $table->text('tasting_tips')->nullable();
            $table->text('storage')->nullable();
            $table->string('shelf_life')->nullable();

            // SEO
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
