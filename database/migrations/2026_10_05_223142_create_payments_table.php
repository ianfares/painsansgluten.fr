<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trace de chaque tentative/événement de paiement (Stripe ou virement).
     * `raw` conserve la charge utile brute du prestataire pour investigation,
     * sans jamais contenir de données de carte bancaire (CLAUDE.md §4/§20).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->string('provider_ref')->nullable();
            $table->unsignedInteger('amount');
            $table->string('status');
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index('provider_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
