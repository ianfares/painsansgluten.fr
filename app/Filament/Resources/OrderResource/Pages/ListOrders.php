<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

/**
 * Liste des commandes, en onglets : par défaut les vraies commandes (payées,
 * virements à valider, commandes saisies par l'admin en attente de paiement) ;
 * à part les paiements carte non aboutis, annulées et remboursées.
 */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouvelle commande')];
    }

    public function getTabs(): array
    {
        return [
            'orders' => Tab::make('Commandes')
                ->modifyQueryUsing(fn (Builder $query): Builder => self::scopeOrders($query)),
            'not_completed' => Tab::make('Non abouties, annulées, remboursées')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNot(fn (Builder $q) => self::scopeOrders($q))),
            'all' => Tab::make('Toutes'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'orders';
    }

    /**
     * Commandes à traiter ou traitées : payées (y compris en cours / livrées),
     * virements en attente de validation et commandes manuelles (BO) en attente
     * de paiement. Exclut les paiements carte en cours ou échoués, les annulées
     * et les remboursées.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public static function scopeOrders(Builder $query): Builder
    {
        $paidStates = array_values(array_filter(OrderStatus::cases(), fn (OrderStatus $s): bool => $s->isPaidState()));

        return $query->where(fn (Builder $q) => $q
            ->whereIn('status', $paidStates)
            ->orWhere(fn (Builder $pending) => $pending
                ->where('status', OrderStatus::PendingPayment)
                ->where(fn (Builder $who) => $who
                    ->where('payment_method', PaymentMethod::BankTransfer)
                    ->orWhereNotNull('created_by_admin_id'))));
    }
}
