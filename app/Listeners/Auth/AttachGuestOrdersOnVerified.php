<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

/**
 * Rattache au compte les commandes passées en invité avec la même adresse,
 * **uniquement une fois l'email vérifié** (CLAUDE.md §4, T18) : sans cette
 * preuve, n'importe qui pourrait créer un compte avec l'email d'un autre et
 * voir ses commandes. Joue aussi après un changement d'email revérifié.
 */
class AttachGuestOrdersOnVerified
{
    public function handle(Verified $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        Order::query()
            ->whereNull('user_id')
            ->where('email', $event->user->email)
            ->update(['user_id' => $event->user->id]);
    }
}
