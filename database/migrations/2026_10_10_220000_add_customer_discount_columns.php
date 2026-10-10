<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T27-L6 : remise client en %. Taux réglé sur le compte ; figé sur la
 * commande et sur chaque ligne (remise unitaire) au moment de la commande.
 * `order_items.line_total_ttc` et `orders.subtotal_ttc` restent les montants
 * réellement payés (après remise).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('discount_percent')->default(0)->after('lab_pickup_allowed');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('discount_percent')->default(0)->after('subtotal_ttc');
            $table->unsignedInteger('discount_total_ttc')->default(0)->after('discount_percent');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('unit_discount_ttc')->default(0)->after('unit_price_ttc');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('unit_discount_ttc'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['discount_percent', 'discount_total_ttc']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('discount_percent'));
    }
};
