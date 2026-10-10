<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tableau « À produire » (T27-L9) : quantités à fabriquer pour les commandes
 * payées ou en préparation, filtrées sur la date d'expédition prévue.
 * Aucune donnée de prix. Une seule requête (jointures), pas de N+1.
 */
class ProductionPlan
{
    public const OTHER_CATEGORY = 'Autre';

    public const SORTABLE = ['planned_ship_date', 'order_number', 'customer', 'delivery_method', 'product_name', 'quantity'];

    private readonly CarbonImmutable $from;

    private readonly CarbonImmutable $to;

    /**
     * @param  array<int, int|string>  $categoryIds
     * @param  array<int, int|string>  $productIds
     * @param  array<int, string>  $deliveryMethods  valeurs de App\Enums\DeliveryMethod
     */
    public function __construct(
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        private readonly array $categoryIds = [],
        private readonly array $productIds = [],
        private readonly array $deliveryMethods = [],
    ) {
        $this->from = ($from ?? CarbonImmutable::today())->startOfDay();
        $this->to = ($to ?? CarbonImmutable::today()->addDays(7))->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function fromFilters(array $filters): self
    {
        $date = static function (mixed $value): ?CarbonImmutable {
            try {
                return filled($value) ? CarbonImmutable::parse((string) $value) : null;
            } catch (\Throwable) {
                return null;
            }
        };

        return new self(
            $date($filters['from'] ?? null),
            $date($filters['to'] ?? null),
            (array) ($filters['categories'] ?? []),
            (array) ($filters['products'] ?? []),
            (array) ($filters['delivery_methods'] ?? []),
        );
    }

    /**
     * Total à produire par produit, trié par catégorie puis nom.
     *
     * @return Collection<int, array{product_name: string, category: string, quantity: int}>
     */
    public function totalsByProduct(): Collection
    {
        return $this->rows()
            ->groupBy(fn (array $row): string => $row['product_id'] !== null ? 'p'.$row['product_id'] : 'n'.$row['product_name'])
            ->map(fn (Collection $rows): array => [
                'product_name' => $rows->first()['product_name'],
                'category' => $rows->first()['category'],
                'quantity' => (int) $rows->sum('quantity'),
            ])
            ->sort(fn (array $a, array $b): int => [$a['category'], mb_strtolower($a['product_name'])] <=> [$b['category'], mb_strtolower($b['product_name'])])
            ->values();
    }

    /**
     * Détail par commande.
     *
     * @return Collection<int, array{planned_ship_date: string, order_number: string, customer: string, delivery_method: string, product_name: string, quantity: int}>
     */
    public function details(string $sortBy = 'planned_ship_date', string $direction = 'asc'): Collection
    {
        $sortBy = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'planned_ship_date';
        $desc = $direction === 'desc';

        $rows = $this->rows()->map(fn (array $row): array => [
            'planned_ship_date' => $row['planned_ship_date'],
            'order_number' => $row['order_number'],
            'customer' => $row['customer'],
            'delivery_method' => $row['delivery_method'],
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
        ]);

        // Tri stable : critère choisi, puis date, n° de commande, produit.
        return $rows->sort(function (array $a, array $b) use ($sortBy, $desc): int {
            $cmp = $a[$sortBy] <=> $b[$sortBy];
            if ($desc) {
                $cmp = -$cmp;
            }

            return $cmp !== 0
                ? $cmp
                : [$a['planned_ship_date'], $a['order_number'], $a['product_name']] <=> [$b['planned_ship_date'], $b['order_number'], $b['product_name']];
        })->values();
    }

    /**
     * @return array<int, string> lignes lisibles rappelant les filtres appliqués (en-tête du PDF)
     */
    public function filterSummary(): array
    {
        $lines = ["Date d'expédition prévue : du {$this->from->format('d/m/Y')} au {$this->to->format('d/m/Y')}"];

        if ($this->categoryIds !== []) {
            $lines[] = 'Catégories : '.DB::table('categories')->whereIn('id', $this->categoryIds)->orderBy('name')->pluck('name')->implode(', ');
        }
        if ($this->productIds !== []) {
            $lines[] = 'Produits : '.DB::table('products')->whereIn('id', $this->productIds)->orderBy('name')->pluck('name')->implode(', ');
        }
        if ($this->deliveryMethods !== []) {
            $lines[] = 'Mode de livraison : '.collect($this->deliveryMethods)
                ->map(fn (string $value): string => DeliveryMethod::tryFrom($value)?->label() ?? $value)
                ->implode(', ');
        }

        return $lines;
    }

    /**
     * @return Collection<int, array{product_id: int|null, product_name: string, category: string, quantity: int, planned_ship_date: string, order_number: string, customer: string, delivery_method: string}>
     */
    private function rows(): Collection
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('orders.status', [OrderStatus::Paid->value, OrderStatus::Preparing->value])
            ->whereBetween('orders.planned_ship_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->select([
                'order_items.product_id',
                'order_items.product_name',
                'order_items.quantity',
                'orders.planned_ship_date',
                'orders.number as order_number',
                'orders.first_name',
                'orders.last_name',
                'orders.delivery_method',
                'categories.name as category_name',
            ]);

        if ($this->categoryIds !== []) {
            $query->whereIn('products.category_id', $this->categoryIds);
        }
        if ($this->productIds !== []) {
            $query->whereIn('order_items.product_id', $this->productIds);
        }
        if ($this->deliveryMethods !== []) {
            $query->whereIn('orders.delivery_method', $this->deliveryMethods);
        }

        return $query->get()->map(fn (object $row): array => [
            'product_id' => $row->product_id !== null ? (int) $row->product_id : null,
            'product_name' => (string) $row->product_name,
            'category' => $row->category_name !== null ? (string) $row->category_name : self::OTHER_CATEGORY,
            'quantity' => (int) $row->quantity,
            'planned_ship_date' => (string) $row->planned_ship_date,
            'order_number' => (string) $row->order_number,
            'customer' => trim($row->first_name.' '.$row->last_name),
            'delivery_method' => DeliveryMethod::tryFrom((string) $row->delivery_method)?->label() ?? (string) $row->delivery_method,
        ]);
    }
}
