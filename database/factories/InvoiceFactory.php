<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->numberBetween(1000, 9000);

        return [
            'order_id' => Order::factory(),
            'type' => InvoiceType::Invoice,
            'number' => 'F'.now()->year.'-'.fake()->unique()->numerify('#####'),
            'issued_at' => now(),
            'snapshot' => ['lines' => []],
            'total_ht' => (int) round($total / 1.055),
            'total_vat' => $total - (int) round($total / 1.055),
            'total_ttc' => $total,
            'pdf_path' => null,
            'related_invoice_id' => null,
        ];
    }
}
