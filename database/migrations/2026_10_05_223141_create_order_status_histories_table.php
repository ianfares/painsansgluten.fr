<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique des transitions de statut (PLAN.md §9.2). Table
     * d'audit append-only : une ligne par transition, jamais modifiée.
     */
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from')->nullable();
            $table->string('to');
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
