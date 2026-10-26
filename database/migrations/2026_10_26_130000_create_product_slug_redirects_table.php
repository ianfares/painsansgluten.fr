<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anciennes adresses de fiches produit (audit du 26/10/2026) : quand le slug
 * d'un produit change, l'ancien est gardé ici et redirigé en 301 vers le
 * nouveau, pour ne perdre ni les liens existants ni le référencement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_slug_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('old_slug')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_slug_redirects');
    }
};
