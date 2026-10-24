<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jour(s) et plage horaire de retrait propres à chaque commerçant
 * (24/10/2026, demande de Ian) : la date de retrait ne suit plus les jours
 * d'expédition Chronopost. Vide = jours d'expédition, comme avant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_points', function (Blueprint $table) {
            $table->json('pickup_weekdays')->nullable()->after('opening_hours');
            $table->time('pickup_from')->nullable()->after('pickup_weekdays');
            $table->time('pickup_until')->nullable()->after('pickup_from');
        });
    }

    public function down(): void
    {
        Schema::table('pickup_points', fn (Blueprint $table) => $table->dropColumn(['pickup_weekdays', 'pickup_from', 'pickup_until']));
    }
};
