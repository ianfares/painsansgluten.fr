<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Compte client pro créé (ou basculé en pro) à l'approbation d'une demande (correctif du 11/10/2026). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pro_account_requests', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pro_account_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
    }
};
