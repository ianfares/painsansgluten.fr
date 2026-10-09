<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de compte professionnel (T26 B1). Données personnelles : durée de
 * conservation des demandes refusées À CONFIRMER (proposition : 12 mois).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pro_account_requests', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('siret', 14);
            $table->string('vat_number', 20)->nullable();
            $table->string('activity_type', 100);
            $table->string('activity_other', 150)->nullable();
            $table->string('contact_first_name', 100);
            $table->string('contact_last_name', 100);
            $table->string('job_title', 100)->nullable();
            $table->string('email', 150);
            $table->string('phone', 30);
            $table->string('address_line1');
            $table->string('postal_code', 10);
            $table->string('city', 100);
            $table->json('products_of_interest');
            $table->text('volumes')->nullable();
            $table->text('description');
            $table->timestamp('consent_at');
            $table->string('status', 20)->default('pending')->index();
            $table->text('admin_comment')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_account_requests');
    }
};
