<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Actions\Customers\ManageCustomerAccount;
use App\Enums\AccountType;
use App\Enums\ProStatus;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approvePro')
                ->label('Valider le compte pro')
                ->color('success')
                ->icon('heroicon-o-check')
                ->requiresConfirmation()
                ->modalDescription('Le client reçoit un email « Votre compte professionnel est validé ».')
                ->visible(fn (): bool => $this->user()->account_type === AccountType::Pro && $this->user()->pro_status === ProStatus::Pending)
                ->action(function (): void {
                    app(ManageCustomerAccount::class)->approvePro($this->user());
                    $this->refreshFormData(['pro_status', 'pro_approved_at']);
                    Notification::make()->title('Compte pro validé')->success()->send();
                }),
            Action::make('deactivate')
                ->label('Désactiver le compte')
                ->color('danger')
                ->icon('heroicon-o-no-symbol')
                ->requiresConfirmation()
                ->modalHeading('Désactiver ce compte ?')
                ->modalDescription('Le client ne pourra plus se connecter et sera déconnecté. Sa demande de suppression éventuelle est close. Aucune donnée n\'est effacée : commandes et factures sont conservées.')
                ->visible(fn (): bool => ! $this->user()->isDeactivated())
                ->action(function (): void {
                    app(ManageCustomerAccount::class)->deactivate($this->user());
                    $this->refreshFormData(['deactivated_at', 'deletion_requested_at']);
                    Notification::make()->title('Compte désactivé')->success()->send();
                }),
            Action::make('reactivate')
                ->label('Réactiver le compte')
                ->color('success')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->user()->isDeactivated())
                ->action(function (): void {
                    app(ManageCustomerAccount::class)->reactivate($this->user());
                    $this->refreshFormData(['deactivated_at']);
                    Notification::make()->title('Compte réactivé')->success()->send();
                }),
            EditAction::make(),
        ];
    }

    private function user(): User
    {
        /** @var User $record */
        $record = $this->getRecord();

        return $record;
    }
}
