<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Mentions légales de facturation (PLAN.md §12). Taux de TVA, mentions et
 * formats de numérotation : à valider par l'expert-comptable avant
 * production (CLAUDE.md §3.1) — jamais de valeur inventée ici.
 */
class BillingSettings extends Settings
{
    public ?string $company_name;

    public ?string $legal_form;

    public ?string $share_capital;

    public ?string $address;

    public ?string $siret;

    public ?string $rcs;

    public ?string $vat_number;

    public ?string $invoice_footer_mentions;

    public ?string $invoice_number_format;

    public ?string $credit_note_number_format;

    public static function group(): string
    {
        return 'billing';
    }
}
