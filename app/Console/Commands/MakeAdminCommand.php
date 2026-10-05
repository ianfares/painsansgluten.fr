<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Crée un compte administrateur back-office (guard "admin", table `admins`).
 *
 * Usage : `php artisan make:admin`. Demande interactivement le nom, l'email
 * et le mot de passe (saisie masquée), valide les données, puis crée le
 * compte. N'accepte jamais de mot de passe en argument de ligne de commande
 * (resterait dans l'historique shell).
 */
#[Signature('make:admin')]
#[Description('Crée un compte administrateur du back-office')]
class MakeAdminCommand extends Command
{
    public function handle(): int
    {
        $name = $this->ask('Nom');
        $email = $this->ask('Email');
        $password = $this->secret('Mot de passe (min. 12 caractères)');
        $passwordConfirmation = $this->secret('Confirmer le mot de passe');

        if ($password !== $passwordConfirmation) {
            $this->components->error('Les deux mots de passe ne correspondent pas.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
                'password' => ['required', 'string', 'min:12'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();

        $admin = Admin::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $this->components->info("Administrateur « {$admin->name} » créé ({$admin->email}).");

        return self::SUCCESS;
    }
}
