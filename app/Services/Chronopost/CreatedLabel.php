<?php

declare(strict_types=1);

namespace App\Services\Chronopost;

/** Étiquette renvoyée par `shippingMultiParcelV4` : numéro de LT + PDF décodé. */
final readonly class CreatedLabel
{
    public function __construct(
        public string $skybillNumber,
        public ?string $reservationNumber,
        public string $pdf,
    ) {}
}
