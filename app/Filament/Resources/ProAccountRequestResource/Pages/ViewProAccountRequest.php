<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProAccountRequestResource\Pages;

use App\Actions\Pro\ProcessProAccountRequestAction;
use App\Enums\ProRequestStatus;
use App\Filament\Resources\ProAccountRequestResource;
use App\Models\ProAccountRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

class ViewProAccountRequest extends ViewRecord
{
    protected static string $resource = ProAccountRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approuver')
                ->color('success')
                ->icon('heroicon-o-check')
                ->modalDescription('Un compte client professionnel validé est créé (le demandeur reçoit un email pour choisir son mot de passe). Si un client a déjà cet email, son compte devient professionnel.')
                ->form([Textarea::make('admin_comment')->label('Commentaire (facultatif, repris dans l\'email)')->maxLength(1000)])
                ->visible(fn (): bool => $this->isPending())
                ->action(fn (array $data) => $this->process(fn (ProcessProAccountRequestAction $a, ProAccountRequest $r) => $a->approve($r, $data['admin_comment'] ?? null), 'Demande approuvée, compte client pro prêt')),
            Action::make('createAccount')
                ->label('Créer le compte client')
                ->icon('heroicon-o-user-plus')
                ->modalDescription('Pour une demande approuvée sans compte client : le compte pro est créé (ou le compte existant passe pro) et le demandeur est prévenu par email.')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->needsAccount())
                ->action(fn () => $this->process(fn (ProcessProAccountRequestAction $a, ProAccountRequest $r) => $a->createAccountFor($r), 'Compte client pro créé')),
            Action::make('reject')
                ->label('Refuser')
                ->color('danger')
                ->icon('heroicon-o-x-mark')
                ->modalDescription('Le demandeur reçoit un email poli, avec votre commentaire s\'il y en a un.')
                ->form([Textarea::make('admin_comment')->label('Commentaire (facultatif, repris dans l\'email)')->maxLength(1000)])
                ->visible(fn (): bool => $this->isPending())
                ->action(fn (array $data) => $this->process(fn (ProcessProAccountRequestAction $a, ProAccountRequest $r) => $a->reject($r, $data['admin_comment'] ?? null), 'Demande refusée')),
        ];
    }

    private function needsAccount(): bool
    {
        /** @var ProAccountRequest $record */
        $record = $this->getRecord();

        return $record->status === ProRequestStatus::Approved && $record->user_id === null;
    }

    private function isPending(): bool
    {
        /** @var ProAccountRequest $record */
        $record = $this->getRecord();

        return $record->status === ProRequestStatus::Pending;
    }

    /**
     * @param  callable(ProcessProAccountRequestAction, ProAccountRequest): void  $callback
     */
    private function process(callable $callback, string $successTitle): void
    {
        /** @var ProAccountRequest $record */
        $record = $this->getRecord();

        try {
            $callback(app(ProcessProAccountRequestAction::class), $record);
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($successTitle)->success()->send();
    }
}
