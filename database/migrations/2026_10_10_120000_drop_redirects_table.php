<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suppression du système de redirections (T27-L4, décision du 10/10/2026) :
     * l'ancien site Shopify n'avait pas de référencement, la table n'a plus d'usage.
     * La migration de création `2026_10_05_223152_create_redirects_table` reste
     * inchangée (historique des migrations).
     */
    public function up(): void
    {
        Schema::dropIfExists('redirects');
    }

    /**
     * Recrée la table à l'identique de la migration de création (données perdues).
     */
    public function down(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source')->unique();
            $table->string('target');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
