<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T26 A7 : la grille des frais de port se saisit en HT (grille transporteur).
 * Le TTC client = HT × (1 + TVA du port). Les tranches existantes sont
 * converties au taux de TVA du port en vigueur (20 % si non renseigné).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->renameColumn('price_ttc', 'price_ht');
        });

        $divisor = 1 + $this->shippingVatRate() / 100;

        foreach (DB::table('shipping_rates')->get(['id', 'price_ht']) as $rate) {
            DB::table('shipping_rates')->where('id', $rate->id)->update(['price_ht' => (int) round($rate->price_ht / $divisor)]);
        }
    }

    public function down(): void
    {
        $multiplier = 1 + $this->shippingVatRate() / 100;

        foreach (DB::table('shipping_rates')->get(['id', 'price_ht']) as $rate) {
            DB::table('shipping_rates')->where('id', $rate->id)->update(['price_ht' => (int) round($rate->price_ht * $multiplier)]);
        }

        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->renameColumn('price_ht', 'price_ttc');
        });
    }

    private function shippingVatRate(): float
    {
        $payload = DB::table('settings')->where('group', 'shipping')->where('name', 'shipping_vat_rate')->value('payload');
        $rate = $payload !== null ? json_decode((string) $payload, true) : null;

        return is_numeric($rate) ? (float) $rate : 20.0;
    }
};
