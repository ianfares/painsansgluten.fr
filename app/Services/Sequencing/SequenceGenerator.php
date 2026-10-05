<?php

declare(strict_types=1);

namespace App\Services\Sequencing;

use App\Models\InvoiceSequence;

/**
 * Attribution d'un numéro séquentiel continu, sans trou, par type et par
 * année — table `invoice_sequences` (générique malgré son nom : partagée
 * entre commandes "order", factures "invoice" et avoirs "credit_note",
 * voir docs/DECISIONS.md T12/T16). **Doit être appelée à l'intérieur d'une
 * transaction** (le verrou `lockForUpdate` n'a d'effet que dans une
 * transaction englobante).
 */
class SequenceGenerator
{
    public function next(string $type, string $prefix): string
    {
        $year = (int) now()->year;

        $sequence = InvoiceSequence::query()
            ->where('type', $type)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = InvoiceSequence::query()->create(['type' => $type, 'year' => $year, 'last_number' => 0]);
        }

        $sequence->increment('last_number');

        return sprintf('%s%d-%05d', $prefix, $year, $sequence->last_number);
    }
}
