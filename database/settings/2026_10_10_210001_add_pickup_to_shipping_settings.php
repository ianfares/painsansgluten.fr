<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** T27-L7a : retrait au labo (instructions, utilisé en L7b) et distance max des points de retrait. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('shipping.lab_pickup_instructions', null);
        $this->migrator->add('shipping.pickup_max_distance_km', 50);
    }
};
