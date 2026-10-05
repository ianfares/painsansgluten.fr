<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Cart;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Purge les paniers invités (sans compte) de plus de 30 jours
 * (PLAN.md §7). Les paniers clients (user_id renseigné) ne sont jamais
 * purgés.
 */
#[Signature('cart:purge-old-guests')]
#[Description('Supprime les paniers invités de plus de 30 jours')]
class PurgeOldGuestCarts extends Command
{
    public function handle(): int
    {
        $count = Cart::query()
            ->whereNull('user_id')
            ->where('updated_at', '<', now()->subDays(30))
            ->get()
            ->each(fn (Cart $cart) => $cart->delete())
            ->count();

        $this->components->info("{$count} panier(s) invité(s) de plus de 30 jours supprimé(s).");

        return self::SUCCESS;
    }
}
