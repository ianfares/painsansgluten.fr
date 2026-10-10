<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\ProStatus;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Compte client (guard "web", table `users`). Distinct de `App\Models\Admin`
 * (CLAUDE.md §1, §2) : un client n'est jamais un administrateur.
 *
 * @property AccountType $account_type
 * @property ?ProStatus $pro_status
 * @property ?string $company_name
 * @property ?string $siret
 * @property bool $lab_pickup_allowed
 */
#[Fillable(['first_name', 'last_name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmail, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'account_type' => AccountType::class,
            'pro_status' => ProStatus::class,
            'pro_approved_at' => 'datetime',
            'lab_pickup_allowed' => 'boolean',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Compte pro validé par l'admin. Un pro « en attente » se comporte comme
     * un particulier. Les champs sensibles (type, statut pro, retrait labo,
     * désactivation) ne sont pas assignables en masse : ils sont posés
     * explicitement par l'inscription et par `ManageCustomerAccount`.
     */
    public function isApprovedPro(): bool
    {
        return $this->account_type === AccountType::Pro && $this->pro_status === ProStatus::Approved;
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Nom complet, pour l'affichage et les emails (Fortify/notifications).
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
