<?php

declare(strict_types=1);

namespace App\Exceptions\Orders;

use RuntimeException;

/** Suppression refusée : commande facturée, réglée, ou paiement Stripe encore possible (24/10/2026). */
class OrderDeletionNotAllowed extends RuntimeException
{
    public static function notDeletable(): self
    {
        return new self('Cette commande ne peut pas être supprimée : seules les commandes non réglées et sans facture peuvent l\'être (une commande payée se rembourse).');
    }

    public static function stripeSessionMayStillBePaid(int $minutes): self
    {
        return new self("Le client peut encore payer cette commande par carte : réessayez dans {$minutes} min.");
    }
}
