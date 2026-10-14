<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adresses IP déjà vues à la connexion admin (empreinte HMAC, jamais l'IP en clair) : alerte sur une nouvelle IP. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_known_ips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->char('ip_hash', 64);
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['admin_id', 'ip_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_known_ips');
    }
};
