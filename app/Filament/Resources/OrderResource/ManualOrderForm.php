<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\User;
use App\Services\Shipping\DeliveryOptions;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;

/**
 * Formulaire « Nouvelle commande » (T27-L10). Aucun prix n'y est saisi :
 * tout est recalculé par `CreateManualOrderAction` comme dans le tunnel.
 */
final class ManualOrderForm
{
    /** @return array<int, Component> */
    public static function schema(): array
    {
        return [
            Section::make('Client')
                ->columns(3)
                ->schema([
                    Select::make('user_id')
                        ->label('Client')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => User::query()
                            ->whereNull('deactivated_at')
                            ->where(fn ($q) => $q->where('email', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('company_name', 'like', "%{$search}%"))
                            ->limit(20)->get()
                            ->mapWithKeys(fn (User $u) => [$u->id => self::userLabel($u)])->all())
                        ->getOptionLabelUsing(fn ($value): ?string => ($u = User::query()->find($value)) ? self::userLabel($u) : null)
                        ->helperText('Pas encore de compte ? Créez d\'abord le client dans « Clients ».')
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            $address = $state ? User::query()->find($state)?->addresses()->first() : null;
                            $set('billing_line1', $address?->line1);
                            $set('billing_postal_code', $address?->postal_code);
                            $set('billing_city', $address?->city);
                        })
                        ->columnSpanFull(),
                    TextInput::make('billing_line1')->label('Adresse de facturation')->required()->maxLength(255),
                    TextInput::make('billing_postal_code')->label('Code postal')->required()->regex('/^\d{5}$/'),
                    TextInput::make('billing_city')->label('Ville')->required()->maxLength(255),
                ]),

            Section::make('Produits')
                ->description('Prix, remise du client, TVA et frais de port sont calculés automatiquement.')
                ->schema([
                    Repeater::make('lines')
                        ->label('')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->columns(4)
                        ->addActionLabel('Ajouter un produit')
                        ->schema([
                            Select::make('product_id')
                                ->label('Produit')
                                ->options(fn (): array => Product::query()->where('is_published', true)->orderBy('name')->get()
                                    ->mapWithKeys(fn (Product $p) => [$p->id => $p->name.' — '.number_format($p->price_ttc / 100, 2, ',', ' ').' €'])->all())
                                ->searchable()
                                ->required()
                                ->columnSpan(3),
                            TextInput::make('quantity')->label('Quantité')->integer()->minValue(1)->default(1)->required(),
                        ]),
                ]),

            Section::make('Livraison et paiement')
                ->columns(2)
                ->schema([
                    Select::make('delivery_method')
                        ->label('Mode de livraison')
                        ->options(fn (Get $get): array => collect(DeliveryMethod::cases())
                            ->reject(fn (DeliveryMethod $m) => $m === DeliveryMethod::LabPickup
                                && ! app(DeliveryOptions::class)->labPickupAllowed(User::query()->find($get('user_id'))))
                            ->mapWithKeys(fn (DeliveryMethod $m) => [$m->value => $m->label()])->all())
                        ->default(DeliveryMethod::ChronopostRelay->value)
                        ->required()
                        ->live(),
                    Radio::make('payment_method')
                        ->label('Paiement par le client')
                        ->options([PaymentMethod::Stripe->value => 'Carte bancaire (lien de paiement envoyé par email)', PaymentMethod::BankTransfer->value => 'Virement (instructions envoyées par email)'])
                        ->default(PaymentMethod::Stripe->value)
                        ->required(),
                    TextInput::make('relay_name')->label('Relais Chronopost (nom et ville)')->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('delivery_method') === DeliveryMethod::ChronopostRelay->value)
                        ->required(fn (Get $get): bool => $get('delivery_method') === DeliveryMethod::ChronopostRelay->value),
                    TextInput::make('relay_postal_code')->label('Code postal du relais')->regex('/^\d{5}$/')
                        ->visible(fn (Get $get): bool => $get('delivery_method') === DeliveryMethod::ChronopostRelay->value)
                        ->required(fn (Get $get): bool => $get('delivery_method') === DeliveryMethod::ChronopostRelay->value),
                    Select::make('pickup_point_id')->label('Point de retrait')
                        ->options(fn (): array => PickupPoint::query()->bookable()->pluck('name', 'id')->all())
                        ->visible(fn (Get $get): bool => $get('delivery_method') === DeliveryMethod::MerchantPickup->value)
                        ->required(fn (Get $get): bool => $get('delivery_method') === DeliveryMethod::MerchantPickup->value),
                    Placeholder::make('lab_note')->label('')
                        ->content('Retrait gratuit au laboratoire (client pro autorisé).')
                        ->visible(fn (Get $get): bool => $get('delivery_method') === DeliveryMethod::LabPickup->value),
                ]),
        ];
    }

    private static function userLabel(User $user): string
    {
        $name = trim("{$user->first_name} {$user->last_name}");

        return ($user->company_name ? "{$user->company_name} — " : '')."{$name} ({$user->email})";
    }
}
