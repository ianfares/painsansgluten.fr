<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Mode de livraison d'une commande (T27-L7b). Chronopost Relais est payant ;
 * les deux retraits sont gratuits et passent par « Prête au retrait » puis
 * « Retirée » au lieu de « Expédiée » / « Livrée ».
 */
enum DeliveryMethod: string
{
    case ChronopostRelay = 'chronopost_relay';
    case MerchantPickup = 'merchant_pickup';
    case LabPickup = 'lab_pickup';

    public function label(): string
    {
        return match ($this) {
            self::ChronopostRelay => 'Chronopost Relais',
            self::MerchantPickup => 'Retrait chez un commerçant partenaire',
            self::LabPickup => 'Retrait au laboratoire',
        };
    }

    public function isPickup(): bool
    {
        return $this !== self::ChronopostRelay;
    }
}
