<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Actions\Orders\CreateManualOrderAction;
use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Exceptions\Orders\ManualOrderNotAllowed;
use App\Filament\Resources\OrderResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/** Commande manuelle (T27-L10) : tout passe par CreateManualOrderAction. */
class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Nouvelle commande';

    protected static bool $canCreateAnother = false;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateManualOrderAction::class)->execute(
                adminId: (int) auth('admin')->id(),
                user: User::query()->findOrFail($data['user_id']),
                lines: array_values($data['lines'] ?? []),
                deliveryMethod: DeliveryMethod::from($data['delivery_method']),
                delivery: [
                    'relay_name' => $data['relay_name'] ?? null,
                    'relay_postal_code' => $data['relay_postal_code'] ?? null,
                    'pickup_point_id' => $data['pickup_point_id'] ?? null,
                ],
                billing: ['line1' => $data['billing_line1'], 'postal_code' => $data['billing_postal_code'], 'city' => $data['billing_city']],
                paymentMethod: PaymentMethod::from($data['payment_method']),
            );
        } catch (ManualOrderNotAllowed $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            throw new Halt;
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Commande créée, email envoyé au client.';
    }

    protected function getRedirectUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
