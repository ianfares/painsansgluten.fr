<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Paramètres du paiement par virement (PLAN.md §11). `cancel_after_days`
 * (5) et `auto_cancel_enabled` (true) sont les deux seules valeurs par
 * défaut explicitement validées par la cliente (CLAUDE.md §2) ; tout le
 * reste reste `null` tant que non renseigné.
 */
class BankTransferSettings extends Settings
{
    public ?string $account_holder;

    public ?string $iban;

    public ?string $bic;

    public ?string $bank_name;

    public int $cancel_after_days;

    public bool $auto_cancel_enabled;

    public static function group(): string
    {
        return 'bank_transfer';
    }
}
