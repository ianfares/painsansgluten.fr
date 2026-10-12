<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\PushesAnalyticsEvents;
use App\Models\Order;
use App\Services\Analytics\Ecommerce;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Statut du paiement carte sur la page de confirmation (T14, PLAN.md §10) :
 * le webhook peut arriver après le retour du client, on rafraîchit toutes
 * les 2 s pendant 30 s au maximum. N'affiche « accepté » que si la BDD le dit.
 */
class StripePaymentStatus extends Component
{
    use PushesAnalyticsEvents;

    public const MAX_POLLS = 15;

    #[Locked]
    public string $token;

    public int $polls = 0;

    public function render()
    {
        $this->polls++;
        $order = Order::query()->where('token', $this->token)->firstOrFail();

        // GA4 `purchase` : une seule fois, dès que la BDD dit « payée » (T22).
        $purchase = app(Ecommerce::class)->claimPurchase($order);
        if ($purchase !== null) {
            $this->pushAnalytics('purchase', $purchase);
        }

        return view('livewire.stripe-payment-status', [
            'order' => $order,
            'keepPolling' => $this->polls < self::MAX_POLLS,
        ]);
    }
}
