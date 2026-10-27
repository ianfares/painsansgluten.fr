<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Format d'étiquette Chronopost réglable (27/10/2026) : la boutique utilise une
 * Zebra ZD220 (thermique 203 dpi) → `THE1015`, PDF 10×15 imprimé via le pilote
 * Zebra (doc WS Chronopost, champ `mode`). `PDF` (A4) reste disponible.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('shipping.chronopost_label_mode', 'THE1015');
    }
};
