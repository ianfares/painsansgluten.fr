<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commande (PLAN.md §9, §22). Créée au clic "Payer" avec un snapshot
     * complet (adresse de facturation, relais Chronopost, lignes, totaux) :
     * aucune dépendance aux données vivantes du client/produit par la suite.
     *
     * Montants en centimes (int), jamais de float (CLAUDE.md §3.6).
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status')->default('pending_payment');
            $table->string('payment_method');

            // Coordonnées (PLAN §9.1 : email, prénom, nom, téléphone mobile obligatoires)
            $table->string('email');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');

            // Snapshot adresse de facturation
            $table->string('billing_first_name');
            $table->string('billing_last_name');
            $table->string('billing_company')->nullable();
            $table->string('billing_line1');
            $table->string('billing_line2')->nullable();
            $table->string('billing_postal_code');
            $table->string('billing_city');
            $table->string('billing_country', 2)->default('FR');

            // Snapshot relais Chronopost (structure exacte volontairement en JSON :
            // PLAN.md §9.4 note explicitement que les champs du "snapshot complet"
            // restent à préciser — voir docs/DECISIONS.md).
            $table->string('relay_id');
            $table->string('relay_name');
            $table->json('relay_snapshot');

            // Totaux (centimes)
            $table->unsignedInteger('subtotal_ttc');
            $table->unsignedInteger('shipping_ttc');
            $table->decimal('shipping_vat_rate', 5, 2);
            $table->unsignedInteger('total_ttc');
            $table->unsignedInteger('total_ht');
            $table->unsignedInteger('total_vat');

            $table->date('planned_ship_date')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('tracking_number')->nullable();

            $table->timestamp('cgv_accepted_at');
            $table->boolean('payment_anomaly')->default(false);
            $table->boolean('ga_purchase_sent')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('planned_ship_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
