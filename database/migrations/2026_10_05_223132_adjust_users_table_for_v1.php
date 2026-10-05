<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adapte la table `users` générée par défaut par Laravel au modèle de
     * données du projet (PLAN.md §22) : prénom/nom séparés, téléphone
     * (obligatoire pour le SMS Chronopost, PLAN §9.1), demande de
     * suppression de compte (PLAN §13).
     *
     * Ne modifie pas la migration `create_users_table` déjà mergée dans
     * `develop` (CLAUDE.md §3.4) : les changements se font ici, dans une
     * migration séparée.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
            $table->string('phone')->nullable()->after('email');
            $table->timestamp('deletion_requested_at')->nullable()->after('remember_token');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'phone', 'deletion_requested_at']);
        });
    }
};
