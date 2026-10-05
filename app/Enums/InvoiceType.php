<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Type de document de facturation (PLAN.md §12). Une facture émise est
 * immuable ; un remboursement génère un avoir distinct, jamais une
 * modification de la facture d'origine.
 */
enum InvoiceType: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Facture',
            self::CreditNote => 'Avoir',
        };
    }

    /**
     * Préfixe de numérotation séquentielle (PLAN.md §12 : propositions
     * F2026-00001 / A2026-00001, format exact À VALIDER par le comptable).
     */
    public function numberPrefix(): string
    {
        return match ($this) {
            self::Invoice => 'F',
            self::CreditNote => 'A',
        };
    }
}
