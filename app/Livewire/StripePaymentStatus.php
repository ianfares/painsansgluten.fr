<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Order;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Statut du paiement carte sur la page de confirmation (T14, PLAN.md §10) :
 * le webhook peut arriver après le retour du client, on rafraîchit toutes
 * les 2 s pendant 30 s au maximum. N'affiche « accepté » que si la BDD le dit.
 */
class StripePaymentStatus extends Component
{
    public const MAX_POLLS = 15;

    #[Locked]
    public string $token;

    public int $polls = 0;

    public function render()
    {
        $this->polls++;

        return view('livewire.stripe-payment-status', [
            'order' => Order::query()->where('token', $this->token)->firstOrFail(),
            'keepPolling' => $this->polls < self::MAX_POLLS,
        ]);
    }
}
