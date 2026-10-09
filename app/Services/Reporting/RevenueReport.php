<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recettes du back-office (page « Recettes », T26). Source : les factures
 * émises moins les avoirs (montants négatifs) — l'argent réellement encaissé,
 * cohérent avec l'export comptable. Commandes non payées ou annulées exclues
 * de fait (pas de facture). Montants en centimes.
 */
class RevenueReport
{
    public const PERIODS = [
        'today' => 'Aujourd\'hui',
        '7d' => '7 derniers jours',
        '30d' => '30 derniers jours',
        '3m' => '3 derniers mois',
        '6m' => '6 derniers mois',
        '12m' => '12 derniers mois',
    ];

    public const DEFAULT_PERIOD = '30d';

    private readonly CarbonImmutable $from;

    private readonly CarbonImmutable $to;

    public function __construct(private readonly string $period = self::DEFAULT_PERIOD, ?CarbonImmutable $now = null)
    {
        $now ??= CarbonImmutable::now();
        $this->to = $now->endOfDay();
        $this->from = match ($period) {
            'today' => $now->startOfDay(),
            '7d' => $now->subDays(6)->startOfDay(),
            '3m' => $now->subMonthsNoOverflow(3)->addDay()->startOfDay(),
            '6m' => $now->subMonthsNoOverflow(6)->addDay()->startOfDay(),
            '12m' => $now->subMonthsNoOverflow(12)->addDay()->startOfDay(),
            default => $now->subDays(29)->startOfDay(),
        };
    }

    public static function for(?string $period): self
    {
        return new self(array_key_exists((string) $period, self::PERIODS) ? (string) $period : self::DEFAULT_PERIOD);
    }

    /**
     * @return array{ttc: int, ht: int, orders: int, average_basket: int}
     */
    public function summary(): array
    {
        $totals = $this->invoices()->selectRaw('COALESCE(SUM(total_ttc), 0) AS ttc, COALESCE(SUM(total_ht), 0) AS ht')->first();
        $invoiced = $this->invoices()->where('type', InvoiceType::Invoice)->count();
        $refunded = $this->invoices()->where('type', InvoiceType::CreditNote)->count();
        $orders = max(0, $invoiced - $refunded);
        $ttc = (int) $totals?->getAttribute('ttc');

        return [
            'ttc' => $ttc,
            'ht' => (int) $totals?->getAttribute('ht'),
            'orders' => $orders,
            'average_basket' => $orders > 0 ? intdiv($ttc, $orders) : 0,
        ];
    }

    /**
     * Recette TTC dans le temps, découpée par heure, jour, semaine ou mois selon la période.
     *
     * @return array<string, int> libellé => centimes
     */
    public function timeline(): array
    {
        [$step, $keyFormat, $labelFormat] = match ($this->period) {
            'today' => ['hour', 'Y-m-d H', 'H\h'],
            '3m' => ['week', 'o-W', '\s\e\m. W'],
            '6m', '12m' => ['month', 'Y-m', 'm/Y'],
            default => ['day', 'Y-m-d', 'd/m'],
        };

        $start = match ($step) {
            'week' => $this->from->startOfWeek(),
            'month' => $this->from->startOfMonth(),
            default => $this->from,
        };

        $buckets = [];
        $labels = [];
        for ($cursor = $start; $cursor <= $this->to; $cursor = $cursor->add(1, $step)) {
            $buckets[$cursor->format($keyFormat)] = 0;
            $labels[$cursor->format($keyFormat)] = $cursor->format($labelFormat);
        }

        foreach ($this->invoices()->get(['issued_at', 'total_ttc']) as $invoice) {
            $key = CarbonImmutable::instance($invoice->issued_at)->tz(config('app.timezone'))->format($keyFormat);
            if (array_key_exists($key, $buckets)) {
                $buckets[$key] += $invoice->total_ttc;
            }
        }

        return collect($buckets)->mapWithKeys(fn (int $amount, string $key) => [$labels[$key] => $amount])->all();
    }

    /**
     * @return array<string, int> catégorie => centimes TTC
     */
    public function byCategory(): array
    {
        return $this->signedItems()
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy('categories.name')
            ->selectRaw('COALESCE(categories.name, ?) AS label, SUM(CAST(order_items.line_total_ttc AS SIGNED) * CASE WHEN invoices.type = ? THEN -1 ELSE 1 END) AS amount', ['Autre', InvoiceType::CreditNote->value])
            ->get()
            ->pipe(fn (Collection $rows) => $this->toChartData($rows));
    }

    /**
     * @return array<string, int> produit => centimes TTC (10 premiers)
     */
    public function topProducts(int $limit = 10): array
    {
        return $this->signedItems()
            ->groupBy('order_items.product_name')
            ->selectRaw('order_items.product_name AS label, SUM(CAST(order_items.line_total_ttc AS SIGNED) * CASE WHEN invoices.type = ? THEN -1 ELSE 1 END) AS amount', [InvoiceType::CreditNote->value])
            ->orderByDesc('amount')
            ->limit($limit)
            ->get()
            ->pipe(fn (Collection $rows) => $this->toChartData($rows));
    }

    /**
     * @return array<string, int> moyen de paiement => centimes TTC
     */
    public function byPaymentMethod(): array
    {
        return DB::table('invoices')
            ->join('orders', 'orders.id', '=', 'invoices.order_id')
            ->whereBetween('invoices.issued_at', [$this->from, $this->to])
            ->groupBy('orders.payment_method')
            ->selectRaw('orders.payment_method AS label, SUM(invoices.total_ttc) AS amount')
            ->get()
            ->each(fn (\stdClass $row) => $row->label = PaymentMethod::tryFrom((string) $row->label)?->label() ?? (string) $row->label)
            ->pipe(fn (Collection $rows) => $this->toChartData($rows));
    }

    /** @return Builder<Invoice> */
    private function invoices(): Builder
    {
        return Invoice::query()->whereBetween('issued_at', [$this->from, $this->to]);
    }

    /** Lignes des commandes facturées sur la période ; un avoir compte en négatif. */
    private function signedItems(): \Illuminate\Database\Query\Builder
    {
        return DB::table('invoices')
            ->join('order_items', 'order_items.order_id', '=', 'invoices.order_id')
            ->whereBetween('invoices.issued_at', [$this->from, $this->to]);
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     * @return array<string, int>
     */
    private function toChartData(Collection $rows): array
    {
        return $rows->mapWithKeys(fn ($row) => [(string) $row->label => (int) $row->amount])
            ->filter(fn (int $amount) => $amount > 0)
            ->sortDesc()
            ->all();
    }
}
