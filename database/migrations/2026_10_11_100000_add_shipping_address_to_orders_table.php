<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adresse de livraison du client (11/10/2026), distincte de la facturation.
 * Null = identique à la facturation. Sert à proposer les points de retrait
 * proches ; le colis, lui, part toujours en relais ou en point de retrait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_first_name')->nullable()->after('billing_country');
            $table->string('shipping_last_name')->nullable()->after('shipping_first_name');
            $table->string('shipping_line1')->nullable()->after('shipping_last_name');
            $table->string('shipping_postal_code', 10)->nullable()->after('shipping_line1');
            $table->string('shipping_city')->nullable()->after('shipping_postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['shipping_first_name', 'shipping_last_name', 'shipping_line1', 'shipping_postal_code', 'shipping_city']));
    }
};
