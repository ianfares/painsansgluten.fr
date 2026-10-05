<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Settings\ConfigurationStatus;
use Filament\Widgets\Widget;

/**
 * Alerte "Configuration incomplète" sur le tableau de bord BO (T04).
 */
class ConfigurationAlertsWidget extends Widget
{
    protected static string $view = 'filament.widgets.configuration-alerts-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    // Affiché immédiatement (pas de round-trip Livewire différé) : le calcul
    // est trivial (quelques lectures de settings) et doit être visible dès
    // le chargement du tableau de bord.
    protected static bool $isLazy = false;

    /**
     * @return list<string>
     */
    public function getMissingItems(): array
    {
        return app(ConfigurationStatus::class)->missingItems();
    }
}
