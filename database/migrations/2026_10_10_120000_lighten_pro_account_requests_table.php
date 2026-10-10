<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T27-L3 : formulaire pro allégé. Seuls nom, prénom, téléphone et email
 * restent obligatoires ; les champs « Produits », « Volumes » et « N° de TVA »
 * disparaissent (0 demande en préprod au 10/10/2026, aucune donnée perdue).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pro_account_requests', function (Blueprint $table) {
            $table->string('company_name')->nullable()->change();
            $table->string('siret', 14)->nullable()->change();
            $table->string('activity_type', 100)->nullable()->change();
            $table->string('address_line1')->nullable()->change();
            $table->string('postal_code', 10)->nullable()->change();
            $table->string('city', 100)->nullable()->change();
            $table->text('description')->nullable()->change();
        });

        Schema::table('pro_account_requests', function (Blueprint $table) {
            $table->dropColumn(['vat_number', 'products_of_interest', 'volumes']);
        });
    }

    /**
     * Restaure les colonnes supprimées (vides) et les contraintes NOT NULL.
     * Les champs vides reçoivent '' pour que le retour arrière ne casse pas ;
     * le contenu des colonnes supprimées est perdu.
     */
    public function down(): void
    {
        Schema::table('pro_account_requests', function (Blueprint $table) {
            $table->string('vat_number', 20)->nullable();
            $table->json('products_of_interest')->nullable();
            $table->text('volumes')->nullable();
        });

        foreach (['company_name', 'siret', 'activity_type', 'address_line1', 'postal_code', 'city', 'description'] as $column) {
            DB::table('pro_account_requests')->whereNull($column)->update([$column => '']);
        }

        DB::table('pro_account_requests')->update(['products_of_interest' => '[]']);

        Schema::table('pro_account_requests', function (Blueprint $table) {
            $table->string('company_name')->nullable(false)->change();
            $table->string('siret', 14)->nullable(false)->change();
            $table->string('activity_type', 100)->nullable(false)->change();
            $table->string('address_line1')->nullable(false)->change();
            $table->string('postal_code', 10)->nullable(false)->change();
            $table->string('city', 100)->nullable(false)->change();
            $table->text('description')->nullable(false)->change();
            $table->json('products_of_interest')->nullable(false)->change();
        });
    }
};
