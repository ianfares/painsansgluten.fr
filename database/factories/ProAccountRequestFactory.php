<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProRequestStatus;
use App\Models\ProAccountRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProAccountRequest>
 */
class ProAccountRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'siret' => '73282932000074',
            'vat_number' => null,
            'activity_type' => 'Restaurant',
            'activity_other' => null,
            'contact_first_name' => fake()->firstName(),
            'contact_last_name' => fake()->lastName(),
            'job_title' => null,
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0612345678',
            'address_line1' => fake()->streetAddress(),
            'postal_code' => '50300',
            'city' => 'Avranches',
            'products_of_interest' => ['Pains'],
            'volumes' => null,
            'description' => fake()->paragraph(),
            'consent_at' => now(),
            'status' => ProRequestStatus::Pending,
        ];
    }
}
