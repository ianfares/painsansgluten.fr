<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étiquettes Chronopost générées depuis le BO (T28). Une étiquette annulée
 * est conservée (traçabilité) avec `cancelled_at` ; au plus une étiquette
 * active par commande (contrôlé sous verrou par GenerateShippingLabelAction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_labels', function (Blueprint $table): void {
            $table->id();
            // Pas de suppression en cascade sur les commandes (QUALITE.md §7).
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('skybill_number', 32)->unique();
            $table->string('reservation_number', 64)->nullable();
            $table->string('pdf_path');
            $table->unsignedInteger('weight_g');
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_labels');
    }
};
