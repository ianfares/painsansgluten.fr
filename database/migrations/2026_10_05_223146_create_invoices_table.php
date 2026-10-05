<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Facture ou avoir (PLAN.md §12). Immuable une fois émise : le
     * `snapshot` JSON permet de régénérer le PDF à l'identique, sans
     * dépendre des données vivantes de la commande/du client.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('number')->unique();
            $table->timestamp('issued_at');
            $table->json('snapshot');
            $table->unsignedInteger('total_ht');
            $table->unsignedInteger('total_vat');
            $table->unsignedInteger('total_ttc');
            $table->string('pdf_path')->nullable();
            $table->foreignId('related_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
