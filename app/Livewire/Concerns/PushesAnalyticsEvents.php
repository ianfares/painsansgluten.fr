<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Services\Analytics\Ecommerce;

/**
 * Événement GA4 depuis un composant Livewire : envoyé au navigateur
 * (`analytics-event`), poussé dans le dataLayer par resources/js/consent.js.
 */
trait PushesAnalyticsEvents
{
    /** @param  array<string, mixed>  $ecommerce */
    protected function pushAnalytics(string $event, array $ecommerce): void
    {
        if (app(Ecommerce::class)->enabled()) {
            $this->dispatch('analytics-event', name: $event, ecommerce: $ecommerce);
        }
    }
}
