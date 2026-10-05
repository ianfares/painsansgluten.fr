<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceType;
use App\Models\InvoiceSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceSequence>
 */
class InvoiceSequenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => InvoiceType::Invoice->value,
            'year' => (int) now()->format('Y'),
            'last_number' => 0,
        ];
    }
}
