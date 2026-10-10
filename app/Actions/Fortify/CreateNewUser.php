<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Enums\AccountType;
use App\Enums\ProStatus;
use App\Mail\Admin\NewProAccountToValidateMail;
use App\Models\User;
use App\Rules\Siret;
use App\Services\Mail\AdminMailer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Valide et crée un nouveau compte client (PLAN.md §9.1, §13 ; T27-L5).
     * Un compte « professionnel » est créé immédiatement mais en attente de
     * validation par l'admin : il se comporte comme un particulier d'ici là.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $input['account_type'] = $input['account_type'] ?? AccountType::Individual->value;
        $input['siret'] = preg_replace('/[\s.]/', '', (string) ($input['siret'] ?? ''));

        Validator::make($input, [
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['exclude_unless:account_type,'.AccountType::Pro->value, 'required', 'string', 'max:255'],
            'siret' => ['exclude_unless:account_type,'.AccountType::Pro->value, 'required', new Siret],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ], [
            'company_name.required' => 'La raison sociale est obligatoire pour un compte professionnel.',
            'siret.required' => 'Le numéro SIRET est obligatoire pour un compte professionnel.',
        ])->validate();

        $isPro = $input['account_type'] === AccountType::Pro->value;

        $user = new User;
        $user->forceFill([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'phone' => $input['phone'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'account_type' => $isPro ? AccountType::Pro : AccountType::Individual,
            'company_name' => $isPro ? $input['company_name'] : null,
            'siret' => $isPro ? $input['siret'] : null,
            'pro_status' => $isPro ? ProStatus::Pending : null,
        ])->save();

        if ($isPro) {
            AdminMailer::queue(new NewProAccountToValidateMail($user));
        }

        return $user;
    }
}
