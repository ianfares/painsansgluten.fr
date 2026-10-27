<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commandes refusées dans le tunnel pour minimum non atteint (27/10/2026,
 * demande d'Angélique) : compteur anonyme, sans donnée personnelle. `order_id`
 * est renseigné si le même panier aboutit ensuite à une commande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 32);
            $table->string('delivery_method', 32);
            $table->unsignedInteger('subtotal_ttc');
            $table->unsignedInteger('minimum_ttc');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_blocks');
    }
};
