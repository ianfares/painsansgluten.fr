<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Actions\Customers\ManageCustomerAccount;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Création manuelle d'un client : pas de mot de passe saisi, email de choix du mot de passe (T27-L5). */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Nouveau client';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(ManageCustomerAccount::class)->create($data);
    }
}
