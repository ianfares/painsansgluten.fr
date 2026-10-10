<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T27-L10 : commande créée à la main par l'admin (traçabilité) et
 * commande validée sans paiement « par avoir » (référence/motif mentionnés
 * sur la facture).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('created_by_admin_id')->nullable()->after('user_id')->constrained('admins')->nullOnDelete();
            $table->string('settlement_reference', 150)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_admin_id');
            $table->dropColumn('settlement_reference');
        });
    }
};
