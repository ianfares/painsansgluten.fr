<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une seule facture et un seul avoir par commande (V1 : remboursement total
 * uniquement) : garde-fou en base contre un double traitement de la file
 * d'attente (audit de sécurité du 2026-10-09).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'type']);
        });
    }
};
