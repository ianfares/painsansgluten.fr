<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onglets de page (24/10/2026, demande de Ian, « Où nous trouver ») :
 * liste de {title, content, show_pickup_points}. Vide = page sans onglets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('tabs')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('pages', fn (Blueprint $table) => $table->dropColumn('tabs'));
    }
};
