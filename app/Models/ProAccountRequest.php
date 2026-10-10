<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProRequestStatus;
use Carbon\CarbonInterface;
use Database\Factories\ProAccountRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Demande de compte professionnel (T26 B1). `status`, `admin_comment` et
 * `processed_at` ne sont volontairement pas assignables en masse : seul
 * App\Actions\Pro\ProcessProAccountRequestAction les modifie.
 *
 * @property ProRequestStatus $status
 * @property string $email
 * @property ?CarbonInterface $processed_at
 */
#[Fillable([
    'company_name', 'siret', 'activity_type', 'activity_other',
    'contact_first_name', 'contact_last_name', 'job_title', 'email', 'phone',
    'address_line1', 'postal_code', 'city', 'description', 'consent_at',
])]
class ProAccountRequest extends Model
{
    /**
     * Durée de conservation (en mois) des demandes refusées : au-delà, la
     * commande `pro-requests:purge-rejected` les supprime (RGPD).
     */
    public const RETENTION_MONTHS = 3;

    /** @use HasFactory<ProAccountRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProRequestStatus::class,
            'consent_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public const OTHER = 'Autre';

    public function contactName(): string
    {
        return "{$this->contact_first_name} {$this->contact_last_name}";
    }
}
