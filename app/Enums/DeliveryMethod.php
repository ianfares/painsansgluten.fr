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

    /**
     * Début de phrase devant la date prévue (24/10/2026, Angélique) : chez un
     * commerçant, la commande se retire le jour de retrait du point.
     */
    public function plannedDatePrefix(): string
    {
        return match ($this) {
            self::ChronopostRelay => 'Expédition prévue le',
            self::MerchantPickup => 'Retrait le',
            self::LabPickup => 'Prête à retirer à partir du',
        };
    }

    public function isPickup(): bool
    {
        return $this !== self::ChronopostRelay;
    }
}
