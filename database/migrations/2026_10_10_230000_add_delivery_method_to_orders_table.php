<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T27-L7b : mode de livraison. Les commandes existantes sont toutes en
 * Chronopost Relais. Pour un retrait, les colonnes `relay_*` gardent le
 * snapshot du point (nom, adresse, horaires) : une seule structure à lire
 * partout (emails, BL, espace client).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_method', 30)->default('chronopost_relay')->after('payment_method')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('delivery_method'));
    }
};
