<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Virement réservé aux pros (15/10/2026) : autorisé par défaut, désactivable compte par compte dans le BO. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('bank_transfer_allowed')->default(true)->after('lab_pickup_allowed');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('bank_transfer_allowed'));
    }
};
