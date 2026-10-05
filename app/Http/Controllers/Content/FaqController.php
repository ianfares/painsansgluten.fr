<?php

declare(strict_types=1);

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\FaqItem;
use Illuminate\View\View;

/**
 * Page FAQ publique (PLAN.md §16.2) : groupée, avec données structurées
 * `FAQPage` (JSON-LD).
 */
class FaqController extends Controller
{
    public function __invoke(): View
    {
        $groups = FaqItem::query()
            ->where('is_published', true)
            ->orderBy('position')
            ->get()
            ->groupBy(fn (FaqItem $item) => $item->group ?? 'Général');

        return view('content.faq', ['groups' => $groups]);
    }
}
