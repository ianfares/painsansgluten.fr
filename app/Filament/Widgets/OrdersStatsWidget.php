<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Indicateurs clés du tableau de bord BO (PLAN.md §14, T17). CA = somme des
 * commandes payées ce mois-ci (`paid_at`) ; nb de commandes = commandes
 * créées ce mois-ci (`created_at`) — deux dates différentes, volontairement
 * (voir docs/DECISIONS.md).
 */
class OrdersStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $caTtc = Order::query()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total_ttc');

        $ordersThisMonth = Order::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $pendingBankTransfers = Order::query()
            ->where('payment_method', PaymentMethod::BankTransfer)
            ->where('status', OrderStatus::PendingPayment)
            ->count();

        $toShipToday = Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing])
            ->whereDate('planned_ship_date', now()->toDateString())
            ->count();

        $lateToShip = Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing])
            ->whereDate('planned_ship_date', '<', now()->toDateString())
            ->count();

        return [
            Stat::make('CA TTC du mois', number_format($caTtc / 100, 2, ',', ' ').' €'),
            Stat::make('Commandes du mois', (string) $ordersThisMonth),
            Stat::make('Virements en attente', (string) $pendingBankTransfers),
            Stat::make('À expédier', "Aujourd'hui : {$toShipToday} — En retard : {$lateToShip}")
                ->color($lateToShip > 0 ? 'danger' : 'success'),
        ];
    }
}
