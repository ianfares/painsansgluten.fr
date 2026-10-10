<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** T27-L5 : type de compte (particulier/pro), validation pro, retrait labo, désactivation. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type', 20)->default('individual')->after('phone');
            $table->string('company_name')->nullable()->after('account_type');
            $table->string('siret', 14)->nullable()->after('company_name');
            $table->string('pro_status', 20)->nullable()->after('siret');
            $table->timestamp('pro_approved_at')->nullable()->after('pro_status');
            $table->boolean('lab_pickup_allowed')->default(false)->after('pro_approved_at');
            $table->timestamp('deactivated_at')->nullable()->after('lab_pickup_allowed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['account_type', 'company_name', 'siret', 'pro_status', 'pro_approved_at', 'lab_pickup_allowed', 'deactivated_at']);
        });
    }
};
